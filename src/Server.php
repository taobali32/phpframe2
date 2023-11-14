<?php

namespace Jtar;

use Jtar\Event\Epoll;
use Jtar\Event\Event;
use Jtar\Event\Select;
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

    static public $_eventLoop;

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


        if (DIRECTORY_SEPARATOR == "/") {
            static::$_eventLoop = new Epoll();
        } else {
            static::$_eventLoop = new Select();
        }

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
        static::$_eventLoop->loop();
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

//            print_r("接受到客户端连接了");
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
                fclose($sockfd);
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

        static::$_eventLoop->add($this->_mainSocket, Event::EV_READ, [$this, 'Accept']);

//        static::$_eventLoop->add(2, Event::EV_TIMER, [$this, 'checkHeartTime']);
        $timerId = static::$_eventLoop->add(1, Event::EV_TIMER, function ($timerId, $arg) {
//            var_dump($arg);

            var_dump($timerId);

            static::$_eventLoop->del($timerId, Event::EV_TIMER);
        }, ['name' => 'xxx']);

//        $this->Accept();

        $this->eventLoop();
    }
}
