<?php

namespace App\Http\Controllers;

use App\Logging\Logger;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function __destruct() {
        $logger = Logger::instance();
        $logger->commitLog("commit from base controller", "INFO");
    }

}
