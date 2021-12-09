<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 2/19/2019
 * Time: 11:23 AM
 */

namespace App\AsteriskStatistics;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


class CdrLog extends Model {

    protected $connection = "mysql_asterisk_stats";

    private static $connName = "mysql_asterisk_stats";

    protected $table = "tbl_cdr";

    private static $foreignQueues = ['B2Crus', 'B2Ceng', 'B2Brus', 'B2Beng', 'PREPAIDeng', 'PREPAIDrus'];

    public static function getDTMF($inNumber, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $isInNumber = (isset($inNumber)) ? true : false;
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select
                                                   replace(case
                                                            when regexp_like(cdr.dtmf, '[087]') = 1 then substr(cdr.dtmf, 1, regexp_instr(cdr.dtmf, '[087]') - 2)
                                                            when regexp_like(cdr.dtmf, '[087]') <> 1 then cdr.dtmf
                                                           end , 's', 'start') as filtered_dtmf,
                                                   date_format(cdr.calldate, '%%Y-%%m-%%d') as date,
                                                   count(1) counter
                                            from db_asterisk.tbl_cdr cdr
                                            where cdr.calldate between STR_TO_DATE(:startDate, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(:endDate, '%%Y-%%m-%%d %%H:%%i:%%s')
                                              and cdr.calltype = 'IN'
                                              %s
                                            group by date, filtered_dtmf",($isInNumber) ?  "and cdr.innumber = :inNumber" : ""));
        if($isInNumber) $stmt->bindValue(":inNumber", $inNumber, \PDO::PARAM_STR);
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = [];
        $stmt->fetchAll(\PDO::FETCH_FUNC, function($dtmf, $date, $counter) use(&$resultSet) {
            $resultSet[$date][$dtmf] = $counter;
        });
        $stmt = null;
        return $resultSet;
    }

    public static function getDTMFGroupedByLang(string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select IFNULL(sum(case when cdr.dtmf like 's_1%' then 1 else 0 end), 0) as georgian,
                                                       IFNULL(sum(case when cdr.dtmf like 's_2%' then 1 else 0 end), 0) as russian,
                                                       IFNULL(sum(case when cdr.dtmf like 's_3%' then 1 else 0 end), 0) as english
                                        from db_asterisk.tbl_cdr cdr
                                        where cdr.calldate between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s') 
                                        and cdr.calltype = 'IN'");
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $row = array_map(function($val) {
            return intval($val);
        }, $row);
        $row['total'] = $row['georgian'] + $row['russian'] + $row['english'];
        $stmt = null;
        return $row;
    }

    public static function getAllDTMF(?string $inNumber, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select dtmf
                                                from db_asterisk.tbl_cdr cdr
                                                where cdr.calldate between STR_TO_DATE(:startDate, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(:endDate, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                  and cdr.calltype = 'IN' 
                                                  %s", (isset($inNumber)) ? " and cdr.innumber = :inNumber" : ""));
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        if(isset($inNumber)) $stmt->bindValue(":inNumber", $inNumber, \PDO::PARAM_STR);
        $stmt->execute();
        while(($row = $stmt->fetch(\PDO::FETCH_NUM)) !== false) {
            $resultSet[] = $row[0];
        }
        $stmt = null;
        return $resultSet;
    }

    public static function getStatsByInNumber($inNumber, string $startDate, string $endDate) {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $isInnumber = isset($inNumber) ? true : false;
        $stmt = $pdoHandler->prepare(sprintf("with
                                                         cdr as (select cdr.uniqueid,cdr.lastapp,cdr.innumber
                                                                from db_asterisk.tbl_cdr cdr
                                                                where cdr.calldate between STR_TO_DATE(:startDate, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(:endDate, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                                  and cdr.calltype = 'IN'
                                                                  and cdr.istransfer = 'NO'
                                                                  %s
                                                                group by cdr.uniqueid,cdr.lastapp,cdr.innumber
                                                                order by cdr.uniqueid)
                                                    select sum(case when cdr.lastapp = 'Queue' then 1 else 0 end) as entered_queue,
                                                           sum(case when cdr.lastapp <> 'Queue' then 1 else 0 end) as abandoned_before_queue,
                                                           sum(if(cdr.lastapp = 'Queue' and (select tql.callid from db_asterisk.tbl_queue_log tql where tql.callid = cdr.uniqueid and tql.event = 'CONNECT' limit 1) is not null, 1, 0) ) as answered_calls,
                                                           sum(if(cdr.lastapp = 'Queue' and (select tql2.callid from db_asterisk.tbl_queue_log tql2 where tql2.callid = cdr.uniqueid and tql2.event = 'ABANDON' limit 1) is not null, 1, 0)) as abandoned_calls,
                                                           cdr.innumber
                                                    from cdr                                            
                                                    %s",
                                             ($isInnumber) ? "and cdr.innumber = :inNumber" : "", ($isInnumber) ? "" : "group by cdr.innumber"));
        if ($isInnumber) $stmt->bindValue(":inNumber", $inNumber, \PDO::PARAM_STR);
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->execute();
//        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $resultSet = [];
        while(($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $row['total'] = $row['entered_queue'] + $row['abandoned_before_queue'];
            $resultSet[] = $row;
        }
        $stmt = null;
        return $resultSet;
    }

    public static function getUniqueIncomingCalls($inNumber, string $startDate, string $endDate, string $uniqueType) {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select count(distinct cdr.src) as unique_numbers, cdr.innumber
                                                    from db_asterisk.tbl_cdr cdr
                                                    where cdr.calldate between STR_TO_DATE(:startDate, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(:endDate, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                      %s
                                                      and cdr.calltype = 'IN'
                                                      and cdr.istransfer = 'NO'
                                                    %s", ($uniqueType == "total") ? "" : "and cdr.lastapp <> 'Queue'", (isset($inNumber)) ? "and cdr.innumber = :inNumber" : "group by cdr.innumber"));
        if(isset($inNumber)) $stmt->bindValue(":inNumber", $inNumber, \PDO::PARAM_STR);
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

    public static function getUniqueBeforeQueueAbandons($inNumber, string $startDate, string $endDate) {

    }

    public static function getBeforeQueueCallTime($inNumber, string $startDate, $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $isInnumber = ($inNumber) ? true : false;
        $stmt = $pdoHandler->prepare(sprintf("select IFNULL(round(sum(billsec) / 60, 0), 0) as abandoned_call_time,cdr.innumber
                                                from db_asterisk.tbl_cdr cdr
                                                where cdr.calldate between STR_TO_DATE(:startDate, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(:endDate, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                  and cdr.lastapp <> 'Queue'
                                                  and cdr.calltype = 'IN'
                                                  and cdr.istransfer = 'NO'
                                                  %s
                                                group by cdr.innumber", ($isInnumber) ? "and cdr.innumber = :inNumber" : ""));
        if($isInnumber) $stmt->bindValue(":inNumber", $inNumber, \PDO::PARAM_STR);
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

    public static function getOutGoingCallsByQueue(array $sipArr, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select sum(case when cdr.disposition = 'ANSWERED' then 1 else 0 end) as  answer_count,
                                               sum(case when cdr.disposition = 'ANSWERED' then cdr.billsec else 0 end) as total_duration,
                                               max(case when cdr.disposition = 'ANSWERED' then cdr.billsec else 0 end) as max_duration,
                                               min(case when cdr.disposition = 'ANSWERED' then cdr.billsec else null end) as min_duration,
                                               round(avg(case when cdr.disposition = 'ANSWERED' then cdr.billsec else null end), 2) as avg_duration,
                                               sum(case when cdr.disposition <> 'ANSWERED' then 1 else 0 end) as no_answer_count
                                        from db_asterisk.tbl_cdr cdr
                                        where cdr.calldate between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                          and cdr.calltype = 'OUT'
                                          and cdr.channel like :sip");
        $stmt->bindParam(':sip', $sip, \PDO::PARAM_STR);
        $stmt->bindValue(':startDate', $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(':endDate', $endDate, \PDO::PARAM_STR);
        $resultSet = [
            'total' => [
                'duration' => 0,
                'answerCount' => 0,
                'noAnswerCount' => 0,
                'avgDuration' => 0
            ]
        ];
        foreach($sipArr as $sipData) {
            $sip = sprintf("SIP/%s%%", $sipData['sip']);
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if(empty($row)) continue;
            if($row['answer_count'] == 0 and $row['no_answer_count'] == 0) continue;
            $resultSet[$sipData['sip']]['answerCount'] = intval($row['answer_count']) ?? 0;
            $resultSet[$sipData['sip']]['noAnswerCount'] = intval($row['no_answer_count']) ?? 0;
            $resultSet[$sipData['sip']]['totalDuration'] = intval($row['total_duration']) ?? 0;
            $resultSet[$sipData['sip']]['avgDuration'] = floatval($row['avg_duration']) ?? 0;
            $resultSet[$sipData['sip']]['maxDuration'] = intval($row['max_duration']) ?? 0;
            $resultSet[$sipData['sip']]['minDuration'] = intval($row['min_duration']) ?? 0;
            $resultSet[$sipData['sip']]['operator'] = ($sipData['operators_id'] == "Not assigned") ? "Empty" : sprintf("%s %s", $sipData['first_name'], $sipData['last_name']);
            $resultSet['total']['duration'] += intval($row['total_duration']) ?? 0;
            $resultSet['total']['answerCount'] += intval($row['answer_count']) ?? 0;
            $resultSet['total']['noAnswerCount'] += intval($row['no_answer_count']) ?? 0;
        }
        if($resultSet['total']['answerCount'] != 0) {
            $resultSet['total']['avgDuration'] = round($resultSet['total']['duration'] / $resultSet['total']['answerCount'], 2);
        }
        $stmt = null;
        return $resultSet;
    }

    public static function getOutGoingCallDetailed(array $sipArr, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select cdr.calldate,
                                               cdr.src,
                                               substr(cdr.channel, 1, 7) sip,
                                               cdr.dst,
                                               cdr.disposition,
                                               cdr.duration
                                        from db_asterisk.tbl_cdr cdr
                                        where cdr.calldate between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                          and cdr.calltype = 'OUT'
                                          and cdr.channel like :sip");
        $stmt->bindParam(":sip", $sip, \PDO::PARAM_STR);
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $resultSet = [];
        foreach($sipArr as $sipData) {
            $sip = sprintf("SIP/%s%%", $sipData['sip']);
            $stmt->execute();
            while(($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
                $resultSet[] = $row;
            }
        }
        $stmt = null;
        return $resultSet;
    }

    public static function getForeignLangCalls(string $startDate, string $endDate, array $queues): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $formattedQueues = sprintf("'^%s'", implode("|^", $queues));
        $stmt = $pdoHandler->prepare(sprintf("with
                                                 filtered_table as (select cdr.uniqueid, LEFT(cdr.lastdata, instr(cdr.lastdata, ',') - 1) as queue, cdr.istransfer
                                                                    from db_asterisk.tbl_cdr cdr
                                                                    where cdr.calldate between STR_TO_DATE(:startDate, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(:endDate, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                                      and cdr.lastapp = 'Queue'
                                                                      and cdr.calltype = 'IN'
                                                                      and REGEXP_LIKE(cdr.lastdata, %s)
                                                                    group by queue, cdr.uniqueid, cdr.istransfer)
                                            select count(1) as counter, queue, istransfer
                                            from filtered_table
                                            group by queue, istransfer", $formattedQueues));
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = [];
        $stmt->fetchAll(\PDO::FETCH_FUNC, function($counter, $queue, $isTransfer) use(&$resultSet) {
            $resultSet[$queue][($isTransfer == "YES") ? "transfered" : "direct"] = intval($counter);
        });
        $stmt  = null;
        return $resultSet;
    }

    public static function getForeignLangCallsDetails(string $startDate, string $endDate, array $queues, bool $isDirect): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $formattedQueues = sprintf("'^%s'", implode("|^", $queues));
        $stmt = $pdoHandler->prepare(sprintf("select cdr.uniqueid
                                                                from db_asterisk.tbl_cdr cdr
                                                                where cdr.calldate between STR_TO_DATE(:startDate, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(:endDate, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                                  and cdr.lastapp = 'Queue'
                                                                  and cdr.calltype = 'IN'
                                                                  and cdr.istransfer = '%s'
                                                                  and REGEXP_LIKE(cdr.lastdata, %s)
                                                                group by cdr.uniqueid, cdr.istransfer", ($isDirect) ? "NO" : "YES", $formattedQueues));
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $uniqueIDArr = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $stmt = null;
        if(empty($uniqueIDArr)) {
            return [];
        }
        $bindMarks = implode(",", array_fill(0, count($uniqueIDArr), "?"));
        $stmt = $pdoHandler->prepare(sprintf("select tql.time,tql.callid,tql.queuename,tql.agent,tql.event,tql.data1,tql.data2,tql.data3
                                                from db_asterisk.tbl_queue_log tql
                                                where tql.callid in (%s)
                                                order by tql.callid asc, tql.id asc", $bindMarks));
        $index = 1;
        foreach($uniqueIDArr as $uniqueID) {
            $stmt->bindValue($index, $uniqueID, \PDO::PARAM_STR);
            $index++;
        }
        $stmt->execute();
        $currCall = "";
        $resultSet = [];
        $callData = [];
        $callType = "";
        while(($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
            if($currCall == "") {
                $currCall = $row['callid'];
            }
            if($currCall != $row['callid']) {
                $resultSet[] = $callData;
                $currCall = $row['callid'];
                $callType = "";
                $callData = [];
            }
            switch($row['event']) {
                case "INFO":
                    if($callType == "") {
                        $callType = $row['data2'];
                    }
                    break;
                case "ENTERQUEUE":
                    $tmpArr = [
                        'uniqueID' => $row['callid'],
                        'time' => $row['time'],
                        'queueName' => $row['queuename'],
                        'caller' => $row['data2'],
                        'event' => $row['event']
                    ];
                    if($callType != "") {
                        $tmpArr['callType'] = $callType;
                    }
                    $callData[] = $tmpArr;
                    break;
                case "CONNECT":
                    $callData[] = [
                        'uniqueID' => $row['callid'],
                        'time' => $row['time'],
                        'queueName' => $row['queuename'],
                        'sipNumber' => substr($row['agent'], strpos($row['agent'], '/') + 1),
                        'ringingTime' => $row['data1'],
                        'event' => $row['event']
                    ];
                    break;
                case "BLINDTRANSFER":
                    $callData[] = [
                        'uniqueID' => $row['callid'],
                        'time' => $row['time'],
                        'queueName' => $row['queuename'],
                        'sipNumber' => substr($row['agent'], strpos($row['agent'], '/') + 1),
                        'transferCode' => $row['data1'],
                        'event' => $row['event']
                    ];
                    break;
                case "COMPLETECALLER":
                case "COMPLETEAGENT":
                    $callData[] = [
                        'uniqueID' => $row['callid'],
                        'time' => $row['time'],
                        'queueName' => $row['queuename'],
                        'sipNumber' => substr($row['agent'], strpos($row['agent'], '/') + 1),
                        'event' => $row['event'],
                        'callTime' => $row['data2']
                    ];
                    break;
                case "RINGNOANSWER":
                    $callData[] = [
                        'uniqueID' => $row['callid'],
                        'time' => $row['time'],
                        'queueName' => $row['queuename'],
                        'sipNumber' => substr($row['agent'], strpos($row['agent'], '/') + 1),
                        'event' => (intval($row['data1']) <= config('asterisk.DND_TIMER')) ? 'DND' : 'MISS',
                    ];
                    break;
                case "ABANDON":
                    $callData[] = [
                        'uniqueID' => $row['callid'],
                        'time' => $row['time'],
                        'queueName' => $row['queuename'],
                        'abandonPos' => $row['data1'],
                        'originalPos' => $row['data2'],
                        'waitingTime' => $row['data3']
                    ];
                    break;
            }
        }
        if(empty($callData) === false) {
            $resultSet[] = $callData;
        }
        $stmt = null;
        return $resultSet;
    }

    public static function getBeforeQueueAbandonedCalls($inNumber, string $startDate, string $endDate) {
        $isInNumber = (isset($inNumber)) ? true : false;
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select cdr.calldate,cdr.uniqueid,cdr.src,cdr.billsec duration,cdr.innumber
                                                from db_asterisk.tbl_cdr cdr
                                                where cdr.calldate between STR_TO_DATE(:startDate, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(:endDate, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                  and cdr.lastapp <> 'Queue'
                                                  and cdr.calltype = 'IN'
                                                  and cdr.istransfer = 'NO'
                                                  %s
                                                order by cdr.id desc", ($isInNumber) ? "and cdr.innumber = :inNumber" : ""));
        if($isInNumber) $stmt->bindValue(":inNumber", $inNumber, \PDO::PARAM_STR);
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

}