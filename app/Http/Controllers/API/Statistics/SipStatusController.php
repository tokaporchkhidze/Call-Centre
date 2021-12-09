<?php

namespace App\Http\Controllers\API\Statistics;

use App\AsteriskStatistics\SipStatusLog;
use App\Sip;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class SipStatusController extends Controller {

    public function getSipLogins(Request $request) {
        $inputArr = $request->input();
        if(!isset($inputArr['sipArr']) or empty($inputArr['sipArr'][0]) or $inputArr['sipArr'][0] == 'null') {
            $sips = Sip::getSipsWithTemplatesAndOperatorsAndQueues();
            $inputArr['sipArr'] = array_map(function($val) {
                return $val['sip'];
            }, $sips);
        }
        $sipStatuses = SipStatusLog::getSipLogins($inputArr['sipArr'], $inputArr['startDate'], $inputArr['endDate']);
        $lastStatus = null;
        $tmpArr = array();
        $resultSet = array();
        $sip = 0;
        foreach($sipStatuses as $sip => $statuses) {
            foreach($statuses as $row) {
                if (empty($tmpArr)) {
                    if ($row['status'] == config('asterisk.REGISTER')) {
                        $tmpArr['register'] = $row['time'];
                    } else {
                        $tmpArr['unregister'] = $row['time'];
                    }
                    $tmpArr['sip'] = $sip;
                } else {
                    if ($row['status'] == config('asterisk.REGISTER')) {
                        $tmpArr['register'] = $row['time'];
                    } else {
                        $tmpArr['unregister'] = $row['time'];
                    }
                    if($sip != $tmpArr['sip']) {
                        $resultSet[$tmpArr['sip']][] = $tmpArr;
                    } else {
                        $resultSet[$sip][] = $tmpArr;
                    }
                    $tmpArr = array();
                }
            }
            if(!empty($tmpArr)) {
                $resultSet[$sip][] = $tmpArr;
                $tmpArr = array();
            }
        }
        if(!empty($tmpArr)) {
            $resultSet[$sip][] = $tmpArr;
        }
        return $resultSet;
    }

    public function getSipLastStatus(Request $request) {
        $inputArr = $request->input();
        return SipStatusLog::getSipLastStatus($inputArr['sipNumber']);
    }

}
