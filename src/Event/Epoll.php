<?php

namespace Jtar\Event;

class Epoll implements Event
{
    public $_eventBase;


    public $_allEvents = [];

    public $_signalEvents = [];

    public $_timers = [];

    public static $_timeId = 0;

    public function __construct()
    {
        $this->_eventBase = new \EventBase();
    }

    public function add($fd, $flag, $func, $arg = [])
    {
        switch ($flag) {
            case self::EV_READ:

                // fd必须设置为非阻塞方式,因为epoll内部运行就是非阻塞方式把文件描述符添加到内核事件表.
                $event = new \Event($this->_eventBase, $fd, \Event::READ | \Event::PERSIST, $func, $arg);

                if (!$event || !$event->add()) {
                    return false;
                }

                $this->_allEvents[(int)$fd][self::EV_READ] = $event;

                return true;

            case self::EV_WRITE:
                // fd必须设置为非阻塞方式,因为epoll内部运行就是非阻塞方式把文件描述符添加到内核事件表.
                $event = new \Event($this->_eventBase, $fd, \Event::WRITE | \Event::PERSIST, $func, $arg);

                if (!$event || !$event->add()) {
                    return false;
                }
                $this->_allEvents[(int)$fd][self::EV_WRITE] = $event;

                return true;

            case self::EV_SIGNAL:
                $event = new \Event($this->_eventBase, $fd, \Event::SIGNAL, $func, $arg);

                if (!$event || !$event->add()) {
                    return false;
                }

                $this->_signalEvents[(int)$fd] = $event;

                return true;

            case self::EV_TIMER:
            case self::EV_TIMER_ONCE:

                $timerId = static::$_timeId;
                $param = [$func, $flag, $timerId, $arg];

                $event = new \Event($this->_eventBase, -1, \Event::TIMEOUT | \Event::PERSIST, [$this, 'timerCallBack'], $param);

                if (!$event || !$event->add($fd)) {
                    return false;
                }

                $this->_timers[$timerId][$flag] = $event;

                ++static::$_timeId;
                return $timerId;
        }
    }

    public function timerCallBack($fd, $waht, $arg)
    {
        $func = $arg[0];

        $flag = $arg[1];

        $timerId = $arg[2];

        $userArg = $arg[3];

        if ($flag == self::EV_TIMER_ONCE) {
            $event = $this->_timers[$timerId][$flag];

            $event->del();

            unset($this->_timers[$timerId][$flag]);
        }

        call_user_func_array($func, [$timerId, $userArg]);
    }

    public function del($fd, $flag)
    {
        switch ($flag) {
            case self::EV_READ:

                if (isset($this->_allEvents[(int)$fd][self::EV_READ])) {
                    $event = $this->_allEvents[(int)$fd][self::EV_READ];

                    $event->del();

                    unset($this->_allEvents[(int)$fd][self::EV_READ]);

                }

                if (empty($this->_allEvents[(int)$fd])) {
                    unset($this->_allEvents[(int)$fd]);
                }

                return true;

            case self::EV_WRITE:
                if (isset($this->_allEvents[(int)$fd][self::EV_WRITE])) {
                    $event = $this->_allEvents[(int)$fd][self::EV_WRITE];

                    $event->del();

                    unset($this->_allEvents[(int)$fd][self::EV_WRITE]);
                }

                if (empty($this->_allEvents[(int)$fd])) {
                    unset($this->_allEvents[(int)$fd]);
                }

                return true;

            case self::EV_SIGNAL:

                return true;

            case self::EV_TIMER:
            case self::EV_TIMER_ONCE:
                if (isset($this->_timers[$fd][$flag])) {
//                    $event = $this->_timers[$fd][$flag];

//                    $event->del();

                    unset($this->_timers[$fd][$flag]);
                }

                break;
        }
    }

    public function loop()
    {
        // 内部实现是while
        $this->_eventBase->dispatch();
    }

    public function clearTimer()
    {
    }

    public function clearSignalEvents()
    {
    }
}