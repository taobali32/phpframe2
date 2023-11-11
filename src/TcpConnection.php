<?php

namespace Jtar;

use Jtar\Event\Event;

class TcpConnection
{
    public $_sockfd;

    public $_clientIp;

    /**
     * @var Server $_server
     */
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

    public $_heartTime = 0;

    const HEART_TIME = 20;


    const STATUS_CLOSED = 10;
    const STATUS_CONNECTION = 11;
    public int $_status = 0;


    public function resetHeartTime()
    {
        $this->_heartTime = time();
    }

    public function checkHeartTime()
    {
        $now = time();

        if ($now - $this->_heartTime >= self::HEART_TIME) {

            fprintf(STDOUT, "已超过心跳时间:%d\n", $now - $this->_heartTime);
            return true;
        }

        return false;
    }

    public function isConnected()
    {
        return $this->_status == static::STATUS_CONNECTION && is_resource($this->_sockfd);
    }

    public function __construct($_sockfd, $_clientIp, $_server)
    {
        $this->_sockfd = $_sockfd;

        stream_set_blocking($this->_sockfd, 0);

        // 设置为0快速返回 读写的时候
        stream_set_write_buffer($this->_sockfd, 0);
        stream_set_read_buffer($this->_sockfd, 0);

        $this->_clientIp = $_clientIp;
        $this->_server = $_server;

        $this->_heartTime = time();

        $this->_status = self::STATUS_CONNECTION;

//        Server::$_eventLoop
        $this->_server::$_eventLoop->add($_sockfd, Event::EV_READ, [$this, 'recv4socket']);
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

                $this->resetHeartTime();

                $message = $this->_server->_protocol->decode($oneMsg);

                $this->_server->runEventCallBack('receive', [$message, $this]);
            }

        } else {
            $server->runEventCallBack("receive", [$this->_recvBuffer, $this]);

            $this->_recvBuffer = "";
            $this->_recvLen = 0;
            $this->_recvBufferFull = 0;
            $this->_server->onMsg();
            $this->resetHeartTime();

        }
    }

    public function Close()
    {
        $this->_server::$_eventLoop->del($this->_sockfd, Event::EV_READ);
        $this->_server::$_eventLoop->del($this->_sockfd, Event::EV_WRITE);

        if (is_resource($this->_sockfd)) {
            fclose($this->_sockfd);
        }

        /**
         * @var Server $server
         */
        $server = $this->_server;
        $server->runEventCallBack('close', [$this]);

        $server->removeClient($this->_sockfd);

        $this->_sendLen = 0;
        $this->_sendBuffer = '';
        $this->_sendBufferFull = 0;

        $this->_status = self::STATUS_CLOSED;
        $this->_sockfd = null;
    }

    public function send($data)
    {
        if ($this->isConnected() == false) {
            $this->Close();
            return false;
        }

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

                $server->runEventCallBack('receiveBufferFull', [$this]);
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

            $this->_recvBufferFull--;

            // 必须是可写事件在监听,不要没事乱监听或者上来就开始监听!,  比如上面 fwrite 就触发可写事件了,.
            $this->_server::$_eventLoop->add($this->_sockfd, Event::EV_WRITE, [$this, 'write2Socket']);

        } else {
            // 对端关了
            $this->Close();
        }
    }


    public function needWrite()
    {
        return $this->_sendLen > 0;
    }

    public function write2Socket()
    {
        if ($this->needWrite() && $this->isConnected()) {

            $len = fwrite($this->_sockfd, $this->_sendBuffer, $this->_sendLen);

            if ($len == $this->_sendLen) {

                $this->_sendBuffer = '';
                $this->_sendLen = 0;

                $this->_server::$_eventLoop->del($this->_sockfd, Event::EV_WRITE);

                return true;
            } elseif ($len > 0) {
                $this->_sendBuffer = substr($this->_sendBuffer, $len);
                $this->_sendLen -= $len;

            } else {
                if (!is_resource($this->_sockfd) || feof($this->_sockfd)) {
                    $this->Close();
                }
            }
        }
    }
}