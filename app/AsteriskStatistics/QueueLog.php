<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 2/12/2019
 * Time: 12:05 PM
 */

namespace App\AsteriskStatistics;


use App\Queue;
use function GuzzleHttp\Psr7\str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Traits\CommonFuncs;

class QueueLog extends  Model {

    use CommonFuncs;

    public function __construct(array $attributes = []) {
        parent::__construct($attributes);

    }

    protected $connection = "mysql";

    private static $connName = "mysql";

    private static $transferMapping = [
        'b2cgeo' => [
            'codes' => ['43'],
            'queues' => ['B2Crus', 'B2Ceng']
        ],
        'b2crus' => [
            'codes' => ['41'],
            'queues' => ['B2Cgeo', 'B2Ceng']
        ],
        'b2ceng' => [
            'codes' => ['42'],
            'queues' => ['B2Crus', 'B2Cgeo']
        ],
        'prepaidgeo' => [
            'codes' => ['43', '37'],
            'queues' => ['PREPAIDrus', 'PREPAIDeng']
        ],
        'prepaidrus' => [
            'codes' => ['41'],
            'queues' => ['PREPAIDeng', 'PREPAIDgeo']
        ],
        'prepaideng' => [
            'codes' => ['42'],
            'queues' => ['PREPAIDrus', 'PREPAIDeng']
        ],
        'b2bgeo' => [
            'codes' => ['43'],
            'queues' => ['B2Brus', 'B2Beng']
        ],
        'b2brus' => [
            'codes' => ['41'],
            'queues' => ['B2Bgeo', 'B2Beng']
        ],
        'b2beng' => [
            'codes' => ['42'],
            'queues' => ['B2Brus', 'B2Bgeo']
        ],
        'silknet' => [
            'codes' => ['55'],
        ],
        'silknetCorp' => [
            'codes' => ['66']
        ],
        'CORP' => [
            'codes' => ['27']
        ],
        '100400' => [
            'codes' => ['29']
        ],
        '100100' => [
            'codes' => ['21']
        ],
        '100100rus' => [
            'codes' => ['91']
        ],
        '100100eng' => [
            'codes' => ['92']
        ],
        'tshoot' => [
            'codes' => ['22']
        ],
        'tshootrus' => [
            'codes' => ['93']
        ],
        'tshooteng' => [
            'codes' => ['94']
        ],
        '28' => [
            'codes' => ['28']
        ],
        'tshoot-zsmart'=>[
            'codes'=>['64']
        ],
        'zsmartq'=>[
            'codes'=>['56']
        ]
    ];

    private static $eligibleTransferCodes = ['41', '42', '43', '55', '66', '21', '22', '27', '29', '91', '92', '22', '93', '94', '28', '56', '64'];

    private static $codes_to_queues = array(
        "27" => "CORP",
        "29" => "100400",
        "21" => "100100",
        "91" => "100100rus",
        "92" => "100100eng",
        "22" => "tshoot",
        "93" => "tshootrus",
        "94" => "tshooteng",
        "28" => "28",
        "55"=>"geocell",
        "56"=>"zsmartq",
        "64"=>"tshoot-zsmart"
    );

    private static $silkTransfer = '55';

    private static $silkCorpTransfer = '66';

    protected $table = "db_asterisk.tbl_queue_log";

