<?php

namespace Jtar\Protocol;

class Stream implements Protocol
{
    // 协议设计
    //  4个字节存储数据总长度
    public function Len($data)
    {
        if (strlen($data) < 4) {
            return false;
        }

        $tmp = unpack("NtotalLen", $data);

        if (strlen($data) < $tmp['totalLen']) {
            return false;
        }

        return true;
    }

    //  打包
    public function encode($data = '')
    {
        // 数据包总长度 4 +数据长度２　所以＝６
        $totalLen = strlen($data) + 6;

        // "1" 是数据
        $bin = pack("Nn", $totalLen, "1") . $data;

        return [$totalLen, $bin];
    }

    public function decode($data = '')
    {
        //123456
        $cmd = substr($data, 4, 2);

        $msg = substr($data, 6);

        return $msg;
    }

    // 返回消息的总长度,用来截取消息
    public function msgLen($data = '')
    {
        $tmp = unpack("Nlength", $data);

        return $tmp['length'];
    }
}