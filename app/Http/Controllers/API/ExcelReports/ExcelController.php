<?php

namespace App\Http\Controllers\API\ExcelReports;


use App\AsteriskStatistics\CdrLog;
use App\Common\ExcelGenerators\GeocellCallReport;
use App\CRR;
use App\Traits\DateTimeTrait;
use DateTime;
use App\AsteriskStatistics\QueueLog;
use App\Http\Controllers\Controller;
use App\Logging\Logger;
use App\Queue;
use App\QueueGroup;
use Illuminate\Http\Request;

class ExcelController extends Controller {

    use DateTimeTrait;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct() {
        $this->logger = Logger::instance();
    }

    public function generateGeocellCallReport(Request $request) {
        $inputArr = $request->input();
        $intervalsArr = $this->getMonthsByGivenDates($inputArr['startDate'], $inputArr['endDate']);
        $queues = array_column(Queue::all('name')->toArray(), 'name');
        $groupsWithQueues = QueueGroup::getGroupsWithQueues(null);
        $prepaidGroup = array_column($groupsWithQueues['prepaid_group']['queues'], 'name');
        $b2cGroup = array_column($groupsWithQueues['b2c_group']['queues'], 'name');
        $b2bGroup = array_column($groupsWithQueues['b2b_group']['queues'], 'name');
        $allStats = [];
        foreach($intervalsArr as $interval) {
//            logger()->error($interval);
            $allStats[$interval['startDate']]['totalCallStats'] = $this->getTotalCallStats($queues, $prepaidGroup, $b2cGroup, $b2bGroup, $interval['startDate'], $interval['endDate']);
            $allStats[$interval['startDate']]['callCenterAgentStats'] = $this->getGeocellCallCenterAgentsStats($queues, $interval['startDate'], $interval['endDate']);
            $answerAbandonCount = QueueLog::getWaitCount($queues, $interval['startDate'], $interval['endDate']);
            if($allStats[$interval['startDate']]['totalCallStats']['total']['answered'] != 0) {
                $answerAbandonPercent['answered'] = round((($answerAbandonCount['answeredCount'] / $allStats[$interval['startDate']]['totalCallStats']['total']['answered'])), 2);
                $answerAbandonPercent['abandoned'] = round((($answerAbandonCount['abandonedCount'] / $allStats[$interval['startDate']]['totalCallStats']['total']['answered'])), 2);
            } else {
                $answerAbandonPercent['answered'] = 0;
                $answerAbandonPercent['abandoned'] = 0;
            }
            $waitTimeArr = QueueLog::getWaitingTimeBeforeAnswer($queues, $interval['startDate'], $interval['endDate']);
            $abandonTimeArr = QueueLog::getAbandonWaitTimes($queues, $interval['startDate'], $interval['endDate']);
            $allStats[$interval['startDate']]['kpiStats'] = $answerAbandonPercent;
            $allStats[$interval['startDate']]['kpiStats']['avgWaitTimeSec'] = $waitTimeArr['avgWaitTime'];
            $allStats[$interval['startDate']]['kpiStats']['maxWaitTimeMin'] = round($waitTimeArr['maxWaitTime'] / 60, 2);
            $allStats[$interval['startDate']]['kpiStats']['avgAbandonTimeSec'] = $abandonTimeArr['avgWaitTime'];
            if($allStats[$interval['startDate']]['totalCallStats']['total']['total'] == 0) {
                $allStats[$interval['startDate']]['kpiStats']['avgTalkTimeMin'] = 0;
            } else {
                $allStats[$interval['startDate']]['kpiStats']['avgTalkTimeMin'] = round($allStats[$interval['startDate']]['callCenterAgentStats']['totalMin'] / $allStats[$interval['startDate']]['totalCallStats']['total']['total'], 2);
            }
            $allStats[$interval['startDate']]['kpiStats']['UCR'] = $this->getUnwantedCallRatio($interval['startDate'], $interval['endDate']);
            $allStats[$interval['startDate']]['IVR'] = CdrLog::getDTMFGroupedByLang($interval['startDate'], $interval['endDate']);
            $allStats[$interval['startDate']]['transferedCalls']['count'] = QueueLog::getTransfersCountFromSilknetSide($interval['startDate'], $interval['endDate']);
            if($allStats[$interval['startDate']]['totalCallStats']['total']['total'] != 0) {
                $allStats[$interval['startDate']]['transferedCalls']['ratio'] = round($allStats[$interval['startDate']]['transferedCalls']['count'] / $allStats[$interval['startDate']]['totalCallStats']['total']['total'], 2);
            }
        }
        $excelGenerator = new GeocellCallReport($allStats);
        $excelGenerator->generateReport();
        return $allStats;
    }

    private function getMonthsByGivenDates(string $startDate, string $endDate) {
        $startDate = new DateTime($startDate);
        $endDate = new DateTime($endDate);
        $periodArr = $this->getPeriodArr("month", $startDate, $endDate);
        $groupedByIntervals = [];
        $counter = count($periodArr);
        if($counter == 1) {
            $groupedByIntervals[0]['startDate'] = $periodArr[0];
            $groupedByIntervals[0]['endDate'] = $endDate->format("Y-m-d");
        } else if($counter > 1) {
            foreach($periodArr as $key => $date) {
                $groupedByIntervals[$key]['startDate'] = $date;
                $nextDate = next($periodArr);
                if($nextDate === false) {
                    $lastDate = new DateTime($date);
                    if($endDate->getTimestamp() > $lastDate->getTimestamp()) {
                        $groupedByIntervals[$key]['endDate'] = $endDate;
                    } else {
                        unset($groupedByIntervals[$key]);
                    }
                }
                ($nextDate === false) ? $groupedByIntervals[$key]['endDate'] = $endDate->format("Y-m-d") : $groupedByIntervals[$key]['endDate'] = $nextDate;
            }
        }
        return $groupedByIntervals;
    }

