<?php

namespace Jtar;

class TcpConnection
{
    public $_sockfd;

    public $_clientIp;

    public $_server;

    public function __construct($_sockfd, $_clientIp, $_server)
    {
        $this->_sockfd = $_sockfd;
        $this->_clientIp = $_clientIp;
        $this->_server = $_server;
    }


    public function sockfd()
    {
        return $this->_sockfd;
    }


    public function recv4socket()
    {
        $data = fread($this->sockfd(), 1024);

        // fprintf(STDOUT, "接收到<%d>客户端的数据:%s\r\n", (int)$this->_sockfd, $data);

        if ($data) {
            /**
             * @var Server $server
             */
            $server = $this->_server;

            $server->runEventCallBack('receive', [$data, $this]);
        }

    }

    public function write2Socket($data)
    {
        $len = strlen($data);
        $writeLen = fwrite($this->sockfd(), $data, $len);

        fprintf(STDOUT, "我写了<%d>客户端的数据字节:%d\r\n", (int)$this->_sockfd, $len);

    }
}