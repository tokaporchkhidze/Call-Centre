<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Logging\Logger;


class BlackListController extends Controller {

    private $logger;

    public function __construct() {
        $this->logger = Logger::instance();
    }

    public function testFunc(){
        logger()->info("test");
    }
}

