<?php

namespace Jtar\Event;

class Select implements Event
{

    public $_allEvents = [];

    public $_signalEvents = [];

    public $_timers = [];

    public $_readFds = [];
    public $_writeFds = [];
    public $_exptFds = [];

    public $_timeout = 0;

    public function __construct()
    {
    }

    public function add($fd, $flag, $func, $arg = [])
    {
        switch ($flag) {
            case self::EV_READ:
                $fdKey = (int)$fd;

                $this->_readFds[$fdKey] = $fd;
                $this->_allEvents[$fdKey][self::EV_READ] = [$func, [$fd, $arg]];

                return true;

            case self::EV_WRITE:
                $fdKey = (int)$fd;

                $this->_writeFds[$fdKey] = $fd;
                $this->_allEvents[$fdKey][self::EV_WRITE] = [$func, [$fd, $arg]];

                return true;
                break;

            case self::EV_SIGNAL:

                return true;
        }
    }

    public function del($fd, $flag)
    {
        switch ($flag) {
            case self::EV_READ:
                $fdKey = (int)$fd;

                unset($this->_allEvents[$fdKey][self::EV_READ]);
                unset($this->_readFds[$fdKey]);

                if (empty($this->_allEvents[$fdKey])) {
                    unset($this->_allEvents[$fdKey]);
                }

                return true;

            case self::EV_WRITE:
                $fdKey = (int)$fd;

                unset($this->_allEvents[$fdKey][self::EV_WRITE]);
                unset($this->_writeFds[$fdKey]);

                if (empty($this->_allEvents[$fdKey])) {
                    unset($this->_allEvents[$fdKey]);
                }
                return true;

            case self::EV_SIGNAL:

                return true;
        }
    }

    public function loop1()
    {
        // 内部实现是while
        $readFds = $this->_readFds;

        $writeFds = $this->_writeFds;
        $exceptFds = $this->_exptFds;

        // tv_sec设置为0 则很快就返回了, 不需要等待, 导致该函数一直执行占用cpu..
        // 给null的话有客户端连接才执行

        $ret = stream_select($readFds, $writeFds, $exceptFds, 0, $this->_timeout);

        if ($ret === FALSE) {
            return false;
        }

        if ($readFds) {
            foreach ($readFds as $fd) {
                $fdKey = (int)$fd;

                if (isset($this->_allEvents[$fdKey][self::EV_READ])) {
                    $cb = $this->_allEvents[$fdKey][self::EV_READ];

                    call_user_func_array($cb[0], $cb[1]);
                }
            }
        }

        if ($writeFds) {
            foreach ($writeFds as $fd) {
                $fdKey = (int)$fd;

                if (isset($this->_allEvents[$fdKey][self::EV_WRITE])) {
                    $cb = $this->_allEvents[$fdKey][self::EV_WRITE];

                    call_user_func_array($cb[0], $cb[1]);
                }
            }
        }

        return true;
    }

    public function loop()
    {
        // 内部实现是while
        while (1) {
            $readFds = $this->_readFds;

            $writeFds = $this->_writeFds;
            $exceptFds = $this->_exptFds;

            // tv_sec设置为0 则很快就返回了, 不需要等待, 导致该函数一直执行占用cpu..
            // 给null的话有客户端连接才执行

            $ret = stream_select($readFds, $writeFds, $exceptFds, 0, $this->_timeout);

            if ($ret === FALSE) {
                break;
            }

            if ($readFds) {
                foreach ($readFds as $fd) {
                    $fdKey = (int)$fd;

                    if (isset($this->_allEvents[$fdKey][self::EV_READ])) {
                        $cb = $this->_allEvents[$fdKey][self::EV_READ];

                        call_user_func_array($cb[0], $cb[1]);
                    }
                }
            }

            if ($writeFds) {
                foreach ($writeFds as $fd) {
                    $fdKey = (int)$fd;

                    if (isset($this->_allEvents[$fdKey][self::EV_WRITE])) {
                        $cb = $this->_allEvents[$fdKey][self::EV_WRITE];

                        call_user_func_array($cb[0], $cb[1]);
                    }
                }
            }
        }
    }

    public function clearTimer()
    {
    }

    public function clearSignalEvents()
    {
    }
}