    public static function getStatsForBonus(int $sip, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select substr(tql.agent, instr(tql.agent, '/')+1) extension,
                                               sum(case
                                                     when tql.event = 'RINGNOANSWER' then
                                                       case
                                                         when cast(tql.data1 as unsigned) < 1001 then 1
                                                         else 0 end
                                               else 0 end)                                                                                   as dnd,
                                               sum(case
                                                     when tql.event = 'RINGNOANSWER' then
                                                       case
                                                         when cast(tql.data1 as unsigned) > 1001 then 1
                                                         else 0
                                                         end
                                               else 0 end)                                                                            as missed,
                                               sum(case when (tql.event in ('CONNECT') and (select count(1) from db_asterisk.tbl_queue_log tql2 where tql2.callid = tql.callid and tql2.event = 'BLINDTRANSFER' and tql2.queuename not in ('PREPAIDrus', 'PREPAIDeng', 'B2Beng', 'B2Brus') and tql2.data1 in ('41', '42')) = 0 )  then 1 else 0 end)                            as answered
                                        from db_asterisk.tbl_queue_log tql
                                        where tql.agent = ? and
                                              tql.time between STR_TO_DATE(?, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(?, '%Y-%m-%d %H:%i:%s')
                                          and tql.event in ('CONNECT', 'COMPLETEAGENT', 'COMPLETECALLER', 'BLINDTRANSFER', 'RINGNOANSWER')");
        $index = 1;
        $stmt->bindValue($index++, sprintf("SIP/%s", $sip), \PDO::PARAM_STR);
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $stmt = null;
        if($row === false or $row['extension'] == null) {
            return [];
        } else {
            return $row;
        }
    }

    public static function getStatsBySips(array $sipsArr, ?array $queueArr, bool $sipOnly, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $sipsArr = array_map(function(int $val) {
            if(is_numeric($val) === false) {
                throw new \RuntimeException("Sip can only contain numbers!");
            }
            return sprintf("SIP/%d", $val);
        }, $sipsArr);
        $isQueueArr = (isset($queueArr)) ? true : false;
        if($isQueueArr) $bindMarks = implode(",", array_fill(0, count($queueArr), "?"));
        $sipBindmarks = implode(",", array_fill(0, count($sipsArr), "?"));
        $stmt = $pdoHandler->prepare(sprintf("select tql.agent,
                                               sum(case
                                                     when tql.event = 'RINGNOANSWER' then
                                                       case
                                                         when cast(tql.data1 as unsigned) < 1001 then 1
                                                         else 0 end
                                               else 0 end)                                                                                   as DND,
                                               sum(case
                                                     when tql.event = 'RINGNOANSWER' then
                                                       case
                                                         when cast(tql.data1 as unsigned) > 1001 then 1
                                                         else 0
                                                         end
                                               else 0 end)                                                                            as missed_call,
                                               sum(case when tql.event in ('CONNECT') then 1 else 0 end)                            as answered_calls,
                                               sum(case when tql.event = 'COMPLETECALLER' then 1 else 0 end)                     as caller_hangup,
                                               sum(case when tql.event = 'COMPLETEAGENT' then 1 else 0 end)                      as agent_hangup,
                                               sum(case when tql.event in ('CONNECT', 'RINGNOANSWER') then 1 else 0 end) as total_calls,
                                               round(avg(case when tql.event in ('COMPLETECALLER', 'COMPLETEAGENT') then cast(tql.data1 as unsigned) else 0 end)) as hold_time,
                                               sum(case when tql.event in ('COMPLETECALLER', 'COMPLETEAGENT') then cast(tql.data2 as unsigned) else 0 end) as call_time,
                                               sum(case when tql.event in ('CONNECT') then cast(tql.data3 as unsigned) else 0 end)                            as ring_time,
                                               sum(case when tql.event = 'BLINDTRANSFER' then 1 else 0 end)                   as transfers
                                        from db_asterisk.tbl_queue_log tql
                                        where tql.agent in (%s) and
                                              tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                          and tql.event in ('CONNECT', 'COMPLETEAGENT', 'COMPLETECALLER', 'BLINDTRANSFER', 'RINGNOANSWER', 'PAUSE', 'UNPAUSE')
                                          %s
                                        group by tql.agent order by tql.agent asc", $sipBindmarks,($isQueueArr) ? sprintf("and queuename in (%s)", $bindMarks) : ""));
        $index = 1;
//        $stmt->bindParam($index++, $sipQueue, \PDO::PARAM_STR);
//        $stmt->bindParam($index++, $sipQueue, \PDO::PARAM_STR);
//        $stmt->bindParam($index++, $sipQueue, \PDO::PARAM_STR);
//        $stmt->bindParam($index++, $sipQueue, \PDO::PARAM_STR);
        foreach($sipsArr as $sip) {
            $stmt->bindValue($index++, $sip, \PDO::PARAM_STR);
        }
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        if($isQueueArr) {
            foreach($queueArr as $queueName) {
                $stmt->bindValue($index++, $queueName, \PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        $sipsResultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if(empty($sipsResultSet)) return [];
        $resultSet = [
            'total' => [
                'totalCallTime' => 0,
                'totalAnswered' => 0,
                'totalRingTime' => 0,
                'totalAgentHangup' => 0,
                'totalCallerHangup' => 0,
                'totalCalls' => 0,
                'totalHoldTime' => 0,
                'totalTransfers' => 0,
                'totalMissed' => 0,
                'totalDND' => 0,
                'avgCallTime' => 0,
            ]
        ];
        foreach ($sipsResultSet as $row) {
            $sip = $row['agent'];
            $sipQueue = Queue::getQueuesBySip(substr($row['agent'], strpos($row['agent'], "/") + 1))[0]['name'];
            $resultSet['total']['totalAnswered'] += $row['answered_calls'];
            $resultSet['total']['totalCallTime'] += $row['call_time'];
            $resultSet['total']['totalRingTime'] += $row['ring_time'];
            $resultSet['total']['totalAgentHangup'] += $row['agent_hangup'];
            $resultSet['total']['totalCallerHangup'] += $row['caller_hangup'];
            $resultSet['total']['totalCalls'] += $row['total_calls'];
            $resultSet['total']['totalHoldTime'] += $row['hold_time'];
            $resultSet['total']['totalTransfers'] += $row['transfers'];
            $resultSet['total']['totalMissed'] += $row['missed_call'];
            $resultSet['total']['totalDND'] += $row['DND'];
            if ($row['answered_calls'] !== "0") {
                $row['avg_call_time'] = round(($row['call_time'] + $row['ring_time'] + ($row['answered_calls'] * config('asterisk.ACW_TIME'))) / $row['answered_calls'], 0);
            } else {
                $row['avg_call_time'] = "0";
            }
            if(!$sipOnly) {
                try {
                    $pauseStats = self::getPauseStatusDetailedV3(substr($row['agent'], strpos($row['agent'], "/") + 1), $sipQueue, $startDate, $endDate, true);
                    foreach (array_keys($pauseStats) as $key) {
                        $row[$key] = $pauseStats[$key];
                    }
                } catch (\Exception $e) {
                    logger()->error(sprintf("Exception: %s, File: %s, Line: %s", $e->getMessage(), $e->getFile(), $e->getLine()));
                    $row['teaBreakTime'] = "err_t";
                    $row['teaBreakCounter'] = "err_c";
                    $row['lunchTime'] = "err_t";
                    $row['lunchCounter'] = "err_c";
                    $row['FAQTime'] = "err_t";
                    $row['FAQCounter'] = "err_c";
                    $row['workTime'] = "err_t";
                }
            }
            $resultSet[] = $row;
        }
        if ($resultSet['total']['totalCalls'] === 0) {
            return [];
        }
        if ($resultSet['total']['totalAnswered'] !== 0) {
            $resultSet['total']['avgCallTime'] = round(($resultSet['total']['totalCallTime'] + $resultSet['total']['totalRingTime'] + ($resultSet['total']['totalAnswered'] * config('asterisk.ACW_TIME'))) / $resultSet['total']['totalAnswered'], 0);
        }
        return $resultSet;
    }

    public static function getDefaultStatsByQueue(string $queueName, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select tql.agent,
                                               sum(case
                                                     when tql.event = 'RINGNOANSWER' then
                                                       case
                                                         when cast(tql.data1 as unsigned) < 1001 then 1
                                                         else 0 end
                                               else 0 end)                                                                                   as missed_call,
                                               sum(case
                                                     when tql.event = 'RINGNOANSWER' then
                                                       case
                                                         when cast(tql.data1 as unsigned) > 1001 then 1
                                                         else 0
                                                         end
                                               else 0 end)                                                                            as DND,
                                               sum(case when tql.event = 'CONNECT' then 1 else 0 end)                            as answered_calls,
                                               sum(case when tql.event = 'COMPLETECALLER' then 1 else 0 end)                     as caller_hangup,
                                               sum(case when tql.event = 'COMPLETEAGENT' then 1 else 0 end)                      as agent_hangup,
                                               sum(case when tql.event in ('COMPLETEAGENT', 'COMPLETECALLER') then 1 else 0 end) as total_calls,
                                               sum(case when tql.event in ('COMPLETECALLER', 'COMPLETEAGENT') then cast(tql.data1 as unsigned) else 0 end) as hold_time,
                                               sum(case when tql.event in ('COMPLETECALLER', 'COMPLETEAGENT') then cast(tql.data2 as unsigned) else 0 end) as call_time,
                                               sum(case when tql.event = 'BLINDTRANSFER' then 1 else 0 end)                   as transfers
                                        from db_asterisk.tbl_queue_log tql
                                        where tql.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s') and
                                              tql.queuename = :queueName and
                                              tql.event in ('CONNECT', 'COMPLETEAGENT', 'COMPLETECALLER', 'BLINDTRANSFER', 'RINGNOANSWER')
                                        group by tql.agent");
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->bindValue(":queueName", $queueName, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::PARAM_STR);
        $stmt = null;
        return $resultSet;
    }

    public static function getOverallQueueStats(array $queueArr, string $startDate, string $endDate, ?bool $grouped=null, ?string $dateFormat=null): array {
        /**
         * @var \PDO $pdoHandler
         */
        if(isset($grouped) && isset($dateFormat)) {
            $dateFormatGroup = ",days";
        } else if(isset($grouped) === false && isset($dateFormat)) {
            $dateFormatGroup = "group by days";
        } else if(isset($dateFormat) === false) {
            $dateFormatGroup = "";
        }
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $bindmarks = implode(",", array_fill(0, count($queueArr), "?"));
        $stmt = $pdoHandler->prepare(sprintf("select sum(case when tql.event = 'ENTERQUEUE' then 1 else 0 end) as entered_calls,
                                               sum(case when tql.event = 'CONNECT' then 1 else 0 end)    as answered_calls,
                                               sum(case when tql.event = 'ABANDON' then 1 else 0 end)    as abandoned_calls,
                                               sum(case when tql.event = 'BLINDTRANSFER' then 1 else 0 end) as out_transfered_calls,
                                               sum(case when tql.event = 'CONNECT' then cast(tql.data3 as unsigned) else 0 end) as ring_time,
                                               sum(case
                                                      when tql.event in ('COMPLETEAGENT', 'COMPLETECALLER') then cast(tql.data2 as unsigned integer)
                                                      when tql.event = 'BLINDTRANSFER' then cast(tql.data4 as unsigned integer ) else 0
                                                   end) as talk_time
                                               %s
                                               %s
                                        from db_asterisk.tbl_queue_log tql
                                        where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and
                                              tql.queuename in (%s) and
                                              tql.event in ('ENTERQUEUE', 'CONNECT', 'ABANDON', 'BLINDTRANSFER', 'COMPLETEAGENT', 'COMPLETECALLER') %s %s",
                                             (isset($grouped)) ? ",tql.queuename" : "", isset($dateFormat) ? sprintf(",date_format(tql.time, '%s') as days", $dateFormat) : "",
                                             $bindmarks,  (isset($grouped)) ? "group by tql.queuename": "", $dateFormatGroup));
        $index = 1;
//        logger($stmt->queryString);
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        foreach($queueArr as $queueName) {
            $stmt->bindValue($index++, $queueName, \PDO::PARAM_STR);
        }
        $stmt->execute();
        (isset($grouped)) ? $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC) : $resultSet = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $stmt = null;
        return $resultSet;
    }

    public static function getHoldTimeInQueue(array $queueArr, string $startDate, string $endDate):array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $bindMarks = implode(",", array_fill(0, count($queueArr), "?"));
        $stmt = $pdoHandler->prepare(sprintf("select max(cast(tql.data1 as unsigned integer)) as data1,
                                                       round(avg(cast(tql.data1 as unsigned integer)), 2) as data1_avg,
                                                       max(cast(tql.data3 as unsigned integer)) as data3,
                                                       round(avg(cast(tql.data3 as unsigned integer)), 2) as data3_avg,
                                                       sum(case when tql.event = 'CONNECT' then cast(tql.data1 as unsigned integer) else 0 end) as wait_time_connect,
                                                       sum(case when tql.event = 'ABANDON' then cast(tql.data3 as unsigned integer) else 0 end) as wait_time_abandon,
                                                       tql.event
                                                from db_asterisk.tbl_queue_log tql
                                                where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and
                                                      tql.queuename in (%s)  and
                                                      tql.event in ('CONNECT', 'ABANDON')
                                                group by tql.event", $bindMarks));
        $index = 1;
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        foreach($queueArr as $queueName) {
            $stmt->bindValue($index++, $queueName, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $resultSet = [];
        # when EVENT is CONNECT - maximum wait time - data1
        # when EVENT is ABANDON - maximum wait time - data3
        $stmt->fetchAll(\PDO::FETCH_FUNC, function($data1, $data1Avg, $data3, $data3Avg, $waitTimeConnect, $waitTimeAbandon, $event) use(&$resultSet) {
            if($event == "CONNECT") {
                $resultSet["პასუხამდე"] = [
                    "totalWaitTime" => intval($waitTimeConnect),
                    "maxWaitTime" => intval($data1),
                    "avgWaitTime" => floatval($data1Avg)
                ];
            } else if($event == "ABANDON") {
                $resultSet["გათიშვამდე"] = [
                    "totalWaitTime" => intval($waitTimeAbandon),
                    "maxWaitTime" => intval($data3),
                    "avgWaitTime" => floatval($data3Avg)
                ];
            }
        });
        $stmt = null;
        return $resultSet;
    }

    public static function getHoldTimeInQueueHourly(array $queueArr, string $startDate, string $endDate): array {
        $bindMarks = implode(",", array_fill(0, count($queueArr), "?"));
        /*** @var \PDO $pdoHandler*/
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select date_format(tql.time, '%%H') as hour,
                                               round(avg(case when tql.event = 'CONNECT' then cast(tql.data1 as unsigned integer) else null end), 2) as avg_connect_wait,
                                               round(avg(case when tql.event = 'ABANDON' then cast(tql.data3 as unsigned integer) else null end), 2) as avg_abandon_wait,
                                               round(avg(case when tql.event = 'CONNECT' then cast(tql.data1 as unsigned integer)
                                                        when tql.event = 'ABANDON' then cast(tql.data3 as unsigned integer) else null end), 2) as avg_total_wait,
                                               sum(case when tql.event = 'CONNECT' then 1 else 0 end) as connect_count,
                                               sum(case when tql.event = 'ABANDON' then 1 else 0 end) as abandon_count,
                                               round(max(case when tql.event = 'CONNECT' then cast(tql.data1 as unsigned integer) else null end), 2) as max_connect_wait,
                                               round(max(case when tql.event = 'ABANDON' then cast(tql.data3 as unsigned integer) else null end), 2) as max_abandon_wait
                                        from db_asterisk.tbl_queue_log tql
                                        where tql.time between  STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                          and tql.queuename in (%s)
                                          and tql.event in ('CONNECT', 'ABANDON')
                                        group by hour order by hour", $bindMarks));
        $index = 1;
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        foreach($queueArr as $queueName) {
            $stmt->bindValue($index++, $queueName, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }


    public static function getOutgoingTransfers(array $queueArr, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select (select tql2.queuename from db_asterisk.tbl_queue_log tql2 where tql2.time >= tql.time and tql2.event = 'ENTERQUEUE' and tql2.callid = tql.callid order by id asc limit 1) queueName,
                                               tql.data1
                                        from db_asterisk.tbl_queue_log tql
                                        where tql.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                          and tql.queuename = :queueName
                                          and tql.event = 'BLINDTRANSFER'");
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->bindParam(":queueName", $queueName, \PDO::PARAM_STR);
        $resultSet = [];
        foreach($queueArr as $queue) {
            $queueName = $queue;
            $stmt->execute();
            $tmpArr = [];
            while(($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
                if($row['queueName'] == null && in_array($row['data1'], self::$eligibleTransferCodes) === false) {
                    (isset($tmpArr['incorrectTransfer'])) ? $tmpArr['incorrectTransfer']++ : $tmpArr['incorrectTransfer'] = 1;
                } else if($row['queueName'] == null && $row['data1'] == self::$silkTransfer) {
                    (isset($tmpArr['Silknet'])) ? $tmpArr['Silknet']++ : $tmpArr['Silknet'] = 1;
                } else if($row['queueName'] == null && $row['data1'] == self::$silkCorpTransfer) {
                    (isset($tmpArr['SilknetCorp'])) ? $tmpArr['SilknetCorp']++ : $tmpArr['SilknetCorp'] = 1;
                }else if($row['queueName'] == null && array_key_exists($row['data1'], self::$codes_to_queues) ) {
                    $name = self::$codes_to_queues[$row['data1']];
                    (isset($tmpArr[$name])) ? $tmpArr[$name]++ : $tmpArr[$name] = 1;
                } else if($row['queueName'] == null && in_array($row['data1'], self::$eligibleTransferCodes) === true) {
                    (isset($tmpArr['droppedTransfers'])) ? $tmpArr['droppedTransfers']++ : $tmpArr['droppedTransfers'] = 1;
                } else {
                    (isset($tmpArr[$row['queueName']])) ? $tmpArr[$row['queueName']]++ : $tmpArr[$row['queueName']] = 1;
                }
            }
            $resultSet[$queueName] = $tmpArr;
        }
        $stmt = null;
        return $resultSet;
    }

    public static function getOutgoingTransfersToQueue(array $fromQueues, string $toQueue, string $startDate, string $endDate):array {
        $toQueue = strtolower($toQueue);
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select (select tql2.queuename from db_asterisk.tbl_queue_log tql2 where tql2.time >= tql.time and tql2.event = 'ENTERQUEUE' and tql2.callid = tql.callid order by id asc limit 1) queueName,
                                                       (select tql2.data2 from db_asterisk.tbl_queue_log tql2 where tql2.event = 'ENTERQUEUE' and tql2.callid = tql.callid order by id asc limit 1) caller,
                                                       tql.data1,tql.callid,tql.event,tql.time
                                                from db_asterisk.tbl_queue_log tql
                                                where tql.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                                  and tql.queuename = :queueName
                                                  and tql.data1 = :transferCode
                                                  and tql.event = 'BLINDTRANSFER'");
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->bindValue(":transferCode", self::$transferMapping[$toQueue]['codes'][0]);
        $stmt->bindParam(":queueName", $queueName, \PDO::PARAM_STR);
        $resultSet = [];
        foreach($fromQueues as $queue) {
            $queueName = $queue;
            $stmt->execute();
            $tmpArr = [];
            while(($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
                if($toQueue == "silknet") {
                    $row['queueName'] = "Silknet";
                } else if($toQueue == "silknetCorp") {
                    $row['queueName'] = "SilknetCorp";
                }
                $tmpArr[] = $row;
            }
            $resultSet[$queueName] = $tmpArr;
        }
        $stmt = null;
        return $resultSet;
    }

    public static function getOutgoingTransfersDetailedBySip(int $sipNumber, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select tql.time,tql.callid,tql.queuename,tql.agent,tql.data1 as combination,tql.data4 as calltime
                                                from db_asterisk.tbl_queue_log tql
                                                where tql.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                                  and tql.event like 'BLINDTRANSFER'
                                                  and tql.agent = :sip
                                                order by tql.id asc");
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->bindValue(":sip", sprintf("SIP/%s", $sipNumber), \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

    public static function getIncomingTransfers(array $queueArr, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select (select tql2.queuename from db_asterisk.tbl_queue_log tql2 where tql2.time >= tql.time and tql2.event = 'ENTERQUEUE' and tql2.callid = tql.callid and tql2.queuename = :queueName order by id asc limit 1) toQueue,
                                                tql.queuename fromQueue
                                                from db_asterisk.tbl_queue_log tql
                                                where tql.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                                  and tql.data1 = :transferCode
                                                  and tql.event = 'BLINDTRANSFER'");
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->bindParam(":transferCode", $transferCode, \PDO::PARAM_STR);
        $stmt->bindParam(":queueName", $queueName, \PDO::PARAM_STR);
        $resultSet = [];
        foreach($queueArr as $queue) {
            $queueName = $queue;
            $transferCode = self::$transferMapping[strtolower($queue)]['codes'][0];
            $stmt->execute();
            $resultSet[$queue] = [];
            while(($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
                if($row['toQueue'] != null) {
                    (isset($resultSet[$queue][$row['fromQueue']])) ? $resultSet[$queue][$row['fromQueue']]++ : $resultSet[$queue][$row['fromQueue']] = 1;
                }
            }
        }
        $stmt = null;
        return $resultSet;
    }

    public static function getIncomingTransfersFromQueue(array $queueArr, string $fromQueue, string $startDate, string $endDate):array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select
         (select tql2.queuename from db_asterisk.tbl_queue_log tql2 where tql2.time >= tql.time and tql2.event = 'ENTERQUEUE' and tql2.callid = tql.callid and tql2.queuename = :queueName order by id asc limit 1) toQueue,
         (select tql3.data2 from db_asterisk.tbl_queue_log tql3 where tql3.event = 'ENTERQUEUE' and tql3.callid = tql.callid order by id asc limit 1) caller,
         tql.queuename fromQueue,tql.callid,tql.time,tql.agent
        from db_asterisk.tbl_queue_log tql
        where tql.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
          and tql.queuename = :fromQueue
          and tql.event = 'BLINDTRANSFER'
          and tql.data1 = :transferCode");
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->bindValue(":fromQueue", $fromQueue, \PDO::PARAM_STR);
        $stmt->bindParam(":transferCode", $transferCode, \PDO::PARAM_STR);
        $stmt->bindParam(":queueName", $queueName, \PDO::PARAM_STR);
        $resultSet = [];
        foreach($queueArr as $queue) {
            $queueName = $queue;
            $transferCode = self::$transferMapping[strtolower($queue)]['codes'][0];
            $stmt->execute();
            $resultSet[$queue] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        $stmt = null;
        logger()->error($resultSet);
        return $resultSet;
    }

    public static function getTransfersBySips(int $sipNumber, ?array $queueArr, string $startDate, string $endDate): ?array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        if(isset($queueArr)) {
            $queueMarks = implode(",", array_fill(0, count($queueArr), "?"));
        }
        $stmt = $pdoHandler->prepare(sprintf("select tql.agent, tql.callid callID, tql.queuename queueName, tql.data4 callTime, tql.data1 as combination
                                            from db_asterisk.tbl_queue_log tql
                                            where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                              and tql.agent = ?
                                              and tql.event = 'BLINDTRANSFER'
                                              %s",
            (isset($queueArr) ? sprintf("and tql.queuename in (%s)", $queueMarks) : "")));
        $index = 1;
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, sprintf("SIP/%s", $sipNumber), \PDO::PARAM_STR);
        foreach($queueArr as $queuName) {
            $stmt->bindValue($index++, $queuName, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $stmt = null;
        return $resultSet;
    }

    public static function getB2bAndB2cStats(string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select count(1) calls, tql.queuename
                                                from db_asterisk.tbl_queue_log tql
                                                where tql.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s') and
                                                      tql.queuename in ('B2Cgeo','B2Bgeo','B2Crus','B2Brus','B2Ceng','B2Beng') and
                                                      tql.event = 'ENTERQUEUE'
                                                group by tql.queuename");
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

    public static function getStatsByCallerNumber(string $callerNum, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select tql.time  as enter_queue_time,
                                                       tql.queuename,
                                                       tql2.agent,
                                                       tql2.event as is_connect,
                                                       tql2.time as connect_time,
                                                       tql3.event as is_abandon,
                                                       tql3.time as abandon_time
                                                from db_asterisk.tbl_queue_log tql
                                                       left join db_asterisk.tbl_queue_log tql2 on tql.callid = tql2.callid and
                                                                                                   tql2.event = 'CONNECT' and
                                                                                                   tql2.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                                       left join db_asterisk.tbl_queue_log tql3 on tql.callid = tql3.callid and
                                                                                                   tql3.event = 'ABANDON' and
                                                                                                   tql3.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                                where tql.event = 'ENTERQUEUE' and
                                                      tql.data2 = :callerNum and
                                                      tql.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')");
        $stmt->bindValue(":callerNum", $callerNum, \PDO::PARAM_STR);
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

    public static function getRepeatedCallsByQueue(array $queueArr, string $startDate, string $endDate, int $counter): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $bindMarks = implode(",", array_fill(0, count($queueArr), "?"));
        $stmt = $pdoHandler->prepare(sprintf("select x.counter, x.data2, x.event
                                                from (
                                                select count(1) as counter,tql.data2, tql2.event,  sum(count(1)) over (PARTITION BY tql.data2) as total_counter
                                                from db_asterisk.tbl_queue_log tql
                                                inner join db_asterisk.tbl_queue_log tql2 on tql.callid = tql2.callid and
                                                                                             tql2.queuename in (%s) and
                                                                                             tql2.event in ('CONNECT', 'ABANDON')
                                                where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and
                                                      tql.event = 'ENTERQUEUE' and
                                                      tql.queuename in (%s)
                                                group by tql.data2,tql2.event
                                                order by tql.data2,tql.event) x
                                                where x.total_counter > ?", $bindMarks, $bindMarks));
        $index = 1;
        foreach($queueArr as $queueName) {
            $stmt->bindValue($index++, $queueName, \PDO::PARAM_STR);
        }
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        foreach($queueArr as $queueName) {
            $stmt->bindValue($index++, $queueName, \PDO::PARAM_STR);
        }
        $stmt->bindValue($index++, $counter, \PDO::PARAM_INT);
        $stmt->execute();
        $resultSet = [
            'totalCounter' => 0,
            'connectCounter' => 0,
            'abandonCounter' => 0
        ];
        $stmt->fetchAll(\PDO::FETCH_FUNC, function($counter, $callerNum, $event) use (&$resultSet) {
            $resultSet[$callerNum][$event] = $counter;
            $resultSet['totalCounter'] += $counter;
            if($event == 'CONNECT') {
                $resultSet['connectCounter'] += $counter;
            } else {
                $resultSet['abandonCounter'] += $counter;
            }
        });
        return $resultSet;
    }

    public static function getCallTimeByQueue(array $queueArr, int $callTime, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $bindMarks = implode(",", array_fill(0, count($queueArr), "?"));
        $stmt = $pdoHandler->prepare(sprintf("select sum(case when tql.event = 'ABANDON' and cast(tql.data3 as unsigned integer) > ? then 1 else 0 end) as abandon_counter_gt,
                                                       sum(case when tql.event = 'COMPLETECALLER' and cast(tql.data1 as unsigned integer) > ? then 1
                                                            when tql.event = 'COMPLETEAGENT' and cast(tql.data1 as unsigned integer) > ? then 1
                                                            when tql.event = 'BLINDTRANSFER' and cast(tql.data3 as unsigned integer) > ? then 1
                                                         else 0
                                                       end) as connect_counter_gt,
                                                       sum(case when tql.event = 'ABANDON' and cast(tql.data3 as unsigned integer) < ? then 1 else 0 end) as abandon_counter_ls,
                                                       sum(case when tql.event = 'COMPLETECALLER' and cast(tql.data1 as unsigned integer) < ? then 1
                                                            when tql.event = 'COMPLETEAGENT' and cast(tql.data1 as unsigned integer) < ? then 1
                                                            when tql.event = 'BLINDTRANSFER' and cast(tql.data3 as unsigned integer) < ? then 1
                                                         else 0
                                                       end) as connect_counter_ls,
                                                       sum(case when tql.event = 'ABANDON' and cast(tql.data3 as unsigned integer) = ? then 1 else 0 end) as abandon_counter_eq,
                                                       sum(case when tql.event = 'COMPLETECALLER' and cast(tql.data1 as unsigned integer) = ? then 1
                                                            when tql.event = 'COMPLETEAGENT' and cast(tql.data1 as unsigned integer) = ? then 1
                                                            when tql.event = 'BLINDTRANSFER' and cast(tql.data3 as unsigned integer) = ? then 1
                                                         else 0
                                                       end) as connect_counter_eq
                                                from db_asterisk.tbl_queue_log tql
                                                where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                  and tql.queuename in (%s)
                                                  and tql.event in ('ABANDON', 'COMPLETECALLER', 'COMPLETEAGENT', 'BLINDTRANSFER')", $bindMarks));
        # binding for CallTime
        $index = 1;
        for(; $index <= 12; $index++) {
            $stmt->bindValue($index, $callTime, \PDO::PARAM_INT);
        }
        ############
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        foreach($queueArr as $queueName) {
            $stmt->bindValue($index++, $queueName, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

    public static function getHourlyQueueStats(array $queueArr, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $bindMarks = implode(",", array_fill(0, count($queueArr), "?"));
        $stmt = $pdoHandler->prepare(sprintf("select date_format(tql.time, '%%H') as time,
                                               round(avg(case
                                                      when tql.event in ('COMPLETEAGENT', 'COMPLETECALLER') then cast(tql.data2 as unsigned integer)
                                                      when tql.event = 'BLINDTRANSFER' then cast(tql.data4 as unsigned integer ) else null
                                                   end), 2) as avg_call_time,
                                               round(avg(case when tql.event = 'ABANDON' then cast(tql.data3 as unsigned integer) else null end), 2) as avg_abandon_wait_time,
                                               sum(case when tql.event = 'CONNECT' then 1 else 0 end) as connect_count,
                                               sum(case when tql.event = 'ABANDON' then 1 else 0 end) as abandon_count,
                                               sum(case when tql.event = 'ENTERQUEUE' then 1 else 0 end) as enter_count,
                                               IF(count(distinct tql.agent) = 0, 0, count(distinct tql.agent) - 1) as active_operators
                                        from db_asterisk.tbl_queue_log tql
                                        where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                          and tql.queuename in (%s)
                                          and tql.event in ('CONNECT', 'COMPLETECALLER', 'COMPLETEAGENT', 'BLINDTRANSFER', 'ABANDON', 'ENTERQUEUE')
                                        group by date_format(tql.time, '%%H')", $bindMarks));
        $index = 1;
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        foreach($queueArr as $queueName) {
            $stmt->bindValue($index++, $queueName, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

    public static function getOngoingCallStats(array $sipArr): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select tql.time,tql.event,tql.data1,tql.data2,tql.data3,tql.queuename
                                                    from db_asterisk.tbl_queue_log tql
                                                    where tql.callid = (select tql2.callid
                                                                        from db_asterisk.tbl_queue_log tql2
                                                                        where tql2.time > STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s')
                                                                          and tql2.event <> 'RINGNOANSWER'
                                                                          and not tql2.callid='NONE'
                                                                          and tql2.agent = :sip
                                                                        order by tql2.time desc limit 1)
                                                      and tql.event in ('CONNECT', 'INFO', 'COMPLETECALLER', 'COMPLETEAGENT', 'BLINDTRANSFER')
                                                    order by tql.time desc");
        $stmt->bindValue(":startDate", date("Y-m-d H:i:s", time() - 3600));
        $stmt->bindParam(":sip", $sip, \PDO::PARAM_STR);
        $ongoingCalls = [];
        foreach($sipArr as $sipNumber) {
            $sip = sprintf("SIP/%s", $sipNumber);
            $stmt->execute();
            $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if(count($resultSet) === 0) {
                continue;
            }
            if($resultSet[0]["event"] != "CONNECT") {
                continue;
            }
            foreach($resultSet as $row) {
                switch($row["event"]) {
                    case "CONNECT":
                        $ongoingCalls[$sipNumber]["time"] = time() - strtotime($row["time"]);
                        $ongoingCalls[$sipNumber]["timeBeforeAnswer"] = $row["data3"];
                        $ongoingCalls[$sipNumber]["inQueueTime"] = $row["data1"];
                        $ongoingCalls[$sipNumber]['queueName'] = $row['queuename'];
                        break;
                    case "INFO":
                        $ongoingCalls[$sipNumber]["tag"] = $row["data2"];
                        break;
                }
            }
        }
        $stmt = null;
        return $ongoingCalls;
    }

    public static function getRecallsAfterAbandon(array $queueArr, string $startDate, string $endDate) {
        /*** @var \PDO $pdoHandler*/
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $bindMarks = implode(",", array_fill(0, count($queueArr), "?"));
        $stmt = $pdoHandler->prepare(sprintf("with QUEUE_FULL_EXT as
                                                    (select *
                                                        from db_asterisk.tbl_queue_log
                                                       where queuename in (%s)
                                                         AND TIME BETWEEN
                                                             str_to_date(?, '%%Y-%%m-%%d %%H:%%i:%%s') AND
                                                             STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                         and EVENT in ('ENTERQUEUE', 'ABANDON', 'CONNECT'))
                                                    select count(NUM_ABANDON_1) as count
                                                      from (SELECT DISTINCT ID_ABANDON_1,ABTIME1,QUEUE_ABANDON,NUM_ABANDON_1,EVENT_ABANDON_1,ID_ABANDON_2,ABTIME2,EVENT_ABANDON_2
                                                             FROM (SELECT callid AS ID_ABANDON_1,TIME as ABTIME1,queuename as QUEUE_ABANDON,data2 as NUM_ABANDON_1,EVENT as EVENT_ABANDON_1
                                                                      FROM QUEUE_FULL_EXT
                                                                     WHERE EVENT = 'ENTERQUEUE') abandon1
                                                             INNER JOIN (SELECT callid AS ID_ABANDON_2,TIME as ABTIME2,EVENT as EVENT_ABANDON_2
                                                                          FROM QUEUE_FULL_EXT
                                                                         WHERE EVENT = 'ABANDON') abandon2
                                                                ON abandon1.ID_ABANDON_1 = abandon2.ID_ABANDON_2) tb1
                                                    inner join (SELECT DISTINCT ID_CONNECT_1,CONNTIME1,QUEUE_CONNECT,NUM_CONNECT_1,EVENT_CONNECT_1,ID_CONNECT_2,CONNTIME2,EVENT_CONNECT_2,AGENT2
                                                                   FROM (SELECT callid AS ID_CONNECT_1,
                                                                                TIME as CONNTIME1,
                                                                                queuename as QUEUE_CONNECT,
                                                                                data2 as NUM_CONNECT_1,
                                                                                EVENT as EVENT_CONNECT_1
                                                                           FROM QUEUE_FULL_EXT
                                                                          WHERE EVENT = 'ENTERQUEUE') connect1
                                                                  INNER JOIN (SELECT callid AS ID_CONNECT_2,
                                                                                    TIME as CONNTIME2,
                                                                                    EVENT as EVENT_CONNECT_2,
                                                                                    AGENT as AGENT2
                                                                               FROM QUEUE_FULL_EXT
                                                                              WHERE EVENT = 'CONNECT') connect2
                                                                     ON connect1.ID_CONNECT_1 = connect2.ID_CONNECT_2) tb2
                                                        on tb1.NUM_ABANDON_1 = tb2.NUM_CONNECT_1
                                                       AND date_format(ABTIME2, '%%d') = date_format(CONNTIME2, '%%d')", $bindMarks));
        $index = 1;
        foreach($queueArr as $queueName) {
            $stmt->bindValue($index++, $queueName, \PDO::PARAM_STR);
        }
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $count = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $count;
    }

    public static function getCallsByPrefixes(array $queueArr, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $bindMarks = implode(",", array_fill(0, count($queueArr), "?"));
        $stmt = $pdoHandler->prepare(sprintf("select tql.event,(select data2 from db_asterisk.tbl_queue_log tql2 where tql.callid = tql2.callid and tql2.event = 'ENTERQUEUE' limit 1) caller
                                                    from db_asterisk.tbl_queue_log tql
                                                    where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                      and tql.queuename in (%s)
                                                      and tql.event in ('CONNECT', 'ABANDON')", $bindMarks));
        $index = 1;
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        foreach($queueArr as $queueName) {
            $stmt->bindValue($index++, $queueName, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $resultSet = [
            'city' => [
                'total' => 0,
                'answer' => 0,
                'abandon' => 0
            ],
            'region' => [
                'total' => 0,
                'answer' => 0,
                'abandon' => 0
            ],
            'mobile' => [
                'total' => 0,
                'answer' => 0,
                'abandon' => 0
            ],
            'other' => [
                'total' => 0,
                'answer' => 0,
                'abandon' => 0
            ]
        ];
        $stmt->fetchAll(\PDO::FETCH_FUNC, function($event, $callerNum) use(&$resultSet) {
            $type = self::getCallTypeByPrefix($callerNum);
            $resultSet[$type]['total']++;
            if($event == "CONNECT") {
                $resultSet[$type]['answer']++;
            } else {
                $resultSet[$type]['abandon']++;
            }
        });
        return $resultSet;
    }

    public static function getLastPauseStatus($sipNumber):array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select tql.id,tql.event,tql.data1
                                                from db_asterisk.tbl_queue_log tql
                                                where tql.event in ('PAUSE', 'UNPAUSE')
                                                  and tql.agent = :sipNumber
                                                order by tql.time desc limit 1");
        $stmt->bindValue(":sipNumber", sprintf("SIP/%s", $sipNumber), \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if($row === false) {
            $row['event'] = "UNPAUSE";
            $row['data1'] = "";
        }
        $stmt = null;
        return $row;
    }

    public static function getPauseLastStatusBulk(array $sipArr, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $bindMarks = implode(',', array_fill(0, count($sipArr), '?'));
        $stmt = $pdoHandler->prepare(sprintf("with
                                                     filtered_table
                                                       as (select max(tql.id) as max_id,right(tql.agent, 3) agent
                                                           from db_asterisk.tbl_queue_log tql
                                                            where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                             and tql.event in ('PAUSE', 'UNPAUSE')
                                                             and tql.agent in (%s)
                                                           group by tql.agent)
                                                select ft.agent,tql2.event,tql2.data1
                                                from filtered_table ft
                                                inner join db_asterisk.tbl_queue_log tql2 on tql2.id = ft.max_id", $bindMarks));
        $stmt->bindValue(1, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(2, $endDate, \PDO::PARAM_STR);
        $index = 3;
        foreach($sipArr as $sipNumber) {
            $stmt->bindValue($index, sprintf("SIP/%s", $sipNumber), \PDO::PARAM_STR);
            $index++;
        }
        $stmt->execute();
        $resultSet = [];
        $stmt->fetchAll(\PDO::FETCH_FUNC, function($sipNumber, $event, $data1) use (&$resultSet) {
            $resultSet[$sipNumber] = [
                "event" => $event,
                "reason" => $data1,
            ];
        });
        return $resultSet;
    }

    public static function getPauseStatusDetailedV3(int $sipNumber, string $queueName, string $startDate, string $endDate, $onlyTime=false) {
        static $pdoHandler = null;
        static $stmt = null;
        /**
         * @var \PDO $pdoHandler
         */
        if(isset($pdoHandler) === false) {
            $pdoHandler = DB::connection(self::$connName)->getPdo();
            $stmt = $pdoHandler->prepare(sprintf("select tql.id,tql.time,tql.agent,tql.event,tql.data1 as pauseReason
                                                from db_asterisk.tbl_queue_log tql use index (tbl_queue_log_time_index)
                                                where tql.time between STR_TO_DATE(:startDate, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(:endDate, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                  and tql.agent = :sipNumber
                                                  and tql.event in ('%s', '%s')
                                                  and tql.queuename = :queueName
                                                order by tql.id asc", config('asterisk.PAUSE_EVENT'), config('asterisk.UNPAUSE_EVENT')));
        }
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->bindValue(":queueName", $queueName, \PDO::PARAM_STR);
        $stmt->bindValue(":sipNumber", sprintf("SIP/%s", $sipNumber), \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = [
            'workTime' => 0,
            'lunchTime' => 0,
            'lunchCounter' => 0,
            'teaBreakTime' => 0,
            'teaBreakCounter' => 0,
            'FAQTime' => 0,
            'FAQCounter' => 0,
            'pcTime' => 0,
            'pcCounter' => 0,
            'managerTime' => 0,
            'managerCounter' => 0,
            'meetingTime' => 0,
            'meetingCounter' => 0,
            'trainingTime' => 0,
            'trainingCounter' => 0,
            'newComerTime' => 0,
            'newComerCounter' => 0,
            'b2bActionTime' => 0,
            'b2bActionCounter' => 0,
            'breakCounter' => 0,
            'lastID' => 0,
        ];
        $firstEvent = true;
        $lastEvent = null;
        $lastEventReason = null;
        $lastEventDate = null;
//        $lastID = 0;
        while(($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
            if(!$onlyTime) $resultSet[] = $row;
            $eventDuration = 0;
            # pirveli eventi gadmocemuli drois intervalistvis
            if($firstEvent) {
                $firstEvent = false;
                # tu eventamde bolo login aris startDateze naklebi nishanvs ro EVENT da startDate ert cvlashi arian
                $login = SipStatusLog::getLastStatusBeforeDate($sipNumber, config('asterisk.REGISTER'), $row['time']);
                if(!isset($login)) {
                    throw new \RuntimeException(sprintf("No Sip status in DB! Sip: %s, %s, %s", $sipNumber, config('asterisk.REGISTER'), $row['time']));
                }
//                logger()->error("Login Date before first Event: $login");
                # startDate aris igive cvlashi romelshic eventi
                if($login <= $startDate) {
                    $eventDuration += strtotime($row['time']) - strtotime($startDate);
                } else if($login > $startDate) {
                    $eventDuration += self::getTimeBetweenStatuses($eventDuration, $sipNumber, $startDate, $login);
//                    logger()->error("ABA VNAXOT: $eventDuration");
                    $eventDuration += strtotime($row['time']) - strtotime($login);
//                    logger()->error($eventDuration);
                }
            }
            else {
                $login = SipStatusLog::getLastStatusBeforeDate($sipNumber, config('asterisk.REGISTER'), $row['time']);
                if(!isset($login)) {
                    throw new \RuntimeException(sprintf("No Sip status in DB! Sip: %s, %s, %s", $sipNumber, config('asterisk.REGISTER'), $row['time']));
                }
                # wina eventi igive cvlashi iyo !
                if($login <= $lastEventDate) {
                    $eventDuration += strtotime($row['time']) - strtotime($lastEventDate);
                } else if($login > $lastEventDate) {
//                    logger()->error("Login metia last Eventze: {$row['event']}, {$row['time']}, {$row['pauseReason']}, LastEvent: $lastEventDate");
//                    logger()->error("Pirveli login eventade: $login");
                    $eventDuration += self::getTimeBetweenStatuses($eventDuration, $sipNumber, $lastEventDate, $login);
//                    logger()->error("Statusebis jami: $eventDuration");
                    $eventDuration += strtotime($row['time']) - strtotime($login);
//                    logger()->error("Mtliani jami: $eventDuration");
                } else {
//                    logger()->error(sprintf("Login: $login, Event: $lastEventDate"));
                    throw new \RuntimeException("Unhandled case in Pause calculation! $sipNumber");
                }
            }
            if($row['event'] == config('asterisk.PAUSE_EVENT')) {
                $resultSet['workTime'] += $eventDuration;
            } else if($row['event'] == config('asterisk.UNPAUSE_EVENT')) {
                $lastEventData = self::getLastEventByType($sipNumber, [config('asterisk.PAUSE_EVENT')], $queueName, $row['time']);
                if(!isset($lastEventData)) {
                    throw new \RuntimeException("Can't find Pause pair for Unpause event!");
                }
                if($lastEventData['pauseReason'] == "TEA BREAK") {
                    $resultSet['teaBreakTime'] += $eventDuration;
                    $resultSet['teaBreakCounter']++;
                } else if($lastEventData['pauseReason'] == "LUNCH") {
                    $resultSet['lunchTime'] += $eventDuration;
                    $resultSet['lunchCounter']++;
                }
                else if($lastEventData['pauseReason'] == "FAQ") {
                    $resultSet['FAQTime'] += $eventDuration;
                    $resultSet['FAQCounter']++;
                }
                else {
                    $resultSet['workTime'] += $eventDuration;
                    switch($lastEventData['pauseReason']) {
                        case "PC Problem":
                            $resultSet['pcTime'] += $eventDuration;
                            $resultSet['pcCounter']++;
                            break;
                        case "Meeting":
                            $resultSet['meetingTime'] += $eventDuration;
                            $resultSet['meetingCounter']++;
                            break;
                        case "Manager":
                            $resultSet['managerTime'] += $eventDuration;
                            $resultSet['managerCounter']++;
                            break;
                        case "Training":
                            $resultSet['trainingTime'] += $eventDuration;
                            $resultSet['trainingCounter']++;
                            break;
                        case "Newcomer's training":
                            $resultSet['newComerTime'] += $eventDuration;
                            $resultSet['newComerCounter']++;
                            break;
                        case "B2B Action":
                            $resultSet['b2bActionTime'] += $eventDuration;
                            $resultSet['b2bActionCounter']++;
                            break;
                    }
                }
                $resultSet['breakCounter']++;
            } else {
                throw new \RuntimeException("Incorrect Event: {$row['event']}");
            }
            $lastEvent = $row['event'];
            $lastEventReason = $row['pauseReason'];
            $lastEventDate = $row['time'];
            $lastID = $row['id'];
        }
//        logger()->error("Work: {$resultSet['workTime']}");
        $eventDuration = 0;
        if(isset($lastEvent)) {
            $currTime = time();
            $logout = SipStatusLog::getFirstStatusAfterDate($sipNumber, config('asterisk.UNREGISTER'), $lastEventDate);
//            if(!isset($logout)) {
//                throw new \RuntimeException(sprintf("No Sip status in DB! Sip: %s, %s, %s", $sipNumber, config('asterisk.REGISTER'), $row['time']));
//            }
            $minTime = min([
                strtotime($logout ?? $endDate),
                strtotime($endDate),
                $currTime
            ]);
//            logger()->error("SIP: $sipNumber, currentTime: $currTime, logout: $logout, endDate: $endDate, Event: $lastEventDate");
//            logger()->error("MinTime: ".date("Y-m-d H:i:s", $minTime));
            if(isset($logout) and $minTime == strtotime($logout)) {
                $eventDuration += self::getTimeBetweenStatuses($eventDuration, $sipNumber, $logout, $endDate);
//                logger()->error("Status dur: $eventDuration");
            }
            $eventDuration += $minTime - strtotime($lastEventDate);
//            logger()->error("Worktimeze dassamatebeli: $eventDuration");

            $resultSet['lastID'] = $lastID;
        } else {
            $lastEventData = self::getLastEventByType($sipNumber, [config('asterisk.PAUSE_EVENT'), config('asterisk.UNPAUSE_EVENT')], $queueName, $startDate);
            $lastEvent = $lastEventData['event'];
            $lastEventReason = $lastEventData['pauseReason'] ?? null;
            $eventDuration += self::getTimeBetweenStatuses($eventDuration, $sipNumber, $startDate, $endDate);
        }
        if(isset($lastEvent)) {
            if($lastEvent == config('asterisk.PAUSE_EVENT')) {
                if($lastEventReason == "TEA BREAK") {
                    $resultSet['teaBreakTime'] += $eventDuration;
                    $resultSet['teaBreakCounter']++;
                } else if($lastEventReason == "LUNCH") {
                    $resultSet['lunchTime'] += $eventDuration;
                    $resultSet['lunchCounter']++;
                } else if($lastEventReason == "FAQ") {
                    $resultSet['FAQTime'] += $eventDuration;
                    $resultSet['FAQCounter']++;
                }
                else {
                    $resultSet['workTime'] += $eventDuration;
                    switch($lastEventReason) {
                        case "PC Problem":
                            $resultSet['pcTime'] += $eventDuration;
                            $resultSet['pcCounter']++;
                            break;
                        case "Meeting":
                            $resultSet['meetingTime'] += $eventDuration;
                            $resultSet['meetingCounter']++;
                            break;
                        case "Manager":
                            $resultSet['managerTime'] += $eventDuration;
                            $resultSet['managerCounter']++;
                            break;
                        case "Training":
                            $resultSet['trainingTime'] += $eventDuration;
                            $resultSet['trainingCounter']++;
                            break;
                        case "Newcomer's training":
                            $resultSet['newComerTime'] += $eventDuration;
                            $resultSet['newComerCounter']++;
                            break;
                        case "B2B Action":
                            $resultSet['b2bActionTime'] += $eventDuration;
                            $resultSet['b2bActionCounter']++;
                            break;
                    }
                }
                $resultSet['breakCounter']++;
            } else if($lastEvent == config('asterisk.UNPAUSE_EVENT')) {
                $resultSet['workTime'] += $eventDuration;
            } else {
                throw new \RuntimeException("Incorrect Event: {$row['event']}");

            }
        } else {
            # es nishnavs rom startDateamde sipistvis arcerti eventi ar arsebobs.
            $resultSet['workTime'] += $eventDuration;
        }
//        $resultSet['breakCounter'] = $breakCounter;
        return $resultSet;
    }


    private static function getTimeBetweenStatuses(int $eventDuration, int $sipNumber, string $startDate, string $endDate): ?int {
//        logger()->error("INPUT: start: $startDate, end: $endDate");
        $sipStatusArr = SipStatusLog::getSipLogins([$sipNumber], $startDate, $endDate)[$sipNumber];
        $firstRow = true;
        $lastStatusDate = $lastStatus = null;
        if(count($sipStatusArr) == 1 and $sipStatusArr[0]['status'] == config('asterisk.REGISTER')) {
            $currTime = time();
            $eventDuration += (($currTime > strtotime($endDate)) ? strtotime($endDate) : $currTime) - strtotime($sipStatusArr[0]['time']);
            return $eventDuration;
        }
        foreach($sipStatusArr as $sipStatus) {
            if($firstRow) {
                $firstRow = false;
                if($sipStatus['status'] == config('asterisk.UNREGISTER')) {
                    $eventDuration += strtotime($sipStatus['time']) - strtotime($startDate);
                }
                $lastStatusDate = $sipStatus['time'];
                $lastStatus = $sipStatus['status'];
                continue;
            }
            if($sipStatus['status'] == config('asterisk.UNREGISTER')) {
                if($lastStatus != config('asterisk.REGISTER')) {
                    throw new \RuntimeException("Incorrect Sips statuses in DB! Sip: $sipNumber, Date: {$sipStatus['time']}");
                }
                $eventDuration += strtotime($sipStatus['time']) - strtotime($lastStatusDate);
            }
            $lastStatus = $sipStatus['status'];
            $lastStatusDate = $sipStatus['time'];
        }
        return $eventDuration;
    }

    public static function getLastEventByType(int $sipNumber, array $events, string $queuName, string $date): ?array {
        /**
         * @var \PDO $pdoHandler
         */
        $bindMarks = implode(",", array_fill(0, count($events), "?"));
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select tql.time, tql.data1 as pauseReason, tql.event
                                                from db_asterisk.tbl_queue_log tql
                                                where tql.id = (select max(tql2.id)
                                                                from db_asterisk.tbl_queue_log tql2
                                                                where tql2.time < STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                                  and tql2.agent = ?
                                                                  and tql2.event in (%s)
                                                                  and tql2.queuename = ?
                                                                  and id>(select min(id) from db_asterisk.tbl_queue_log where time > DATE_SUB(now(), INTERVAL 1 week)))", $bindMarks));
        $index = 1;
        $stmt->bindValue($index++, $date, \PDO::PARAM_STR);
        $stmt->bindValue($index++, sprintf("SIP/%s", $sipNumber), \PDO::PARAM_STR);
        foreach($events as $event) {
            $stmt->bindValue($index++, $event, \PDO::PARAM_STR);
        }
        $stmt->bindValue($index, $queuName, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt = null;
        return ($row === false) ? null : $row;
    }

    public static function updatePauseReason(int $rowID, $newReason): bool {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $selectStmt = $pdoHandler->prepare("select tql.event,tql.data as is_edited, tql.data1 as reason, tql.data2 as old_reason, tql.agent, tql.time
                                                from db_asterisk.tbl_queue_log tql
                                                where tql.id = :rowID");
        $selectStmt->bindValue(":rowID", $rowID, \PDO::PARAM_INT);
        $selectStmt->execute();
        $row = $selectStmt->fetch(\PDO::FETCH_ASSOC);
        $selectStmt = null;
        if($row === false) throw new \RuntimeException("Record with this given ID doesnt exist!");
        if($row['event'] != 'PAUSE') throw new \RuntimeException("Trying to edit non PAUSE Event!!!");
        if($row['is_edited'] == "YES") throw new \RuntimeException("პაუზის ცვლილება ერთხელ არის დასაშვები!!!");
        $updateStmt = $pdoHandler->prepare("update db_asterisk.tbl_queue_log tql
                                                    set tql.data1 = :newReason,
                                                        tql.data2 = :oldReason,
                                                        tql.data  = 'YES'
                                                    where tql.time = STR_TO_DATE(:eventTime, '%Y-%m-%d %H:%i:%s')
                                                      and tql.event = 'PAUSE'
                                                      and tql.agent = :sipNumber");
        $updateStmt->bindValue(":newReason", $newReason, \PDO::PARAM_STR);
        $updateStmt->bindValue(":oldReason", $row['reason'], \PDO::PARAM_STR);
        $updateStmt->bindValue(":sipNumber", $row['agent'], \PDO::PARAM_STR);
        $updateStmt->bindValue(":eventTime", $row['time'], \PDO::PARAM_STR);
        $updateStmt->execute();
        $updateStmt = null;
        return true;
    }

    public static function getCallsWithCRR($sip, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $isSip = isset($sip) ? true : false;
        $stmt = $pdoHandler->prepare(sprintf("select tql.id,tql.callid,tql.time,tql.queuename,
                                               (select tmp.data2
                                               from db_asterisk.tbl_queue_log tmp
                                               where tmp.callid = tql.callid and tmp.event = 'INFO'
                                                 and tmp.time between STR_TO_DATE(:startDate, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(:endDate, '%%Y-%%m-%%d %%H:%%i:%%s') limit 1) as type,
                                               (select tmp2.data2
                                               from db_asterisk.tbl_queue_log tmp2
                                               where tmp2.callid = tql.callid and tmp2.event = 'ENTERQUEUE'
                                                 and tmp2.time between STR_TO_DATE(:startDate, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(:endDate, '%%Y-%%m-%%d %%H:%%i:%%s') limit 1) as caller,
                                               crr_reasons.reason,
                                               crr_reasons.id as reasonID,
                                               crr.status,
                                               crr_sug.suggestion,
                                               crr_sug.id as suggestionID,
                                               crr.number,
                                               crr.skill,
                                               crr.language,
                                               crr.real_number,
                                               crr.comment
                                        from db_asterisk.tbl_queue_log tql
                                        left join db_asterisk.CRR_V2 crr on crr.queue_log_id = tql.id
                                        left join db_asterisk.CRR_reasons crr_reasons on crr_reasons.id = crr.reason
                                        left join db_asterisk.CRR_suggestions crr_sug on crr_sug.id = crr.suggestion
                                        where tql.event = ('CONNECT')
                                          and tql.time between STR_TO_DATE(:startDate, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(:endDate, '%%Y-%%m-%%d %%H:%%i:%%s')
                                          %s", ($isSip) ? "and tql.agent = :sipNumber" : ""));
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        if($isSip) $stmt->bindValue(":sipNumber", sprintf("SIP/%s", $sip), \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

    public static function getCallsWithCRRByCaller(string $caller, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select x.id,x.callid,x.time,x.queuename,x.caller,x.type,x.reason,x.status,x.suggestion,x.number
                                                from (
                                                  select tql.id,tql.callid,tql.time,tql.queuename,
                                                         (select tmp.data2
                                                         from db_asterisk.tbl_queue_log tmp
                                                         where tmp.callid = tql.callid and tmp.event = 'INFO'
                                                           and tmp.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s') limit 1) as type,
                                                         (select tmp2.data2
                                                         from db_asterisk.tbl_queue_log tmp2
                                                         where tmp2.callid = tql.callid and tmp2.event = 'ENTERQUEUE'
                                                           and tmp2.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s') limit 1) as caller,
                                                         crr_reasons.reason,
                                                         crr.status,
                                                         crr_sug.suggestion,
                                                         crr.number,
                                                         crr.skill,
                                                         crr.language,
                                                         crr.real_number,
                                                         crr.comment
                                                  from db_asterisk.tbl_queue_log tql
                                                  left join db_asterisk.CRR_V2 crr on crr.queue_log_id = tql.id
                                                  left join db_asterisk.CRR_reasons crr_reasons on crr_reasons.id = crr.reason
                                                  left join db_asterisk.CRR_suggestions crr_sug on crr_sug.id = crr.suggestion
                                                  where tql.event = ('CONNECT')
                                                    and tql.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                                ) x
                                                where x.caller = :caller");
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->bindValue(":caller", $caller, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::PARAM_STR);
        $stmt = null;
        return $resultSet;
    }

    public static function getLastOrOngoingCall(int $sip): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select tql.id,
                                                       tql.callid,
                                                       tql.time,
                                                       tql.event,
                                                       tql.data1,
                                                       tql.data2,
                                                       tql.data3,
                                                       tql.data4,
                                                       tql.queuename,
                                                       crr_reasons.reason,
                                                       crr_reasons.id as reasonID,
                                                       crr_sug.suggestion,
                                                       crr_sug.id as suggestionID,
                                                       crr.number,
                                                       crr.status
                                                    from db_asterisk.tbl_queue_log tql
                                                    left join db_asterisk.CRR_V2 crr on tql.id = crr.queue_log_id and
                                                                                     crr.inserted > STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s')
                                                    left join db_asterisk.CRR_reasons crr_reasons on crr_reasons.id = crr.reason
                                                    left join db_asterisk.CRR_suggestions crr_sug on crr_sug.id = crr.suggestion
                                                    where tql.callid = (select tql2.callid
                                                                        from db_asterisk.tbl_queue_log tql2
                                                                        where tql2.time > STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s')
                                                                          and tql2.event <> 'RINGNOANSWER'
                                                                          and tql2.agent = :sip
                                                                        order by tql2.time desc limit 1)
                                                      and tql.event in ('CONNECT', 'INFO', 'COMPLETECALLER', 'COMPLETEAGENT', 'BLINDTRANSFER', 'ENTERQUEUE')
                                                    order by tql.time desc");
        $stmt->bindValue(":startDate", date("Y-m-d H:i:s", time() - 3600));
        $stmt->bindValue(":sip", sprintf("SIP/%s", $sip), \PDO::PARAM_STR);
        $stmt->execute();
        $callData = [];
        $firstRow = true;
        $live = false;
        while(($row = $stmt->fetch(\PDO::FETCH_ASSOC)) != false) {
            if($firstRow) {
                $firstRow = false;
                if($row['event'] == "CONNECT") {
                    $live = true;
                    $callData["time"] = time() - strtotime($row["time"]);
                }
            }
            if($row['event'] == "CONNECT") {
                $callData['status'] = $row['status'];
                $callData['reason'] = $row['reason'];
                $callData['reasonID'] = $row['reasonID'];
                $callData['suggestion'] = $row['suggestion'];
                $callData['suggestionID'] = $row['suggestionID'];
                $callData['number'] = $row['number'];
                $callData['uniqueID'] = $row['callid'];
                $callData['CRRUniqueID'] = $row['id'];
                $callData['timeBeforeAnswer'] = $row['data3'];
                $callData['inQueueTime'] = $row['data1'];
                $callData['queuename'] = $row['queuename'];
                $callData['startTime'] = $row['time'];
            } else if($row['event'] == "COMPLETEAGENT" or $row['event'] == "COMPLETECALLER") {
                $callData['callDuration'] = $row['data2'];
            } else if($row['event'] == "BLINDTRANSFER") {
                $callData["transferCode"] = $row['data1'];
                $callData['callDuration'] = $row['data4'];
            } else if($row['event'] == "INFO") {
                $callData['tag'] = $row['data2'];
            } else if($row['event'] == "ENTERQUEUE") {
                $callData['caller'] = $row['data2'];
                break;
            }
        }
        if(empty($callData) === false) $callData['live'] = $live;
        return $callData;
    }

    public static function getDailyDetailedStatsForSip(int $sipNumber, string $startDate, string $endDate):array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select tql.time,tql.callid,tql.queuename,tql.event,tql.data1,tql.data2,tql.data3,tql.data4,tql.data5
                                                from db_asterisk.tbl_queue_log tql
                                                where tql.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                                and tql.agent = :sipNumber
                                                order by id asc");
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->bindValue(":sipNumber", sprintf("SIP/%s", $sipNumber), \PDO::PARAM_STR);
        $stmt->execute();
        $stmt2 = $pdoHandler->prepare("select distinct tql.data2,tql.event from db_asterisk.tbl_queue_log tql where tql.callid = :uniqueID and tql.event in ('ENTERQUEUE', 'INFO')");
        $stmt2->bindParam(":uniqueID", $uniqueID, \PDO::PARAM_STR);
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $dailySipStats = [];
        $alreadyPaused = false;
        foreach($resultSet as $row) {
            $tmpArr = [];
            switch($row['event']) {
                case "CONNECT":
                    $tmpArr['event'] = $row['event'];
                    $tmpArr['eventTime'] = $row['time'];
                    $tmpArr['eventDuration'] = $row['data3'];
                    $tmpArr['queueName'] = $row['queuename'];
                    break;
                case "COMPLETECALLER":
                case "COMPLETEAGENT":
                    $tmpArr['event'] = ($row['event'] == "COMPLETECALLER") ? "COMPLETECALLER" : "COMPLETEAGENT";
                    $tmpArr['eventTime'] = $row['time'];
                    $tmpArr['eventDuration'] = $row['data2'];
                    $tmpArr['queueName'] = $row['queuename'];
                    break;
                case "BLINDTRANSFER":
                    $tmpArr['event'] = $row['event'];
                    $tmpArr['eventTime'] = $row['time'];
                    $tmpArr['eventDuration'] = $row['data4'];
                    $tmpArr['transferCombination'] = $row['data1'];
                    $tmpArr['queueName'] = $row['queuename'];
                    break;
                case "RINGNOANSWER":
                    $tmpArr['event'] = (intval($row['data1']) <= config('asterisk.DND_TIMER')) ? "DND" : "MISSED";
                    $tmpArr['eventTime'] = $row['time'];
                    $tmpArr['eventDuration'] = $row['data1'] / 1000;
                    $tmpArr['queueName'] = $row['queuename'];
                    break;
                case "PAUSE":
                    if($alreadyPaused) {
                        break;
                    }
                    $alreadyPaused = true;
                    $tmpArr['event'] = $row['event'];
                    $tmpArr['eventTime'] = $row['time'];
                    $tmpArr['eventReason'] = $row['data1'];
                    break;
                case "UNPAUSE":
                    if($alreadyPaused === false) {
                        break;
                    }
                    $alreadyPaused = false;
                    $tmpArr['event'] = $row['event'];
                    $tmpArr['eventTime'] = $row['time'];
                    $tmpArr['eventReason'] = $row['data1'];
                    break;
            }
            if($row['event'] == "CONNECT" or $row['event'] == "RINGNOANSWER") {
                $uniqueID = $row['callid'];
                $stmt2->execute();
                $resultSet = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
                if(empty($resultSet)) {
                    $tmpArr['caller'] = null;
                    $tmpArr['callType'] = null;
                } else {
                    foreach($resultSet as $infoRow) {
                        if($infoRow['event'] == 'INFO') {
                            $tmpArr['callType'] = $infoRow['data2'];
                        } else {
                            $tmpArr['caller'] = $infoRow['data2'];
                        }
                    }
                }
            }
            if(empty($tmpArr) === false) $dailySipStats[] = $tmpArr;
        }
        return $dailySipStats;
    }

    public static function getDetailedSipStatsForMonthlyReport(int $sipNumber, $startDate, $endDate) {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select date_format(tql.time, '%m-%d') as days,
                                               sum(case when tql.event = 'CONNECT' then cast(tql.data3 as unsigned integer) else 0 end) as ring_time,
                                               sum(case when tql.event = 'CONNECT' then 1 else 0 end) as total_answers,
                                               sum(case tql.event
                                                 when 'COMPLETECALLER' then cast(tql.data2 as unsigned integer)
                                                 when 'COMPLETEAGENT' then cast(tql.data2 as unsigned integer)
                                                 when 'BLINDTRANSFER' then cast(tql.data4 as unsigned integer)
                                               else 0 end) as call_time
                                        from db_asterisk.tbl_queue_log tql
                                        where tql.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                          and tql.agent = :sipNumber
                                          and tql.event in ('CONNECT', 'COMPLETECALLER', 'COMPLETEAGENT', 'BLINDTRANSFER')
                                        group by days");
        $stmt->bindValue(":sipNumber", sprintf("SIP/%s", $sipNumber), \PDO::PARAM_STR);
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

    public static function getAnsweredCalls(array $queueArr, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        # silknetshi transferis dros agebul zars ar aqvs COMPLETE eventi imito rom pizikurad sxva call centrshi gadadis zari.
        # amitom sachiroa kerdzo shemtxveva gavitvaliswinot maseti zarebistvis, call centebis gaertianebis mere amosagebi iqneba.
        $silknetTransferCode = self::$transferMapping['silknet']['codes'][0];
        $silkneCorpTransferCode = self::$transferMapping['silknetCorp']['codes'][0];
        $bindMarks = implode(",", array_fill(0, count($queueArr), "?"));
        $stmt = $pdoHandler->prepare(sprintf("select tql.callid, tql.time, tql.data2, if(tql2.event in ('COMPLETECALLER', 'COMPLETEAGENT'), tql2.data1, tql2.data3) wait_time,
                                               if(tql2.event in ('COMPLETECALLER', 'COMPLETEAGENT'), tql2.data2, tql2.data4) call_time,right(tql2.agent, 3) as sip
                                        from db_asterisk.tbl_queue_log tql
                                        inner join db_asterisk.tbl_queue_log tql2 on tql2.callid = tql.callid and
                                                                                     (tql2.event in ('COMPLETECALLER', 'COMPLETEAGENT') or (tql2.event = 'BLINDTRANSFER' and tql2.data1 in ('%s', '%s')))
                                        where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                          and tql.queuename in (%s)
                                          and tql.event = 'ENTERQUEUE'", $silknetTransferCode, $silkneCorpTransferCode, $bindMarks));
        $index = 1;
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        foreach($queueArr as $queueName) {
            $stmt->bindValue($index++, $queueName, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

    public static function getAbandonedCalls(array $queueArr, string $startDate, string $endDate): array {
        $bindMarks = implode(",", array_fill(0, count($queueArr), "?"));
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select tql.callid,tql.time, tql.data2, tql2.data1 position, tql2.data2 original_position, tql2.data3 wait_time
                                                from db_asterisk.tbl_queue_log tql
                                                inner join db_asterisk.tbl_queue_log tql2 on tql2.callid = tql.callid and
                                                                                             tql2.event = 'ABANDON'
                                                where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                  and tql.queuename in (%s)
                                                  and tql.event = 'ENTERQUEUE'", $bindMarks));
        $index = 1;
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        foreach($queueArr as $queueName) {
            $stmt->bindValue($index++, $queueName, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

    public static function getCallsByTypes(array $queueArr, string $startDate, string $endDate): array {
        $bindMarks = implode(",", array_fill(0, count($queueArr), "?"));
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("with
                                                 filtered_table as
                                                   (select distinct callid,data2
                                                   from db_asterisk.tbl_queue_log tql
                                                    where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                      and tql.queuename in (%s)
                                                      and tql.event = 'INFO')
                                            select count(1) counter,data2
                                            from filtered_table ft
                                            group by ft.data2", $bindMarks));
        $index = 1;
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        foreach($queueArr as $queueName) {
            $stmt->bindValue($index++, $queueName, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

    public static function getTotalInboundCallDuration(array $queues, string $startDate, string $endDate) {
        $bindMarks = implode(",", array_fill(0, count($queues), "?"));
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select ifnull(round((sum(case when tql.event in ('COMPLETECALLER', 'COMPLETEAGENT') then cast(tql.data2 as unsigned integer)
                                                            when tql.event = 'BLINDTRANSFER' then cast(tql.data4 as unsigned integer)
                                                         else 0
                                                       end) ) / 60), 0) as totalDurMin
                                                from db_asterisk.tbl_queue_log tql
                                                where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                  and tql.queuename in (%s)
                                                  and tql.event in ('COMPLETECALLER', 'COMPLETEAGENT', 'ABANDON')", $bindMarks));
        $index = 1;
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        foreach($queues as $queue) {
            $stmt->bindValue($index++, $queue, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $totalMins = $stmt->fetch(\PDO::FETCH_NUM)[0];
        $stmt = null;
        return intval($totalMins);
    }

    public static function getWaitCount(array $queues, string $startDate, string $endDate):array {
        $bindMarks = implode(",", array_fill(0, count($queues), "?"));
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select sum(case when tql.event = 'CONNECT' and tql.data1 < 30 then 1 else 0 end) as answeredCount,
                                                       sum(case when tql.event = 'ABANDON' and tql.data3 < 30 then 1 else 0 end) as abandonedCount
                                                from db_asterisk.tbl_queue_log tql
                                                where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                  and tql.queuename in (%s)
                                                  and tql.event in ('CONNECT', 'ABANDON')", $bindMarks));
        $index = 1;
        foreach($queues as $queueName) {
            $stmt->bindValue($index++, $queueName, \PDO::PARAM_STR);
        }
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index, $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet= $stmt->fetch(\PDO::FETCH_ASSOC);
//        logger()->error($resultSet);
        $stmt = null;
        return $resultSet;
    }

    public static function getWaitingTimeBeforeAnswer(array $queues, string $startDate, string $endDate): array {
        $bindMarks = implode(",", array_fill(0, count($queues), "?"));
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select avg(cast(tql.data1 as unsigned integer)) avgWaitTime,
                                                            max(cast(tql.data1 as unsigned integer)) maxWaitTime,
                                                            max(cast(tql.data1 as unsigned integer)) minWaitTime
                                                    from db_asterisk.tbl_queue_log tql
                                                    where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                      and tql.queuename in (%s)
                                                      and tql.event = 'CONNECT'", $bindMarks));
        $index = 1;
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        foreach($queues as $queueName) {
            $stmt->bindValue($index++, $queueName, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $resultSet = [];
        $stmt->fetchAll(\PDO::FETCH_FUNC, function($avg, $max, $min) use (&$resultSet) {
            $resultSet['avgWaitTime'] = intval($avg);
            $resultSet['maxWaitTime'] = intval($max);
            $resultSet['minWaitTime'] = intval($min);
        });
        $stmt = null;
        return $resultSet;
    }

    public static function getAbandonWaitTimes(array $queues, string $startDate, string $endDate): array {
        $bindMarks = implode(",", array_fill(0, count($queues), "?"));
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select avg(cast(tql.data3 as unsigned integer)) avgWaitTime,
                                                            max(cast(tql.data3 as unsigned integer)) maxWaitTime,
                                                            max(cast(tql.data3 as unsigned integer)) minWaitTime
                                                    from db_asterisk.tbl_queue_log tql
                                                    where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                      and tql.queuename in (%s)
                                                      and tql.event = 'ABANDON'", $bindMarks));
        $index = 1;
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        foreach($queues as $queueName) {
            $stmt->bindValue($index++, $queueName, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $resultSet = [];
        $stmt->fetchAll(\PDO::FETCH_FUNC, function($avg, $max, $min) use (&$resultSet) {
            $resultSet['avgWaitTime'] = intval($avg);
            $resultSet['maxWaitTime'] = intval($max);
            $resultSet['minWaitTime'] = intval($min);
        });
        $stmt = null;
        return $resultSet;
    }

    public static function getCallCountByQueuesAndType(array $queues, string $startDate, string $endDate): array {
        $bindMarks = implode(",", array_fill(0, count($queues), "?"));
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select count(1) counter, X.event, X.info
                                                    from (
                                                                    select distinct tql.callid,
                                                                                    tql.event,
                                                                                    (select tql2.data2
                                                                                     from db_asterisk.tbl_queue_log tql2
                                                                                     where tql2.callid = tql.callid and tql2.event = 'INFO'
                                                                                     limit 1) as info
                                                                    from db_asterisk.tbl_queue_log tql
                                                                    where tql.event in ('CONNECT', 'ABANDON')
                                                                      and tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                                      and tql.queuename in (%s)
                                                                  ) X group by X.info, X.event", $bindMarks));
        $index = 1;
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        foreach($queues as $queueName) {
            $stmt->bindValue($index++, $queueName, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $resultSet = [];
        $stmt->fetchAll(\PDO::FETCH_FUNC, function($counter, $event, $type) use(&$resultSet) {
            if(isset($resultSet[$type]) === false) {
                $resultSet[$type] = [
                    'answered' => 0,
                    'abandoned' => 0,
                    'total' => 0
                ];
            }
            if($event == "CONNECT") {
                $resultSet[$type]['answered'] = intval($counter);
            } else if($event == "ABANDON") {
                $resultSet[$type]['abandoned'] = intval($counter);
            }
            $resultSet[$type]['total'] += intval($counter);
        });
        $stmt = null;
        if(empty($resultSet)) return $resultSet;
        return $resultSet;
    }

    public static function getTransfersCountFromSilknetSide(string $startDate, string $endDate): int {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select count(distinct callid) counter
                                                from db_asterisk.tbl_queue_log tql
                                                where tql.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                                  and tql.event = 'INFO'
                                                  and tql.data2 = 'Silknet'");
        $stmt->bindValue(":startDate", $startDate,\PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_NUM)[0];
        $stmt = null;
        return $result;
    }

}
