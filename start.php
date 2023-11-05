<?php

require_once "vendor/autoload.php";


// tcp connect/receive/close
// udp packet / close
// stream/text
// http request
// ws open/message/close
// mqtt connect/subscribe/publish/close/unsubscribe
$server = new \Jtar\Server("tcp://0.0.0.0:9501");

$server->on("connect", function (\Jtar\Server $server, \Jtar\TcpConnection $connection) {
    fprintf(STDOUT, "有客户端连接了\n");
});

$server->on("receive", function (\Jtar\Server $server, $msg, \Jtar\TcpConnection $connection) {
    fprintf(STDOUT, "接收到<%d>客户端的数据:%s\r\n", (int)$connection->_sockfd, $msg);

    $connection->send('i im server');
});

$server->on("close", function (\Jtar\Server $server, \Jtar\TcpConnection $connection) {
    fprintf(STDOUT, "客户端<%d>已经关闭\r\n", (int)$connection->_sockfd);
});

$server->Start();

