<?php

namespace Jtar;

use Jtar\Protocol\Stream;
use Jtar\Protocol\Text;

class Server
{
    public $_mainSocket;
    public $_local_socket;

    public static $_connections = [];

    public $_events = [];

    public $_protocol = null;

    public $_protocol_layout;

    public $_usingProtocol;


    // 客户端连接数量
    static public $_clientNum = 0;
    //  执行recv/fread调用次数
    static public $_recvNum = 0;
    // 1秒钟接收了多少条消息
    static public $_msgNum = 0;

    public $_protocols = [
        'stream' => Stream::class,
        "text" => Text::class,
        "ws" => "",
        "http" => "",
        "mqtt" => ""
    ];

    public int $_startTime = 0;

    public function __construct($_local_socket)
    {
        list($protocol, $ip, $port) = explode(":", $_local_socket);

        if (isset($this->_protocols[$protocol])) {
            $this->_usingProtocol = $protocol;

            $this->_protocol = new $this->_protocols[$protocol]();
        } else {
            $this->_usingProtocol = "tcp";
        }

        $this->_startTime = time();

        $this->_local_socket = "tcp:" . $ip . ":" . $port;
    }

    public function statistics()
    {
        $nowTime = time();

        $diffTime = $nowTime - $this->_startTime;

        if ($diffTime >= 1) {
            fprintf(STDOUT, "clientNum:%d, recvNum:%d, msgNum:%d\r\n", static::$_clientNum, static::$_recvNum, static::$_msgNum);

            static::$_recvNum = 0;
            static::$_msgNum = 0;
            $this->_startTime = $nowTime;
        }
    }

    public function on($eventName, $eventCall)
    {
        $this->_events[$eventName] = $eventCall;
    }

    public function Listen()
    {
        $flag = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;

        $option['socket']['backlog'] = 1024;

        $context = stream_context_create($option);

        $this->_mainSocket = stream_socket_server($this->_local_socket, $errno, $errstr, $flag, $context);

        fprintf(STDOUT, "listen on:%s\r\n", $this->_local_socket);

        if (!is_resource($this->_mainSocket)) {
            fprintf(STDOUT, "server create fail:%s\n", $errstr);
            exit(0);
        }

        // 设置为非阻塞方式
        stream_set_blocking($this->_mainSocket, 0);
    }


    public function eventLoop()
    {
        while (1) {
            $readFds = [$this->_mainSocket];

            $writeFds = [];
            $exceptFds = [];

            $this->statistics();

            $this->checkHeartTime();

            if (!empty(static::$_connections)) {
                foreach (static::$_connections as $idx => $connection) {

                    $sockfd = $connection->sockfd();

                    if (is_resource($sockfd)) {
                        $readFds[] = $sockfd;
                        $writeFds[] = $sockfd;
                    }
                }
            }

            // tv_sec设置为0 则很快就返回了, 不需要等待, 导致该函数一直执行占用cpu..
            // 给null的话有客户端连接才执行

            $ret = stream_select($readFds, $writeFds, $exceptFds, 0, 100);

            if ($ret === FALSE) {
                break;
            }

            if ($readFds) {
                foreach ($readFds as $fd) {
                    if ($fd == $this->_mainSocket) {
                        $this->Accept();
                    } else {


                        if (isset(static::$_connections[(int)$fd])) {
                            /**
                             * @var TcpConnection $connection
                             */
                            $connection = static::$_connections[(int)$fd];
                            if ($connection->isConnected()) {
                                $connection->recv4socket();
                            }
                        }
                    }
                }
            }

            if ($writeFds) {
                foreach ($writeFds as $fd) {

                    if (isset(static::$_connections[(int)$fd])) {
                        /**
                         * @var TcpConnection $connection
                         */
                        $connection = static::$_connections[(int)$fd];

                        if ($connection->isConnected()) {
                            $connection->write2socket();
                        }
                    }
                }
            }
        }
    }


    public function checkHeartTime()
    {
        foreach (static::$_connections as $idx => $connection) {

            if ($connection->checkHeartTime()) {
                $connection->Close();
            }
        }
    }


    public function runEventCallBack($eventName, $args = [])
    {
        if (isset($this->_events[$eventName]) && is_callable($this->_events[$eventName])) {
            $this->_events[$eventName]($this, ...$args);
        }
    }

    public function Accept()
    {
        $connfd = stream_socket_accept($this->_mainSocket, -1, $peername);

        if (is_resource($connfd)) {

            $connection = new TcpConnection($connfd, $peername, $this);

            $this->onClientJoin();

            static::$_connections[(int)$connfd] = $connection;

            $this->runEventCallBack('connect', [$connection]);
        }
    }

    public function onClientJoin()
    {
        ++static::$_clientNum;
    }

    public function removeClient($sockfd)
    {
        if (isset(static::$_connections[(int)$sockfd])) {

            if (is_resource($sockfd)) {
                (fclose($sockfd));
            }

            unset(static::$_connections[(int)$sockfd]);

            --static::$_clientNum;
        }
    }


    public function onRecv()
    {
        ++static::$_recvNum;
    }

    public function onMsg()
    {
        ++static::$_msgNum;
    }

    public function Start()
    {
        $this->Listen();

//        $this->Accept();

        $this->eventLoop();
    }
}
