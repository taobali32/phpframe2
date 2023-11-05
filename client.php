<?php

require_once 'vendor/autoload.php';

$client = new \Jtar\Client("tcp://0.0.0.0:9501");

$client->on('connect', function (\Jtar\Client $client) {

    var_dump("to server hello");
    $client->write2Socket('hello');
});

$client->on("receive", function (\Jtar\Client $client, $msg) {
    $client->write2Socket('client');

//    fprintf(STDOUT, "接收到<%d>服务端的数据:%s\r\n", (int)$client->_mainSocket, $msg);
});


$client->on('close', function (\Jtar\Client $client) {
    fprintf(STDOUT, "服务器断开我的连接了\n");
});


$client->on('error', function (\Jtar\Client $client, $errno, $errstr) {
    fprintf(STDOUT, "errno:%d,errstr:%s\n", $errno, $errstr);
});


$client->Start();

