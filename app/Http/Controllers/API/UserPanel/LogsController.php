<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 2/1/2019
 * Time: 6:07 PM
 */

namespace App\Http\Controllers\API\UserPanel;


use App\Http\Controllers\Controller;
use App\Http\Requests\GetActivityLogs;
use App\Logging\Logger;
use Illuminate\Http\Request;

class LogsController extends Controller {

    private $logHandler;

    public function __construct() {
        $this->logHandler = Logger::instance();
    }


    public function getActivityLogs(GetActivityLogs $request) {
        $inputArr = $request->input();
        $inputArr['offset'] = $inputArr['offset'] ?? config('logging.DEFAULT_OFFSET');
        $inputArr['limit'] = $inputArr['limit'] ?? config('logging.DEFAULT_LIMIT');
        $resultSet = json_decode(json_encode($this->logHandler->getLogsByGivenFilters($inputArr)), true);
        return $resultSet;
    }

    public function getLogActionMapping() {
        $arr = config('logging.mongo_mapping');
        return $arr;
    }

}