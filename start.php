<?php

require_once "vendor/autoload.php";


$server = new \Jtar\Server("tcp://0.0.0.0:9501");

$server->Listen();

$server->eventLoop();

