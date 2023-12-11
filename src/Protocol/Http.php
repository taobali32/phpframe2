<?php

namespace Jtar\Protocol;

class Http implements Protocol
{
    public $_headerLen=0;
    public $_bodyLen=0;

    public $_post;
    public $_get;
    public function Len($data)
    {
        // 如果是post 最后跟的数据后面是4个这玩意的，前面的都是\r\n
        if(strpos($data,"\r\n\r\n")){

            //  见static/01.png
            $this->_headerLen = strpos($data,"\r\n\r\n");
            $this->_headerLen+=4;

            // get请求没有 Content-Length

            $bodyLen = 0;
            if (preg_match("/\r\nContent-Length: ?(\d+)/i",$data,$matches)){
                $bodyLen = $matches[1];
            }
            $this->_bodyLen = $bodyLen;

            $totalLen = $this->_headerLen+$this->_bodyLen;

            if(strlen($data)>=$totalLen){

                return true;
            }
            return false;
        }
        return false;
    }

    public function encode($data = '')
    {
        return [strlen($data),$data];
    }

    public function parseHeader($data)
    {
//        var_dump($data);

//string(187) "GET / HTTP/1.1
//User-Agent: PostmanRuntime-ApipostRuntime/1.1.0
//Cache-Control: no-cache
//Accept: */*
//Accept-Encoding: gzip, deflate, br
//Connection: keep-alive
//Host: 182.43.11.219:8227"
//        var_dump($data);

        //php nginx apache
        //
        $_REQUEST = $_GET = [];

        $temp = explode("\r\n",$data);
        $startLine = $temp[0];

//        var_dump($startLine);  GET / HTTP/1.1
        list($method,$uri,$schema) = explode(" ",$startLine);
        $_REQUEST['uri'] = parse_url($uri)['path'];

        // 得到url中的?a=1&b=2这些.  结果:a=11&b=22
        $query = parse_url($uri,PHP_URL_QUERY);

        //  将上面a=11&b=22这些放在 $_GET里面，数组键值对形式
        if ($query){
            parse_str($query,$_GET);

            /**
             * array(2) {
             * ["a"]=>
             * string(2) "11"
             * ["b"]=>
             * string(2) "22"
             * }
             */
        }

        $_REQUEST['method'] = $method;
        $_REQUEST['schema'] = $schema;
        //$_GET $_POST

        unset($temp[0]);

        foreach ($temp as $item){

            $kv = explode(": ",$item,2);
            $key = str_replace("-","_",$kv[0]);
            $_REQUEST[$key] = rtrim($kv[1]);
        }
        if (isset($_REQUEST["Host"])){
            $ipAddr = explode(":",$_REQUEST["Host"],2);
            $_REQUEST['ip'] =$ipAddr[0];
            $_REQUEST['port'] =$ipAddr[1];
        }

//        var_dump($_REQUEST);

//        array(13) {
//        ["uri"]=>
//  string(6) "/a.php"
//        ["method"]=>
//  string(4) "POST"
//        ["schema"]=>
//  string(8) "HTTP/1.1"
//        ["User_Agent"]=>
//  string(35) "PostmanRuntime-ApipostRuntime/1.1.0"
//        ["Cache_Control"]=>
//  string(8) "no-cache"
//        ["Accept"]=>
//  string(3) "*/*"
//        ["Accept_Encoding"]=>
//  string(17) "gzip, deflate, br"
//        ["Connection"]=>
//  string(10) "keep-alive"
//        ["Host"]=>
//  string(18) "182.43.11.219:8227"
//        ["Content_Type"]=>
//  string(80) "multipart/form-data; boundary=--------------------------924305239084203186129316"
//        ["Content_Length"]=>
//  string(3) "161"
//        ["ip"]=>
//  string(13) "182.43.11.219"
//        ["port"]=>
//  string(4) "8227"
//}
    }

    public function parseFormData($boundary,$data)
    {
        $data = substr($data,0,-4);
        $formData = explode($boundary,$data);

        $_FILES = [];
        $key = 0;

        foreach ($formData as $field){

            if ($field){

                $kv = explode("\r\n\r\n",$field,2);
                $value = rtrim($kv[1],"\r\n");
                if (preg_match('/name="(.*)"; filename="(.*)"/',$kv[0],$matches)){

                    $_FILES[$key]['name'] = $matches[1];
                    $_FILES[$key]['file_name'] = $matches[2];
                    //$_FILES[$key]['file_value'] = $value;
                    file_put_contents("www/".$matches[2],$value);

                    $_FILES[$key]['file_size'] = strlen($value);
                    $fileType = explode("\r\n",$kv[0],2);
                    $fileType = explode(": ",$fileType[1]);
                    $_FILES[$key]['file_type'] = $fileType[2];
                    ++$key;
                }
                else if (preg_match('/name="(.*)"/',$kv[0],$matches)){
                    $_POST[$matches[1]] = $value;
                }
            }
        }
    }

    public function parseBody($data)
    {
        $_POST = [];

        //  string(80) "multipart/form-data; boundary=--------------------------166598785310240819994369"
        $content_type = $_REQUEST['Content_Type'] ?? $_REQUEST['content_type'] ;


        $boundary= "";//边界 \S 匹配非空白字符
        if (preg_match("/boundary=(\S+)/i",$content_type,$matches)){
            $boundary = "--".$matches[1];
            $content_type = "multipart/form-data";
        }

        switch ($content_type){

            // 表单
            case 'multipart/form-data':
                $this->parseFormData($boundary,$data);
                break;
            case 'application/x-www-form-urlencoded':
                parse_str($data,$_POST);

                break;
            case 'application/json':

                $_POST = json_decode($data,true);
                break;
        }
    }

    public function decode($data = '')
    {
        $header = substr($data,0,$this->_headerLen-4);
        $body = substr($data,$this->_headerLen);
        $this->parseHeader($header);
        if ($body){
            $this->parseBody($body);
        }

        return $body;
    }

    public function msgLen($data = '')
    {
        return $this->_bodyLen+$this->_headerLen;
    }
}