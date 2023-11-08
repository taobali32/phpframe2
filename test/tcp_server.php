<?php

echo "pid=" . getmypid() . "\n";


$sockfd = socket_create(AF_INET, SOCK_STREAM, 0);

socket_bind($sockfd, '0.0.0.0', 9501);

socket_listen($sockfd);

// 接收客户端连接cl

$connfd = socket_accept($sockfd);

echo socket_read($connfd, 1024);

$data = "HTTP/1.1 200 OK\r\nContent-Length: 2\r\n\r\nok";

var_dump(socket_send($connfd, $data, strlen($data), 0));

socket_close($sockfd);
socket_close($connfd);
