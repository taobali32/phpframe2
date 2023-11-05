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

    public $_sendLen = 0;
    public $_sendBuffer = '';
    public $_sendBufferSize = 1024 * 1000;

    public $_sendBufferFull = 0;

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

                $this->_server->onRecv();
            }
        } else {
            $this->_recvBufferFull++;
        }

        if ($this->_recvLen > 0) {
            $this->handleMessage();
        }
    }

    public function handleMessage()
    {
        /**
         * @var Server $server
         */
        $server = $this->_server;

        if (is_object($server->_protocol) && $server->_protocol != null) {

            while ($server->_protocol->Len($this->_recvBuffer)) {

                $msgLen = $this->_server->_protocol->msgLen($this->_recvBuffer);

                $oneMsg = substr($this->_recvBuffer, 0, $msgLen);

                $this->_recvBuffer = substr($this->_recvBuffer, $msgLen);

                $this->_recvLen -= $msgLen;
                $this->_recvBufferFull--;
                $this->_server->onMsg();

                $message = $this->_server->_protocol->decode($oneMsg);

                $this->_server->runEventCallBack('receive', [$message, $this]);
            }

        } else {
            $server->runEventCallBack("receive", [$this->_recvBuffer, $this]);

            $this->_recvBuffer = "";
            $this->_recvLen = 0;
            $this->_recvBufferFull = 0;
//            $this->_server->onMsg();

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

    public function send($data)
    {
        $len = strlen($data);

        /**
         * @var Server $server
         */
        $server = $this->_server;

        if ($this->_sendLen + $len < $this->_sendBufferSize) {

            if (is_object($server->_protocol) && $server->_protocol != null) {
                $bin = $this->_server->_protocol->encode($data);

                $this->_sendBuffer .= $bin[1];
                $this->_sendLen += $bin[0];
            } else {

                $this->_sendBuffer .= $data;
                $this->_sendLen += $len;
            }

            if ($this->_sendLen >= $this->_sendBufferSize) {
                $this->_sendBufferFull++;
            }
        }

        $writeLen = fwrite($this->_sockfd, $this->_sendBuffer, $this->_sendLen);

        // 完整发送
        if ($writeLen == $this->_sendLen) {

            $this->_sendBuffer = '';
            $this->_sendLen = 0;
            $this->_recvBufferFull = 0;

            return true;
        } elseif ($writeLen < $this->_sendLen) {
            // 只发送一半
            $this->_sendBuffer = substr($this->_sendBuffer, $writeLen);
            $this->_sendLen -= $writeLen;
        } else {
            // 对端关了
            $this->Close();
        }
    }

    public function write2Socket($data)
    {
        $bin = $this->_server->_protocol->encode($data);

        $writeLen = fwrite($this->_sockfd, $bin[1], $bin[0]);

        var_dump("send data to client...");
    }
}