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
//$server = new \Jtar\Server("tcp://0.0.0.0:9501");
$server = new \Jtar\Server("http://0.0.0.0:9501");


//$server = new \Jtar\Server("tcp://0.0.0.0:9501");

$server->setting(
    [
        'workerNum' => 1,
        "daemon" => false,
        'taskNum' => 1,
        "task"  =>  [
            "unix_socket_server_file" => "/home/ubuntu/study/php/wlw/sock/te_unix_socket_server",
            "unix_socket_client_file" => "/home/ubuntu/study/php/wlw/sock/te_unix_socket_client",
        ]
    ]
);

$server->on("request", function (\Jtar\Server $server, \Jtar\Request $request,\Jtar\Response $response) {
    global $routes;
    global $dispatcher;
//    fprintf(STDOUT, "有客户端连接了\n");

    //  响应内容
//    $response->write("hello,world");

    //  响应json
//    $response->header('Content-Type','application/json');
//    $response->write(json_encode(['data' => 'hello world!']));


    //  响应图片
//    $file = "www/3411700127133_.pic.jpg";
//    $response->sendFile($file);

    if (preg_match("/.html|.jpg|.png|.gif|.css|.js|.ico|.woff|.woff2|.ttf|.eot|.svg|.mp4|.mp3/",$request->_request['uri'])){
        $file = "www/".$request->_request['uri'];
        $response->sendFile($file);

        return true;
    }

    /**
     * @var $dispatcher \App\Controllers\ControllerDispatcher
     */
    $dispatcher->callAction($routes,$request,$response);
//    $response->header('Content-Type','application/json');
//    $response->write($result);


});

$server->on("connect", function (\Jtar\Server $server, \Jtar\TcpConnection $connection) {
//    fprintf(STDOUT, "有客户端连接了\n");

//    var_dump($_POST);
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

    global $routes;
    global $dispatcher;
    $routes = require_once "app/routers/api.php";

    $dispatcher = new \App\Controllers\ControllerDispatcher();

    fprintf(STDOUT, "worker <pid:%d> start\r\n", posix_getpid());
});

$server->on("workerStop", function (\Jtar\Server $server) {
    fprintf(STDOUT, "worker <pid:%d> stop\r\n", posix_getpid());
});

$server->on("receive", function (\Jtar\Server $server, $msg, \Jtar\TcpConnection $connection) {
//    fprintf(STDOUT, "接收到<%d>客户端的数据:%s\r\n", (int)$connection->_sockfd, $msg);

//    var_dump($msg);

    $server->task(function ()use($server){

        sleep(1);

//        $server->echoLog("异步任务我执行完，时间到了\r\n");
//        echo time()."\r\n";

    });//耗时任务可以投递到任务进程来做

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

