<?php

namespace App\Controllers;

use Jtar\Request;
use Jtar\Response;

class BaseController
{
    public $_response;
    public $_request;

    public function __construct(Request $request,Response $response)
    {
        $this->_request = $request;
        $this->_response = $response;

    }
}