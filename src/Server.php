<?php

namespace Jtar;

use Jtar\Protocol\Stream;

class Server
{
    public $_mainSocket;
    public $_local_socket;

    public static $_connections = [];

    public $_events = [];

    public $_protocol;

    public function __construct($_local_socket)
    {
        $this->_local_socket = $_local_socket;

        $this->_protocol = new Stream();
    }


    public function on($eventName, $eventCall)
    {
        $this->_events[$eventName] = $eventCall;
    }

    public function Listen()
    {
        $flag = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;

        $option['socket']['backlog'] = 10;

        $context = stream_context_create($option);

        $this->_mainSocket = stream_socket_server($this->_local_socket, $errno, $errstr, $flag, $context);

        fprintf(STDOUT, "listen on:%s\r\n", $this->_local_socket);

        if (!is_resource($this->_mainSocket)) {
            fprintf(STDOUT, "server create fail:%s\n", $errstr);
            exit(0);
        }

        stream_set_blocking($this->_mainSocket, 0);
    }


    public function eventLoop()
    {
        while (1) {
            // 这里很神奇,注释的用着就报错了.
            $readFds = [$this->_mainSocket];
//            $readFds[] = $this->_mainSocket;

            $writeFds = [];
            $exceptFds = [];

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

            $ret = stream_select($readFds, $writeFds, $exceptFds, 5, NULL);

            if ($ret === FALSE) {
                break;
            }

            if ($readFds) {
                foreach ($readFds as $fd) {
                    if ($fd == $this->_mainSocket) {
                        $this->Accept();
                    } else {

                        /**
                         * @var TcpConnection $connection
                         */
                        $connection = static::$_connections[(int)$fd];

                        $connection->recv4socket();
                    }
                }
            }
        }
    }

    public function runEventCallBack($eventName, $args = [])
    {
        var_dump($eventName);
        if (isset($this->_events[$eventName]) && is_callable($this->_events[$eventName])) {
            $this->_events[$eventName]($this, ...$args);
        }
    }

    public function Accept()
    {
        $connfd = stream_socket_accept($this->_mainSocket, -1, $peername);

        if (is_resource($connfd)) {

            $connection = new TcpConnection($connfd, $peername, $this);

            static::$_connections[(int)$connfd] = $connection;

            $this->runEventCallBack('connect', [$connection]);
        }
    }

    public function onClientLeave($sockfd)
    {
        if (isset(static::$_connections[(int)$sockfd])) {

            if (is_resource($sockfd)) {
                (fclose($sockfd));
            }

            unset(static::$_connections[(int)$sockfd]);
        }
    }


    public function Start()
    {
        $this->Listen();

        $this->Accept();

        $this->eventLoop();
    }
}