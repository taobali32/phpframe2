<?php

namespace Jtar\Event;

class Epoll implements Event
{
    public $_eventBase;


    public $_allEvents = [];

    public $_signalEvents = [];

    public $_timers = [];

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
                $event = new \Event($this->_eventBase, $fd, Event::EV_SIGNAL, $func, $arg);

                if (!$event || $event->add()) {
                    return false;
                }

                $this->_signalEvents[(int)$fd] = $event;

                return true;
        }
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