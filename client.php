<?php

require_once 'vendor/autoload.php';

$client = new \Jtar\Client("tcp:0.0.0.0:9501");

$client->on('connect', function (\Jtar\Client $client) {

});

$client->on("receive", function (\Jtar\Client $client, $msg) {
    fprintf(STDOUT, "接收到<%d>服务端的数据:%s\r\n", $msg);
});

$client->on('close', function (\Jtar\Client $client) {
    fprintf(STDOUT, "服务器断开我的连接了\n");
});


$client->on('error', function (\Jtar\Client $client, $errno, $errstr) {
    fprintf(STDOUT, "errno:%d,errstr:%s\n", $errno, $errstr);
});




