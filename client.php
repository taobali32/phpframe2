<?php

use Jtar\Client;

require_once 'vendor/autoload.php';

$clients = [];

for ($i = 0; $i < 5; $i++) {
    $client = new Client("tcp://0.0.0.0:9501");

    $client->on('connect', function (Client $client) {

        fprintf(STDOUT, "socket<%d> connect success!\r\n", (int)$client->sockfd());
    });

    $client->on("receive", function (Client $client, $msg) {
//        $client->write2Socket('client');
//        $client->send('11');

        // 我就碰的整理,注释了
        fprintf(STDOUT, "接收到<%d>服务端的数据:%s\r\n", (int)$client->_mainSocket, $msg);
    });

    $client->on('close', function (Client $client) {
        fprintf(STDOUT, "服务器断开我的连接了\n");
    });


    $client->on('error', function (Client $client, $errno, $errstr) {
        fprintf(STDOUT, "errno:%d,errstr:%s\n", $errno, $errstr);
    });

    $client->Start();

    $clients[] = $client;
}

//
//$pid = pcntl_fork();
//
//if ($pid == 0) {
//    while (1) {
//        for ($i = 0; $i < 5; $i++) {
//            $client = $clients[$i];
//
//            fprintf(STDOUT, "FD:%d,在发送数据\n", (int)$client->sockfd());
//
//            $client->write2Socket('hello_' . (int)$client->sockfd());
//        }
//    }
//
//    exit(0);
//}
//
//
while (1) {
    for ($i = 0; $i < 5; $i++) {

        /**
         * @var Client
         */
        $client = $clients[$i];

        $client->send('11');
        if (!$client->eventLoop()) {
            break;
        }
    }
}
