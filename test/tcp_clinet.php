<?php

$sockfd = socket_create(AF_INET, SOCK_STREAM, 0);

if (socket_connect($sockfd, "127.0.0.1", 9501)) {
    echo "connect success\n";

    socket_write($sockfd, "client", 5);

    echo "recv from server:" . socket_read($sockfd, 1024);

} else {
    echo "connect failed\n";
}