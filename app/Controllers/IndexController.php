<?php

namespace App\Controllers;

class IndexController extends BaseController
{
    public function index()
    {
        print_r($this->_request->_get);
        print_r($this->_request->_post);

        return json_encode(['a'=>'b']);
    }
}