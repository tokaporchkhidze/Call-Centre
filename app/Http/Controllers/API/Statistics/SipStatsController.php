<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 2/12/2019
 * Time: 2:10 PM
 */

namespace App\Http\Controllers\API\Statistics;


use App\AsteriskHandlers\AsteriskManager;
use App\AsteriskStatistics\CdrLog;
use App\AsteriskStatistics\QueueLog;
use App\AsteriskStatistics\SipStatusLog;
use App\Common\MailHandler;
use App\Http\Controllers\Controller;
use App\Http\Requests\GetStatsByQueue;
use App\Http\Requests\GetStatsBySips;
use App\Queue;
use App\QueueGroup;
use App\Sip;
use App\SipToOperatorHistory;
use Illuminate\Http\Request;

class SipStatsController extends Controller {

    private $dtmfGroups = [
        'geo_news' => [
            'dtmf' => 's_1_1',
            'counter' => 0
        ],
        'geo_2' => [
            'dtmf' => 's_1_2',
            'counter' => 0
        ],
        'geo_3' => [
            'dtmf' => 's_1_3',
            'counter' => 0
        ],
        'geo_4' => [
            'dtmf' => 's_1_4',
            'counter' => 0
        ],
        'rus_news' => [
            'dtmf' => 's_2_1',
            'counter' => 0
        ],
        'rus_2' => [
            'dtmf' => 's_2_2',
            'counter' => 0
        ],
        'rus_3' => [
            'dtmf' => 's_2_3',
            'counter' => 0
        ],
        'rus_4' => [
            'dtmf' => 's_2_4',
            'counter' => 0
        ],
        'eng_news' => [
            'dtmf' => 's_3_1',
            'counter' => 0
        ],
        'eng_2' => [
            'dtmf' => 's_3_2',
            'counter' => 0
        ],
        'eng_3' => [
            'dtmf' => 's_3_3',
            'counter' => 0
        ],
        'eng_4' => [
            'dtmf' => 's_3_4',
            'counter' => 0
        ],
    ];

    private $dtmfMapping = [
        'geo_news' => 'ქართული - სიახლე',
        'geo_2' => 'ქართული - პაკეტი მეტი',
        'geo_3' => 'ქართული - ტარიფები და ეკონომ პაკეტები',
        'geo_4' => 'ქართული - პოპულარული სერვისები',
        'rus_news' => 'რუსული - სიახლე',
        'rus_2' => 'რუსული - პაკეტი მეტი',
        'rus_3' => 'რუსული - ტარიფები და ეკონომ პაკეტები',
        'rus_4' => 'რუსული - პოპულარული სერვისები',
        'eng_news' => 'ინგლისური - სიახლე',
        'eng_2' => 'ინგლისური - პაკეტი მეტი',
        'eng_3' => 'ინგლისური - ტარიფები და ეკონომ პაკეტები',
        'eng_4' => 'ინგლისური - პოპულარული სერვისები',
    ];

    /**
     * @var MailHandler
     */
    private $mailHandler;

    public function __construct() {
//        $this->mailHandler = new MailHandler();
//        $this->mailHandler->configureSMTP()->addRecipients(['tporchkhidze@silknet.com']);
    }

    public function getStatsBySips(GetStatsBySips $request) {
        $inputArr = $request->input();
        if(isset($inputArr['sipOnly']) and strtolower($inputArr['sipOnly']) == "true") {
            $inputArr['sipOnly'] = true;
        } else {
            $inputArr['sipOnly'] = false;
        }
        $result = QueueLog::getStatsBySips($inputArr['sips'], $inputArr['queueArr'] ?? null, $inputArr['sipOnly'], $inputArr['startDate'], $inputArr['endDate']);
        return $result;
    }



