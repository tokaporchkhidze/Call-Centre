<?php

namespace App\Http\Controllers\Bonus;

use App\AsteriskStatistics\QueueLog;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bonus\GetBonusStats;
use App\Logging\Logger;
use App\Operator;
use App\SipToOperatorHistory;
use Illuminate\Http\Request;

class BonusController extends Controller {

    /**
     * @var Logger
     */
    private $logger;

    public function getBonusStats(GetBonusStats $request) {
        $inputArr = $request->input();
        $responseArr = [
            'status' => true,
            'message' => "",
            'data' => array(),
        ];
        $operatorHistory = SipToOperatorHistory::getSipsForOperatorByDate([$inputArr['pid']], $inputArr['start'], $inputArr['end']);
        if(empty($operatorHistory)) {
            $responseArr['status'] = false;
            $responseArr['message'] = "მითითებული ოპერატორისთვის სიპი ვერ მოიძებნა";
            return $responseArr;
        }
        $sips = $operatorHistory[$inputArr['pid']];
        foreach($sips as $sip) {
            if($inputArr['start'] >= $sip['paired_at']) {
                $startDate = $inputArr['start'];
            } else {
                $startDate = $sip['paired_at'];
            }
            if(!isset($sip['removed_at'])) {
                $endDate = $inputArr['end'];
            } else if($inputArr['end'] >= $sip['removed_at']) {
                $endDate = $sip['removed_at'];
            } else {
                $endDate = $inputArr['end'];
            }
            $stats = QueueLog::getStatsForBonus($sip['sip'], $startDate, $endDate);
            if(empty($stats)) continue;
            $responseArr['data'][] = $stats;
        }
        if(empty($responseArr['data'])) {
            $responseArr['status']  = false;
            $responseArr['message'] = 'ჩანაწერი ვერ მოიძებნა';
        }
        return $responseArr;
    }


}
