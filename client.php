<?php

use Jtar\Client;

require_once 'vendor/autoload.php';

$clients = [];
$startTime = time();

for ($i = 0; $i < 5; $i++) {
    $client = new Client("tcp://0.0.0.0:9501");

    $client->on('connect', function (Client $client) {

        fprintf(STDOUT, "socket<%d> connect success!\r\n", (int)$client->sockfd());
    });

    $client->on("receive", function (Client $client, $msg) {
//        $client->write2Socket('client');
//        $client->send('11');

        // 我就碰的整理,注释了
//        fprintf(STDOUT, "接收到<%d>服务端的数据:%s\r\n", (int)$client->_mainSocket, $msg);
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

while (1) {

    $now = time();
    $diff = $now - $startTime;

    $startTime = $now;

    if ($diff >= 1) {
        $sendNum = 0;
        $sendMsgNum = 0;

        foreach ($clients as $client) {
            $sendNum += $client->_sendNum;
            $sendMsgNum += $client->_sendMsgNum * 5;
        }
        fprintf(STDOUT, " sendNum:%d, _sendMsgNum:%d\r\n", $sendNum, $sendMsgNum);


        foreach ($clients as $client) {
            $client->_sendNum = 0;
            $client->_sendMsgNum = 0;
        }
    }

    for ($i = 0; $i < 5; $i++) {

        /**
         * @var Client
         */
        $client = $clients[$i];

        $client->send('11');
        if (!$client->loop()) {
            break;
        }
    }
}
