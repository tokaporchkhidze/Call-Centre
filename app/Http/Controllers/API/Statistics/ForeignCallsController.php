<?php

namespace App\Http\Controllers\API\Statistics;

use App\AsteriskStatistics\CdrLog;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ForeignCallsController extends Controller {

    public function getForeignCallQueues() {
        return config('asterisk.FOREIGN_QUEUES');
    }

    public function getForeignCalls(Request $request) {
        $inputArr = $request->input();
        return CdrLog::getForeignLangCalls($inputArr['startDate'], $inputArr['endDate'], $inputArr['queues']);
    }

    public function getForeignCallsDetailed(Request $request) {
        $inputArr = $request->input();
        if(strtolower($inputArr['isDirect']) == "true") {
            $isDirect = true;
        } else {
            $isDirect = false;
        }
        return CdrLog::getForeignLangCallsDetails($inputArr['startDate'], $inputArr['endDate'], $inputArr['queues'], $isDirect);
    }

}
