<?php

namespace Jtar;

use Jtar\Protocol\Stream;

class Client
{
    public $_mainSocket;
    public $_events = [];
    public $_readBufferSize = 102400;

    //  接收缓冲区
    public $_recvBuffer = '';
    private int $_recvLen = 0;

    public $_protocol;

    public $_local_socket;

    public function __construct($local_socket)
    {
        $this->_protocol = new Stream();

        $this->_local_socket = $local_socket;
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
    }

    //
    public function recv4socket()
    {
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

    public function handleMessage()
    {
        while ($this->_protocol->Len($this->_recvBuffer)) {
            $msgLen = $this->_protocol->msgLen($this->_recvBuffer);

            $oneMsg = substr($this->_recvBuffer, 0, $msgLen);

            $this->_recvBuffer = substr($this->_recvBuffer, $msgLen);

            $message = $this->_protocol->decode($oneMsg);

            $this->runEventCallBack('receive', [$message]);
        }
    }

    public function write2Socket($data)
    {
        $bin = $this->_protocol->encode($data);

        fwrite($this->_mainSocket, $bin[1], $bin[0]);
    }

    public function eventLoop()
    {
        while (1) {
            $readFds = [$this->_mainSocket];
            $writeFds = [$this->_mainSocket];
            $exceptFds = [$this->_mainSocket];

            $ret = stream_select($readFds, $writeFds, $exceptFds, NULL);

            if ($ret <= 0 || $ret === FALSE) {
                break;
            }

            if ($readFds) {
                $this->recv4socket();
            }

            if ($writeFds) {

            }

        }
    }

    public function Start()
    {
        $this->_mainSocket = stream_socket_client($this->_local_socket, $errno, $errstr);


        if (is_resource($this->_mainSocket)) {

            $this->runEventCallBack('connect', [$this]);

            $this->eventLoop();

        } else {
            $this->runEventCallBack('error', [$this, $errno, $errstr]);
            exit(0);
        }
    }
}