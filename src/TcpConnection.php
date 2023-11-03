<?php

namespace Jtar;

class TcpConnection
{
    public $_sockfd;

    public $_clientIp;

    public $_server;

    public $_readBufferSize = 1024;

    // 当前连接接收缓冲区大小
    public $_recvBufferSize = 1024 * 100;
    // 当前连接接收到的字节数
    public $_recvLen = 0;
    // 当前连接接收到的字节数超过_recvBufferSize时,记录1
    public $_recvBufferFull = 0;

    //  接收缓冲区
    public $_recvBuffer = '';

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
        if ($this->_recvLen < $this->_recvBufferSize) {

            $data = fread($this->sockfd(), $this->_readBufferSize);

            // 1正常接收, 接收到的数据>0
            // 2对端关闭了, 接收到的字节数为0
            // 3 错误,
            if ($data === '' || $data === false) {

                if (feof($this->_sockfd) || !is_resource($this->_sockfd)) {
                    $this->Close();
                    return;
                }
            } else {
                // 接收到的数据放在接收缓冲区
                $this->_recvBuffer .= $data;
                $this->_recvLen += strlen($data);
            }

            if ($this->_recvLen > 0) {
                // 封包和拆包, 必须要知道这条消息的完整长度.
            }

//                /**
//                 * @var Server $server
//                 */
//                $server = $this->_server;
//                $server->runEventCallBack('receive', [$data, $this]);


        } else {
            $this->_recvBufferFull++;
        }
    }

    public function Close()
    {

        /**
         * @var Server $server
         */
        $server = $this->_server;

        $server->onClientLeave($this->_sockfd);

        $server->runEventCallBack('close', [$this]);
    }

    public function write2Socket($data)
    {
        $len = strlen($data);
        $writeLen = fwrite($this->sockfd(), $data, $len);

        fprintf(STDOUT, "我写了<%d>客户端的数据字节:%d\r\n", (int)$this->_sockfd, $len);

    }
}