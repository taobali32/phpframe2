<?php

ini_set('memory_limit', "2048M");

require_once "vendor/autoload.php";


// tcp connect/receive/close
// udp packet / close
// stream/text
// http request
// ws open/message/close
// mqtt connect/subscribe/publish/close/unsubscribe
//$server = new \Jtar\Server("tcp://0.0.0.0:9501");
$server = new \Jtar\Server("tcp://0.0.0.0:9501");

//$server = new \Jtar\Server("tcp://0.0.0.0:9501");

$server->setting(
    [
        'workerNum' => 2,
        "daemon" => false,
        'taskNum' => 1
    ]
);

$server->on("connect", function (\Jtar\Server $server, \Jtar\TcpConnection $connection) {
    fprintf(STDOUT, "有客户端连接了\n");
});

$server->on("masterStart", function (\Jtar\Server $server) {
    fprintf(STDOUT, "master server start\r\n");
});

$server->on("workerReload", function (\Jtar\Server $server) {
    fprintf(STDOUT, "worker <pid:%d> reload\r\n", posix_getpid());
});

$server->on("masterShutdown", function (\Jtar\Server $server) {
    fprintf(STDOUT, "master server shutdown\r\n");
});

$server->on("workerStart", function (\Jtar\Server $server) {
    fprintf(STDOUT, "worker <pid:%d> start\r\n", posix_getpid());

});

$server->on("workerStop", function (\Jtar\Server $server) {
    fprintf(STDOUT, "worker <pid:%d> stop\r\n", posix_getpid());
});

$server->on("receive", function (\Jtar\Server $server, $msg, \Jtar\TcpConnection $connection) {
//    fprintf(STDOUT, "接收到<%d>客户端的数据:%s\r\n", (int)$connection->_sockfd, $msg);

//    var_dump($msg);
    $connection->send($msg);
});

$server->on("close", function (\Jtar\Server $server, \Jtar\TcpConnection $connection) {
    fprintf(STDOUT, "客户端<%d>已经关闭\r\n", (int)$connection->_sockfd);
});

// 缓冲区满了
$server->on("receiveBufferFull", function (\Jtar\Server $server, \Jtar\TcpConnection $connection) {
    fprintf(STDOUT, "接收缓冲区已满\r\n");
});

$server->Start();