    public function getStatsByQueue(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getDefaultStatsByQueue($inputArr['queueName'], $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getOverallQueueStats(GetStatsByQueue $request) {
        $inputArr = $request->input();
        $queueOverallStats = QueueLog::getOverallQueueStats($inputArr['queueArr'], $inputArr['startDate'], $inputArr['endDate']);
        if($queueOverallStats['answered_calls'] == 0) {
            $queueOverallStats['avgTalkTime'] = 0;
        } else {
            $queueOverallStats['avgTalkTime'] = round(($queueOverallStats['ring_time'] + $queueOverallStats['talk_time'] + ($queueOverallStats['answered_calls'] * config('asterisk.ACW_TIME'))) / $queueOverallStats['answered_calls'], 0);
        }
        return $queueOverallStats;
    }

    public function getAnsweredCalls(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getAnsweredCalls($inputArr['queueArr'], $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getAbandonedCalls(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getAbandonedCalls($inputArr['queueArr'], $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getOutgoingTransfers(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getOutgoingTransfers($inputArr['queueArr'], $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getOutgoingTransfersToQueue(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getOutgoingTransfersToQueue($inputArr['fromQueues'], $inputArr['toQueue'], $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getOutgoingTransfersDetailedBySip(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getOutgoingTransfersDetailedBySip($inputArr['sipNumber'], $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getIncomingTransfers(Request $request) {
        $inputArr = $request->input();
//        logger()->error($inputArr);
        return QueueLog::getIncomingTransfers($inputArr['queueArr'], $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getIncomingTransfersFromQueue(Request $request) {
        $inputArr = $request->input();
        logger()->error($inputArr);
        return QueueLog::getIncomingTransfersFromQueue($inputArr['queueArr'], $inputArr['fromQueue'], $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getTransfersBySips(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getTransfersBySips($inputArr['sipNumber'], $inputArr['queueArr'] ?? null, $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getDTMF(Request $request) {
        $inputArr = $request->input();
        return CdrLog::getDTMF($inputArr['inNumber'] ?? null, $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getDTMFByCategories(Request $request) {
        $inputArr = $request->input();
        $dtmfArr = CdrLog::getAllDTMF($inputArr['inNumber'] ?? null, $inputArr['startDate'], $inputArr['endDate']);
        foreach($dtmfArr as $dtmf) {
            foreach($this->dtmfGroups as $dtmfName => $val) {
                if(strpos($dtmf, $val['dtmf']) !== false) {
                    $this->dtmfGroups[$dtmfName]['counter']++;
                }
            }
        }
        return $this->dtmfGroups;
    }

    public function getDTMFMapping(Request $request) {
        return $this->dtmfMapping;
    }

    public function getStatsByInNumber(Request $request) {
        $inputArr = $request->input();
        $stats = CdrLog::getStatsByInNumber($inputArr['inNumber'] ?? null, $inputArr['startDate'], $inputArr['endDate']);
        $uniqueCalls = CdrLog::getUniqueIncomingCalls($inputArr['inNumber'] ?? null, $inputArr['startDate'], $inputArr['endDate'], 'total');
        $uniqueCallsBeforeAbandons = CdrLog::getUniqueIncomingCalls($inputArr['inNumber'] ?? null, $inputArr['startDate'], $inputArr['endDate'], 'abandon');
        $beforeQueueCallTime = CdrLog::getBeforeQueueCallTime($inputArr['inNumber'] ?? null, $inputArr['startDate'], $inputArr['endDate']);
        return [
            'stats' => $stats,
            'unique' => $uniqueCalls,
            'uniqueAbandon' => $uniqueCallsBeforeAbandons,
            'beforeQueueCallTime' => $beforeQueueCallTime
        ];
    }

    public function getBeforeQueueAbandonedCalls(Request $request) {
        $inputArr = $request->input();
//        logger()->error($inputArr);
        return CdrLog::getBeforeQueueAbandonedCalls($inputArr['inNumber'] ?? null, $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getHoldTimeInQueue(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getHoldTimeInQueue($inputArr['queueArr'], $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getHoldTimeInQueueHourly(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getHoldTimeInQueueHourly($inputArr['queueArr'], $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getB2bAndB2cStats(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getB2bAndB2cStats($inputArr['startDate'], $inputArr['endDate']);
    }

    public function getStatsByCallerNumber(Request $request) {
        $inputArr = $request->input();
        $statsByCallerNumber = QueueLog::getStatsByCallerNumber($inputArr['callerNum'], $inputArr['startDate'], $inputArr['endDate']);
        $sipsToOperatorsMapping = Sip::getSipToOperatorMapping();
        return [
            'stats' => $statsByCallerNumber,
            'sipsToOperators' => $sipsToOperatorsMapping
        ];
    }

    public function getRepeatedCallsByQueue(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getRepeatedCallsByQueue($inputArr['queueArr'], $inputArr['startDate'], $inputArr['endDate'], $inputArr['counter']);
    }

    public function getCallTimeByQueue(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getCallTimeByQueue($inputArr['queueArr'], $inputArr['callTime'], $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getHourlyQueueStats(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getHourlyQueueStats($inputArr['queueArr'], $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getLiveQueueStats(Request $request) {
        $inputArr = $request->input();
        $asteriskManager = new AsteriskManager();
        $queueLiveStats = $asteriskManager->getQueueStatus($inputArr['queueName'] ?? null);
//        return $queueLiveStats;
        $queueGroups = QueueGroup::getGroupsWithQueues();

//        return $queueGroups;
        $totalUniqueSips = $totalPausedSips = $totalInCallSips = $totalRingingSips = $totalAcwSips = [];
        $totalInQueue = $totalFreeSips = 0;

        foreach($queueGroups as $groupName => $groupArr) {
            $groupQueues = $groupArr['queues'];

            $stats = [
                'uniqueActiveSips' => [],
                'uniquePausedSips' => [],
                'uniqueInCallSips' => [],
                'uniqueRingingSips' => [],
                'uniqueAcwSips' => [],
                'uniqueFreeSips' => 0
            ];
            unset($queueGroups[$groupName]['queues']);
            foreach($groupQueues as $queue) {
                $stats['uniqueActiveSips'] = array_merge($stats['uniqueActiveSips'], $queueLiveStats[$queue['name']]['activeSips']);
                $stats['uniquePausedSips'] = array_merge($stats['uniquePausedSips'], $queueLiveStats[$queue['name']]['pausedSips']);
                $stats['uniqueInCallSips'] = array_merge($stats['uniqueInCallSips'], $queueLiveStats[$queue['name']]['inCallSips']);
                $stats['uniqueRingingSips'] = array_merge($stats['uniqueRingingSips'], $queueLiveStats[$queue['name']]['ringingSips']);
                $stats['uniqueAcwSips'] = array_merge($stats['uniqueAcwSips'], $queueLiveStats[$queue['name']]['acwSips']);
                $totalUniqueSips = array_merge($totalUniqueSips, $queueLiveStats[$queue['name']]['activeSips']);
                $totalPausedSips = array_merge($totalPausedSips, $queueLiveStats[$queue['name']]['pausedSips']);
                $totalInCallSips = array_merge($totalInCallSips, $queueLiveStats[$queue['name']]['inCallSips']);
                $totalRingingSips = array_merge($totalRingingSips, $queueLiveStats[$queue['name']]['ringingSips']);
                $totalAcwSips = array_merge($totalAcwSips, $queueLiveStats[$queue['name']]['acwSips']);
                $queueLiveStats[$queue['name']]['activeSips'] = count($queueLiveStats[$queue['name']]['activeSips']);
                $queueLiveStats[$queue['name']]['pausedSips'] = count($queueLiveStats[$queue['name']]['pausedSips']);
//                $totalInCall += $queueLiveStats[$queue['name']]['inCall'];
                $totalInQueue += $queueLiveStats[$queue['name']]['inQueue'];
                $stats[$queue['name']] = $queueLiveStats[$queue['name']];
            }
//            logger()->info($stats);
            $stats['uniqueActiveSips'] = count(array_unique($stats['uniqueActiveSips']));
            $stats['uniquePausedSips'] = count(array_unique($stats['uniquePausedSips']));
            $stats['uniqueRingingSips'] = count(array_unique($stats['uniqueRingingSips']));
            $stats['uniqueAcwSips'] = count(array_unique($stats['uniqueAcwSips']));
            $stats['uniqueFreeSips'] = $stats['uniqueActiveSips'] - count(array_unique($stats['uniqueInCallSips'])) - $stats['uniquePausedSips'] - $stats['uniqueAcwSips'] - $stats['uniqueRingingSips'];
            unset($stats['uniqueInCallSips']);
            $queueGroups[$groupName]['stats'] = $stats;
        }
        $queueGroups['totalUniqueActiveSips'] = count(array_unique($totalUniqueSips));
        $queueGroups['totalUniquePausedSips'] = count(array_unique($totalPausedSips));
        $queueGroups['totalInCall'] = count(array_unique($totalInCallSips));
        $queueGroups['totalUniqueAcwSips'] = count(array_unique($totalAcwSips));
        $queueGroups['totalRingingSips'] = count(array_unique($totalRingingSips));
        $queueGroups['totalUniqueFreeSips'] = $queueGroups['totalUniqueActiveSips'] - $queueGroups['totalUniquePausedSips'] - $queueGroups['totalInCall'] - $queueGroups['totalUniqueAcwSips'] - $queueGroups['totalRingingSips'];
        $queueGroups['totalInQueue'] = $totalInQueue;
//        logger()->info($queueGroups);
        return $queueGroups;
    }

    public function getLiveQueueStatsTest(Request $request){
//        $userId = auth()->user()->id;
        $inputArr = $request->input();
        $asteriskManager = new AsteriskManager();
        $queueLiveStats = $asteriskManager->getQueueStatus($inputArr['queueName'] ?? null);
//        $queues = Queue::getQueues($userId);
        $queues = Queue::getQueues();
        $totalUniqueSips = $totalPausedSips = $totalInCallSips = $totalRingingSips = $totalAcwSips = [];
        $totalInQueue = $totalFreeSips = 0;
        foreach($queues as $queue) {
            $totalPausedSips = array_merge($totalPausedSips, $queueLiveStats[$queue['name']]['pausedSips']);
            $totalInCallSips = array_merge($totalInCallSips, $queueLiveStats[$queue['name']]['inCallSips']);
            $totalRingingSips = array_merge($totalRingingSips, $queueLiveStats[$queue['name']]['ringingSips']);
            $totalAcwSips = array_merge($totalAcwSips, $queueLiveStats[$queue['name']]['acwSips']);
            $queueLiveStats[$queue['name']]['activeSips'] = count($queueLiveStats[$queue['name']]['activeSips']);
            $queueLiveStats[$queue['name']]['pausedSips'] = count($queueLiveStats[$queue['name']]['pausedSips']);
            $queueLiveStats[$queue['name']]['freeSips'] = $queueLiveStats[$queue['name']]['activeSips']- $queueLiveStats[$queue['name']]['pausedSips'] - count(array_unique($queueLiveStats[$queue['name']]['inCallSips'])) - count($queueLiveStats[$queue['name']]['acwSips']) - count($queueLiveStats[$queue['name']]['ringingSips']);
//                $totalInCall += $queueLiveStats[$queue['name']]['inCall'];
            $totalInQueue += $queueLiveStats[$queue['name']]['inQueue'];
            $stats[$queue['name']] = $queueLiveStats[$queue['name']];
            unset($stats[$queue['name']]['uniqueInCallSips']);
        }
        return $stats;

    }

    public function getOngoingCallStats(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getOngoingCallStats($inputArr["sipArr"]);
    }

    public function getLiveStatsBySips(Request $request) {
        $inputArr = $request->input();
        $sipsLiveStatus = SipStatusLog::getSipsLastStatus($inputArr['sipArr']);
        $sipsToOperators = Sip::getSipToOperatorMapping();
        $onGoingCalls = QueueLog::getOngoingCallStats($inputArr['sipArr']);
        $pauseLastStatus = QueueLog::getPauseLastStatusBulk($inputArr['sipArr'], date("Y-m-d H:i:s", strtotime("-1 week")), date("Y-m-d H:i:s"));
        return [
            "sipsLiveStatus" => $sipsLiveStatus,
            "sipsToOperators" => $sipsToOperators,
            "onGoingCalls" => $onGoingCalls,
            "lastPauseStatus" => $pauseLastStatus
        ];
    }

    public function getLiveAgentsStats(Request $request) {
        $inputArr = $request->input();
        $asteriskManager = new AsteriskManager();
        return $asteriskManager->getSipStatuses();
    }

    public function getRecallsAfterAbandon(Request $request) {
        $inputArr = $request->input();
//        logger()->error($inputArr);
        return QueueLog::getRecallsAfterAbandon($inputArr["queueArr"], $inputArr["startDate"], $inputArr["endDate"]);
    }

    public function getOutGoingCallsByQueue(Request $request) {
        $inputArr = $request->input();
//        logger()->error($inputArr);
        $sipArr = Queue::getSipsByQueueName($inputArr['queueName']);
        return CdrLog::getOutGoingCallsByQueue($sipArr, $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getOutGoingCallDetailedBySip(Request $request) {
        $inputArr = $request->input();
        $sipData = Sip::getSipsWithTemplatesAndOperatorsAndQueues($inputArr['sipNumber']);
        return CdrLog::getOutGoingCallDetailed($sipData, $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getOutGoingCallDetailedByQueue(Request $request) {
        $inputArr = $request->input();
        $sipArr = Queue::getSipsByQueueName($inputArr['queueName']);
        return CdrLog::getOutGoingCallDetailed($sipArr, $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getCallsByPrefixes(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getCallsByPrefixes($inputArr['queueArr'], $inputArr['startDate'], $inputArr['endDate']);
    }


    public function getLastPauseStatus(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getLastPauseStatus($inputArr['sipNumber']) ?? [];
    }

    public function getPauseStatusDetailedV2(Request $request) {
        $inputArr = $request->input();
        if(isset($inputArr['onlyTime']) && strtolower($inputArr['onlyTime']) == 'true') {
            $onlyTime = true;
        } else {
            $onlyTime = false;
        }
        $queueName = Sip::getSipsWithTemplatesAndOperatorsAndQueues($inputArr['sipNumber'])[0]['queues'][0]['name'] ?? null;
        if(isset($queueName) === false) {
            throw new \RuntimeException("მოცემული სიპისთვის რიგი ვერ მოიძებნა!");
        }
        $pauseStats = QueueLog::getPauseStatusDetailedV3($inputArr['sipNumber'], $queueName, $inputArr['startDate'], $inputArr['endDate'], $onlyTime);
        return $pauseStats;
    }

    public function getDailyDetailedStatsForSip(Request $request) {
        $inputArr = $request->input();
        return QueueLog::getDailyDetailedStatsForSip($inputArr['sipNumber'], $inputArr['startDate'], $inputArr['endDate']);
    }

    public function getMonthlyDetailedStatsForOperator(Request $request) {
        $inputArr = $request->input();
        $sipToOperator = SipToOperatorHistory::getHistoryByOperator($inputArr['idNumber'], $inputArr['startDate'], $inputArr['endDate']);
        $statsBySips = [];
        foreach($sipToOperator as $sipArr) {
            $resultSet['callStats'] = QueueLog::getDetailedSipStatsForMonthlyReport($sipArr['sip'], $sipArr['paired_at'], $sipArr['removed_at']);
            $resultSet['sipNumber'] = $sipArr['sip'];
            $resultSet['sipStatusData'] = SipStatusLog::getRegisterUnregisterTime($sipArr['sip'], $sipArr['paired_at'], $sipArr['removed_at']);
            if(empty($resultSet) === false) $statsBySips[] = $resultSet;
        }
        return $statsBySips;
    }

    public function getCallsByTypes(Request $request) {
        $inputArr = $request->input();
//        logger()->error($inputArr);
        return QueueLog::getCallCountByQueuesAndType($inputArr['queueArr'], $inputArr['startDate'], $inputArr['endDate']);
    }

    public function testFunction(Request $request) {
        $inputArr = $request->input();
        $operatorsHistoryArr = SipToOperatorHistory::getSipsForOperatorByDate($inputArr['personalIDsArr'], $inputArr['startDate'], $inputArr['endDate']);
        return $operatorsHistoryArr;
//        return QueueLog::getPauseStatusDetailedV3($inputArr['sipNumber'], $inputArr['queueName'], $inputArr['startDate'], $inputArr['endDate']);
    }

}
