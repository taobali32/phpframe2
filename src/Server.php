<?php

namespace Jtar;

class Server
{

    public $_mainSocket;
    public $_local_socket;

    static public $_connections = [];


    public $_events = [];

    public function __construct($_local_socket)
    {
        $this->_local_socket = $_local_socket;
    }


    public function on($eventName, $eventCall)
    {
        $this->_events[$eventName] = $eventCall;
    }

    public function Listen()
    {
        $flag = STREAM_SERVER_LISTEN | STREAM_SERVER_BIND;

        $option['socket']['backlog'] = 10;

        $context = stream_context_create($option);

        $this->_mainSocket = stream_socket_server($this->_local_socket, $errno, $errstr, $flag, $context);

        fprintf(STDOUT, "listen on:%s\r\n", $this->_local_socket);

        if (!is_resource($this->_mainSocket)) {
            fprintf(STDOUT, "server create fail:%s\n", $errstr);
            exit(0);
        }
    }

    public function eventLoop()
    {
        // 返回0没有任何事件发生, 返回false就是错误了,

        while (1) {

            $readFds[] = $this->_mainSocket;
            $writeFds = [];
            $exceptFds = [];

            if (!empty(self::$_connections)) {
                foreach (self::$_connections as $idx => $connection) {

                    $sockfd = $connection->sockfd();
                    $readFds[] = $sockfd;
                    $writeFds[] = $sockfd;
                }
            }
            // tv_sec设置为0 则很快就返回了, 不需要等待, 导致该函数一直执行占用cpu..
            // 给null的话有客户端连接才执行
            $ret = stream_select($readFds, $writeFds, $exceptFds, NULL, NULL);

            if ($ret === FALSE) {
                break;
            }

            if ($readFds) {
                foreach ($readFds as $fd) {
                    if ($fd == $this->_mainSocket) {
                        $this->Accept();
                    }

                    /**
                     * @var TcpConnection $connection
                     */
                    $connection = static::$_connections[(int)$fd];

                    $connection->recv4socket();

//                    else {
//                        $data = fread($fd, 1024);
//                        if ($data) {
//                            fprintf(STDOUT, "接收到<%d>客户端的数据:%s\r\n", (int)$fd, $data);
//
//                            fwrite($fd, "server");
//                        }
//                    }
                }
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

        if (is_resource($this->_mainSocket)) {

            $connection = new TcpConnection($connfd, $peername, $this);

            static::$_connections[(int)$connfd] = $connection;

            $this->runEventCallBack('connect', [$connection]);
        }
    }
}