<?php

namespace Jtar;

class Server
{

    public $_mainSocket;
    public $_local_socket;

    static public $_connections = [];

    public function __construct($_local_socket)
    {
        $this->_local_socket = $_local_socket;
    }

    public function Listen()
    {
        $flag = STREAM_SERVER_LISTEN | STREAM_SERVER_BIND;

        $option['socket']['backlog'] = 10;

        $context = stream_context_create($option);

        $this->_mainSocket = stream_socket_server($this->_local_socket, $errno, $errstr, $flag, $context);

        //
        if (!is_resource($this->_mainSocket)) {
            fprintf(STDOUT, "server create fail:%s\n", $errstr);
            exit(0);
        }
    }

    public function Accept()
    {
        $connfd = stream_socket_accept($this->_mainSocket, -1);

        if (is_resource($this->_mainSocket)) {

            fprintf(STDOUT, "accept success:%s\n", $connfd);
            static::$_connections[(int)$connfd] = $connfd;
        }
    }
}