    private function getTotalCallStats($queues, $prepaidGroup, $b2cGroup, $b2bGroup, $startDate, $endDate):array {
        $allQueueStats = QueueLog::getOverallQueueStats($queues, $startDate, $endDate, true);
        $SMECallsInB2C = QueueLog::getCallCountByQueuesAndType($b2cGroup, $startDate, $endDate)['SME'] ?? [];
//        logger()->error($SMECallsInB2C);
        $totalStats = $prepaidStats = $b2bStats = $b2cStats = $avgTotalStats = [
            'total' => 0,
            'answered' => 0,
            'abandoned' => 0,
            'responses' => 0
        ];
        foreach($allQueueStats as $queueStats) {
            if(in_array($queueStats['queuename'], $prepaidGroup)) {
                $prepaidStats['total'] += $queueStats['entered_calls'];
                $prepaidStats['answered'] += $queueStats['answered_calls'];
                $prepaidStats['abandoned'] += $queueStats['abandoned_calls'];
            } else if(in_array($queueStats['queuename'], $b2cGroup)) {
                $b2cStats['total'] += $queueStats['entered_calls'];
                $b2cStats['answered'] += $queueStats['answered_calls'];
                $b2cStats['abandoned'] += $queueStats['abandoned_calls'];
            } else if(in_array($queueStats['queuename'], $b2bGroup)) {
                $b2bStats['total'] += $queueStats['entered_calls'];
                $b2bStats['answered'] += $queueStats['answered_calls'];
                $b2bStats['abandoned'] += $queueStats['abandoned_calls'];
            }
            $totalStats['total'] += $queueStats['entered_calls'];
            $totalStats['answered'] += $queueStats['answered_calls'];
            $totalStats['abandoned'] += $queueStats['abandoned_calls'];
        }
        if(empty($SMECallsInB2C) === false) {
//            logger()->error($queueStats);
            $b2cStats['total'] -= $SMECallsInB2C['total'];
            $b2cStats['answered'] -= $SMECallsInB2C['connect'];
            $b2cStats['abandoned'] -= $SMECallsInB2C['abandon'];
            $b2bStats['total'] += $SMECallsInB2C['total'];
            $b2bStats['answered'] += $SMECallsInB2C['connect'];
            $b2bStats['abandoned'] += $SMECallsInB2C['abandon'];
        }
//        logger()->error(sprintf("STARTDATE: %s ENDDATE: %s", $startDate, $endDate));
        if($totalStats['total'] !== 0) {
            $totalStats['responses'] = round(($totalStats['answered'] / $totalStats['total']), 2);
            $dateObj = new DateTime($startDate);
            $avgTotalStats['total'] = round($totalStats['total'] / cal_days_in_month(CAL_GREGORIAN, $dateObj->format("m"), $dateObj->format("Y")));
            $avgTotalStats['answered'] = round($totalStats['answered'] / cal_days_in_month(CAL_GREGORIAN, $dateObj->format("m"), $dateObj->format("Y")));
            $avgTotalStats['abandoned'] = round($totalStats['abandoned'] / cal_days_in_month(CAL_GREGORIAN, $dateObj->format("m"), $dateObj->format("Y")));
            $avgTotalStats['responses'] = round(($avgTotalStats['answered'] / $avgTotalStats['total']), 2);
        }
        if($b2cStats['total'] !== 0) $b2cStats['responses'] = round(($b2cStats['answered'] / $b2cStats['total']), 2);
        if($b2bStats['total'] !== 0) $b2bStats['responses'] = round(($b2bStats['answered'] / $b2bStats['total']), 2);
        if($prepaidStats['total'] !== 0) $prepaidStats['responses'] = round(($prepaidStats['answered'] / $prepaidStats['total']), 2);
        return [
            'prepaid' => $prepaidStats,
            'b2c' => $b2cStats,
            'b2b' => $b2bStats,
            'total' => $totalStats,
            'avgTotalStats' => $avgTotalStats
        ];
    }

    private function getGeocellCallCenterAgentsStats(array $queues, string $startDate, string $endDate):array {
        $statArr = [];
        $statArr['totalMin'] = QueueLog::getTotalInboundCallDuration($queues, $startDate, $endDate);
        $dateObj = new DateTime($startDate);
        $days = cal_days_in_month(CAL_GREGORIAN, $dateObj->format("m"), $dateObj->format("Y"));
        $statArr['days'] = $days;
        return $statArr;
    }

    private function getUnwantedCallRatio(string $startDate, string $endDate) {
        $allCRR = CRR::getAllCRRCount($startDate, $endDate);
        $unwantedCRR = CRR::getUnwantedCRRCount($startDate, $endDate);
//        logger()->error(sprintf("%s %s %s   %s", $startDate, $endDate, $allCRR, $unwantedCRR));
        if($unwantedCRR == 0) {
            return 0;
        }
        return round(($unwantedCRR / $allCRR), 2);
    }

}
