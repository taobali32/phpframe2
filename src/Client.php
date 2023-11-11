<?php

namespace Jtar;

use Jtar\Event\Epoll;
use Jtar\Event\Event;
use Jtar\Event\Select;
use Jtar\Protocol\Stream;

class Client
{
    public static $_eventLoop;
    public $_mainSocket;
    public $_events = [];
    public $_readBufferSize = 102400;

    //  接收缓冲区
    public $_recvBuffer = '';
    private int $_recvLen = 0;

    public $_protocol;

    public $_local_socket;


    public $_sendLen = 0;
    public $_sendBuffer = '';
    public $_sendBufferSize = 1024 * 1000;
    public $_sendBufferFull = 0;

    public $_sendNum = 0;
    public $_sendMsgNum = 0;

    const STATUS_CLOSED = 10;
    const STATUS_CONNECTION = 11;
    public int $_status = 0;


    public function __construct($local_socket)
    {
        $this->_protocol = new Stream();

        $this->_local_socket = $local_socket;


        if (DIRECTORY_SEPARATOR == "/") {
            static::$_eventLoop = new Epoll();
        } else {
            static::$_eventLoop = new Select();
        }
    }

    public function sockfd()
    {
        return $this->_mainSocket;
    }

    public function onSendMsg()
    {
        ++$this->_sendNum;
    }


    public function onSendWrite()
    {
        ++$this->_sendMsgNum;
    }


    public function send($data)
    {
        $len = strlen($data);

        if ($this->_sendLen + $len < $this->_sendBufferSize) {

            $bin = $this->_protocol->encode($data);
            $this->_sendBuffer .= $bin[1];
            $this->_sendLen += $bin[0];

            if ($this->_sendLen >= $this->_sendBufferSize) {
                $this->_sendBufferFull++;
            }

            $this->onSendMsg();

        } else {
            $this->runEventCallBack("sendBufferFull", [$this]);
        }

        $writeLen = fwrite($this->_mainSocket, $this->_sendBuffer, $this->_sendLen);

        // 完整发送
        if ($writeLen == $this->_sendLen) {

            $this->_sendBuffer = '';
            $this->_sendLen = 0;
            $this->_recvBufferFull = 0;
            static::$_eventLoop->del($this->_mainSocket, Event::EV_WRITE);

            $this->onSendWrite();

            return true;
        } elseif ($writeLen < $this->_sendLen) {
            // 只发送一半
            $this->_sendBuffer = substr($this->_sendBuffer, $writeLen);
            $this->_sendLen -= $writeLen;

            $this->_recvBufferFull--;

            // 必须是可写事件在监听,不要没事乱监听或者上来就开始监听!,  比如上面 fwrite 就触发可写事件了,.
            static::$_eventLoop->add($this->_mainSocket, Event::EV_WRITE, [$this, 'write2Socket']);

        } else {
            // 对端关了
            $this->onClose();
        }

    }


    public function runEventCallBack($eventName, $args = [])
    {
        if (isset($this->_events[$eventName]) && is_callable($this->_events[$eventName])) {
            $this->_events[$eventName]($this, ...$args);
        }
    }


    public function on($eventName, $eventCall)
    {
        $this->_events[$eventName] = $eventCall;
    }

    public function onClose()
    {
        fclose($this->_mainSocket);
        $this->runEventCallBack('close', [$this]);

        $this->_status = self::STATUS_CLOSED;

        $this->_mainSocket = null;
    }

    //
    public function recv4socket()
    {
        if ($this->isConnected()) {
            $data = fread($this->_mainSocket, $this->_readBufferSize);

            // 1正常接收, 接收到的数据>0
            // 2对端关闭了, 接收到的字节数为0
            // 3 错误,
            if ($data === '' || $data === false) {

                if (feof($this->_mainSocket) || !is_resource($this->_mainSocket)) {
                    $this->onClose();
                    return;
                }
            } else {
                // 接收到的数据放在接收缓冲区
                $this->_recvBuffer .= $data;
                $this->_recvLen += strlen($data);
            }

            if ($this->_recvLen > 0) {
                $this->handleMessage();
            }
        }
    }

    public function needWrite()
    {
        return $this->_sendLen > 0;
    }

    public function handleMessage()
    {
        while ($this->_protocol->Len($this->_recvBuffer)) {
            $msgLen = $this->_protocol->msgLen($this->_recvBuffer);

            $oneMsg = substr($this->_recvBuffer, 0, $msgLen);

            $this->_recvBuffer = substr($this->_recvBuffer, $msgLen);

            $this->_recvLen -= $msgLen;
//            $this->_recvBufferFull--;
            $message = $this->_protocol->decode($oneMsg);

            $this->runEventCallBack('receive', [$message]);
        }
    }


    public function isConnected()
    {
        return $this->_status == static::STATUS_CONNECTION && is_resource($this->_mainSocket);
    }

    public function write2Socket()
    {
        if ($this->needWrite() && $this->isConnected()) {

            $len = fwrite($this->_mainSocket, $this->_sendBuffer, $this->_sendLen);

            $this->onSendWrite();

            if ($len == $this->_sendLen) {

                $this->_sendBuffer = '';
                $this->_sendLen = 0;
                return true;
            } elseif ($len > 0) {
                $this->_sendBuffer = substr($this->_sendBuffer, $len);
                $this->_sendLen -= $len;
//               return false;
            } else {
                if (!is_resource($this->_mainSocket) || feof($this->_mainSocket)) {
                    $this->onClose();
                }
            }
        }

    }

    public function loop()
    {
        return static::$_eventLoop->loop1();
    }

    public function eventLoop()
    {
        if (is_resource($this->_mainSocket)) {
            $readFds = [$this->_mainSocket];
            $writeFds = [$this->_mainSocket];
            $exceptFds = [$this->_mainSocket];

            $ret = stream_select($readFds, $writeFds, $exceptFds, NULL, NULL);

            if ($ret <= 0 || $ret === FALSE) {
                return false;
            }

            if ($readFds) {
                $this->recv4socket();
            }

            if ($writeFds) {
                $this->write2Socket();
            }

            return true;

        } else {
            return false;
        }

    }

    public function Start()
    {
        $this->_mainSocket = stream_socket_client($this->_local_socket, $errno, $errstr);

        if (is_resource($this->_mainSocket)) {

            stream_set_blocking($this->_mainSocket, 0);

            // 设置为0快速返回 读写的时候
            stream_set_write_buffer($this->_mainSocket, 0);
            stream_set_read_buffer($this->_mainSocket, 0);

            $this->runEventCallBack('connect', [$this]);

            $this->_status = static::STATUS_CONNECTION;

            static::$_eventLoop->add($this->_mainSocket, Event::EV_READ, [$this, 'recv4socket']);

        } else {
            $this->runEventCallBack('error', [$this, $errno, $errstr]);
            exit(0);
        }
    }
}