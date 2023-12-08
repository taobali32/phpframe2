<?php

namespace Jtar\Event;

interface Event
{
    const EV_READ = 10;

    const EV_WRITE = 11;

    // 信号
    const EV_SIGNAL = 12;

    // 定时器无限
    const EV_TIMER = 13;

    //  定时器1次
    const EV_TIMER_ONCE = 14;


    // 文件描述符,中断信号,定时器


    public function add($fd, $flag, $func, $arg = []);

    public function del($fd, $flag);

    public function loop();


    public function clearTimer();

    public function clearSignalEvents();
}