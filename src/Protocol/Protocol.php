<?php

namespace Jtar\Protocol;

interface Protocol
{
    public function Len($data);

    //  打包
    public function encode($data = '');

    public function decode($data = '');

    public function msgLen($data = '');
}