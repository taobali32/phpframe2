<?php

namespace Jtar;
class Client
{
    public $_mainSocket;
    public $_events = [];
    public $_readBufferSize = 102400;

    //  接收缓冲区
    public $_recvBuffer = '';
    private int $_recvLen;

    public function __construct($local_socket)
    {
        $this->_mainSocket = stream_socket_client($local_socket, $errno, $errstr);

        if (is_resource($this->_mainSocket)) {
            $this->runEventCallBack('connect', [$this]);

        } else {
            $this->runEventCallBack('error', [$this, $errno, $errstr]);
            exit(0);
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
    }

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
            
        }
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
}