<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CRR extends Model {

    protected $connection = "mysql_asterisk_stats";

    private static $connName = "mysql_asterisk_stats";

    protected $table = "CRR_V2";

    public $timestamps = false;

    protected $guarded = [];

    public static function updateCRR(array $values) {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("insert into db_asterisk.CRR_V2(callid, reason, status, queue_log_id, suggestion, number, real_number, skill, language, comment)
                                                values (:uniqueID, :reason, :CRRStatus, :CRRUniqueID, :suggestion, :caller, :realCaller, :skill, :language, :comment)
                                                on duplicate key update callid=values(callid),
                                                                        reason=values(reason),
                                                                        status=values(status),
                                                                        queue_log_id=values(queue_log_id),
                                                                        suggestion=values(suggestion),
                                                                        number=values(number),
                                                                        language=values(language),
                                                                        skill=values(skill),
                                                                        comment=values(comment),
                                                                        real_number=values(real_number)");
        $stmt->bindValue(":uniqueID", $values['uniqueID'], \PDO::PARAM_STR);
        $stmt->bindValue(":CRRUniqueID", $values['CRRUniqueID'], \PDO::PARAM_INT);
        $stmt->bindValue(":realCaller", $values['realCaller'], \PDO::PARAM_STR);
        $stmt->bindValue(":skill", $values['skill'], \PDO::PARAM_STR);
        $stmt->bindValue(":language", $values['language'], \PDO::PARAM_STR);
        (isset($values['reason'])) ? $stmt->bindValue(":reason", $values['reason'], \PDO::PARAM_INT) : $stmt->bindValue(":reason", null, \PDO::PARAM_INT);
        (isset($values['status'])) ? $stmt->bindValue(":CRRStatus", $values['status'], \PDO::PARAM_INT) : $stmt->bindValue(":CRRStatus", null, \PDO::PARAM_INT);
        (isset($values['suggestion'])) ? $stmt->bindValue(":suggestion", $values['suggestion'], \PDO::PARAM_INT) : $stmt->bindValue(":suggestion", null, \PDO::PARAM_INT);
        (isset($values['caller'])) ? $stmt->bindValue(":caller", $values['caller'], \PDO::PARAM_STR) : $stmt->bindValue(":caller", null, \PDO::PARAM_INT);
        (isset($values['comment'])) ? $stmt->bindValue(":comment", $values['comment'], \PDO::PARAM_STR) : $stmt->bindValue(":comment", null, \PDO::PARAM_INT);
        $res = $stmt->execute();
        $stmt = null;
        return $res;
    }

    public static function getCRRReasonsBySkills(array $reasonsIDArr, string $startDate, string $endDate, array $datesArr, string $dateFormat): array {
        $bindMarks = implode(",", array_fill(0, count($reasonsIDArr), "?"));
        /*** @var \PDO $pdoHandler*/
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select count(1) counter, crr_reasons.reason, date_format(crr.inserted, '%s') as time_group
                                                from db_asterisk.CRR_V2 crr
                                                left join db_asterisk.CRR_reasons crr_reasons on crr.reason = crr_reasons.id
                                                where crr.inserted between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                  and crr.reason in (%s)
                                                group by reason, time_group order by reason asc, time_group asc", $dateFormat, $bindMarks));
        $stmt->bindValue(1, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(2, $endDate, \PDO::PARAM_STR);
        $index = 3;
        foreach($reasonsIDArr as $reasonID) {
            $stmt->bindValue($index, $reasonID, \PDO::PARAM_INT);
            $index++;
        }
        $stmt->execute();
        $resultSet = [];
        $totalCount = 0;
        $currReason = "";
        while(($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
            if($currReason == "") {
                $currReason = $row['reason'];
            }
            if($currReason != $row['reason']) {
                $resultSet[$currReason]['totalCount'] = $totalCount;
                $currReason = $row['reason'];
                $totalCount = 0;
            }
            if(isset($resultSet[$row['reason']]) === false) {
                $resultSet[$row['reason']] = $datesArr;
            }
            $resultSet[$row['reason']][$row['time_group']] = $row['counter'];
            $totalCount += $row['counter'];
        }
        if(empty($resultSet) === false) {
            $resultSet[$currReason]['totalCount'] = $totalCount;
        }
        $stmt = null;
        return $resultSet;
    }

    public static function getCRRByCaller(string $caller, string $startDate, string $endDate): array {
        /*** @var \PDO $pdoHandler*/
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select crr.callid,crr.reason,crr.queue_log_id,crr.suggestion,crr.skill,crr.language,crr.real_number,crr.number,crr.comment,crr.inserted,tql.agent
                                                from db_asterisk.CRR_V2 crr
                                                inner join db_asterisk.tbl_queue_log tql on tql.id = crr.queue_log_id
                                                where crr.inserted between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                                  and (crr.number = :caller or crr.real_number = :caller)");
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->bindValue(":caller", $caller, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

    public static function getCRRCallCountByGSM(string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select crr.real_number, IFNULL(crr.number, 'Not assigned') number, count(1) counter 
                                                from db_asterisk.CRR_V2 crr
                                                where crr.inserted between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                                group by crr.real_number,crr.number order by real_number");
        $stmt->bindValue(":startDate", $startDate, \PDO::FETCH_ASSOC);
        $stmt->bindValue(":endDate", $endDate, \PDO::FETCH_ASSOC);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $resultSet;
    }

    public static function getCRRAllCallsByGSM(string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select crr.real_number, IFNULL(crr.number, 'Not assigned') number, crr.inserted
                                                from db_asterisk.CRR_V2 crr
                                                where crr.inserted between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                                order by real_number, inserted asc");
        $stmt->bindValue(":startDate", $startDate, \PDO::FETCH_ASSOC);
        $stmt->bindValue(":endDate", $endDate, \PDO::FETCH_ASSOC);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

    public static function getCRRNonB2BCalls(string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select crr.real_number, crr.number, crr.inserted
                                                from db_asterisk.CRR_V2 crr
                                                where crr.inserted between STR_TO_DATE(:startDate, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(:endDate, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                  and HOUR(crr.inserted) > %s
                                                  and HOUR(crr.inserted) < %s", config('asterisk.NON_B2B_START_HOUR'), config('asterisk.NON_B2B_END_HOUR')));
        $stmt->bindValue(":startDate", $startDate, \PDO::FETCH_ASSOC);
        $stmt->bindValue(":endDate", $endDate, \PDO::FETCH_ASSOC);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

    public static function getCRRCountByOperatorsGrouped(string $dateFormat, string $startDate, string $endDate, $sips): array {
        /*** @var \PDO $pdoHandler*/
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        if(!is_array($sips)) {
            $sips = [$sips];
        }
        $bindMarks = implode(",", array_fill(0, count($sips), "?"));
        $stmt = $pdoHandler->prepare(sprintf("select count(1) as counter, date_format(crr.inserted, '%s') as time_group, tql.agent
                                                    from db_asterisk.tbl_queue_log tql
                                                    inner join db_asterisk.CRR_V2 crr on tql.id = crr.queue_log_id
                                                    where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                      and tql.agent in (%s)
                                                      and tql.event = 'CONNECT'
                                                    group by time_group, tql.agent order by time_group asc, tql.agent", $dateFormat, $bindMarks));
        $index = 1;
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        foreach($sips as $sip) {
            $stmt->bindValue($index++, sprintf("SIP/%s", $sip), \PDO::PARAM_STR);
        }
        $stmt->execute();
        $resultSet = [];
        while(($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $resultSet[$row['agent']][$row['time_group']] = $row;
        }
        $stmt = null;
        return $resultSet;
    }

    public static function getCRRCountByOperators($startDate, $endDate, $sip) {
        /*** @var \PDO $pdoHandler*/
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select count(1) as counter
                                                    from db_asterisk.tbl_queue_log tql
                                                    inner join db_asterisk.CRR_V2 crr on tql.id = crr.queue_log_id
                                                    where tql.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                                      and tql.agent = :sip
                                                      and tql.event = 'CONNECT'");
        $stmt->bindValue(":startDate",  $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->bindValue(":sip", sprintf("SIP/%s", $sip), \PDO::PARAM_STR);
        $stmt->execute();
        $counter = $stmt->fetch(\PDO::FETCH_NUM)[0];
        $stmt = null;
        return $counter;
    }

    public static function getCRRByOperators(string $joinStr, string $joinType, string $inputStartDate, string $inputEndDate, $sips): array {
        /*** @var \PDO $pdoHandler*/
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $pdoHandler->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
        $bindMarks = implode(',', array_fill(0, count($sips), '?'));
        $stmt = $pdoHandler->prepare(sprintf("select tql.id,tql.callid,tql.agent,tql.time,tql.queuename,
                                               (select tmp.data2
                                               from db_asterisk.tbl_queue_log tmp
                                               where tmp.callid = tql.callid and tmp.event = 'INFO' limit 1) as type,
                                               (select tmp2.data2
                                               from db_asterisk.tbl_queue_log tmp2
                                               where tmp2.callid = tql.callid and tmp2.event = 'ENTERQUEUE' limit 1) as caller,
                                               ifnull((select tmp2.data2
                                                from db_asterisk.tbl_queue_log tmp2
                                                where tmp2.callid = tql.callid
                                                  and tmp2.event in ('COMPLETEAGENT','COMPLETECALLER')
                                                  and tmp2.time >= tql.time
                                                order by id asc limit 1),
                                                 (select tmp3.data4
                                                from db_asterisk.tbl_queue_log tmp3
                                                where tmp3.callid = tql.callid
                                                  and tmp3.event = 'BLINDTRANSFER'
                                                  and tmp3.time >= tql.time
                                                order by id asc limit 1))      as duration,
                                               crr_reasons.reason,
                                               crr.status,
                                               crr_sug.suggestion,
                                               crr.number,
                                               crr.skill,
                                               crr.language,
                                               crr.real_number,
                                               crr.comment,
                                               crr_reasons.isunwanted,
                                               crr_reasons.category,
                                               crr_reasons.isactive
                                                    from db_asterisk.tbl_queue_log tql
                                                    %s join db_asterisk.CRR_V2 crr on tql.id = crr.queue_log_id
                                                    left join db_asterisk.CRR_reasons crr_reasons on crr.reason = crr_reasons.id
                                                    left join db_asterisk.CRR_suggestions crr_sug on crr.suggestion = crr_sug.id
                                                    where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                      and tql.agent in (%s)
                                                      and tql.event = 'CONNECT'
                                                      %s
                                                    order by tql.agent asc, tql.id asc", $joinStr, $bindMarks, ($joinType == "unregistered") ? "and crr.id is null" : ""));
        $index = 1;
        $stmt->bindValue($index++, $inputStartDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $inputEndDate, \PDO::PARAM_STR);
        foreach($sips as $sip) {
            $stmt->bindValue($index++, sprintf("SIP/%s", $sip), \PDO::PARAM_STR);
        }
        $stmt->execute();
        $resultSet = [];
        while(($row = $stmt->fetch(\PDO::FETCH_ASSOC)) != false) {
            $sip = $row['agent'];
            $resultSet[$sip][] = $row;
        }
        $stmt = null;
        return $resultSet;
    }

    public static function getNotRegisteredCalls($startDate, $endDate, $sips): array {
        /*** @var \PDO $pdoHandler*/
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        if(!is_array($sips)) {
            $sips = array($sips);
        }
        $bindMarks = implode(",", array_fill(0, count($sips), "?"));
        $stmt = $pdoHandler->prepare(sprintf("select tql.time answer_time, tql.agent,tql.callid, 
                                                       (select tql2.data2 from db_asterisk.tbl_queue_log tql2 where tql2.callid = tql.callid and tql2.event = 'ENTERQUEUE' limit 1) as caller
                                                    from db_asterisk.tbl_queue_log tql
                                                    left join db_asterisk.CRR_V2 crr on tql.id = crr.queue_log_id
                                                    where tql.time between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                      and tql.agent in (%s)
                                                      and tql.event = 'CONNECT'
                                                      and crr.callid is null
                                                    order by tql.id asc", $bindMarks));
        $index = 1;
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        foreach($sips as $sip) {
            $stmt->bindValue($index++, sprintf("SIP/%s", $sip), \PDO::PARAM_STR);
        }
        $resultSet = [];
        $stmt->execute();
        while(($row = $stmt->fetch(\PDO::FETCH_ASSOC))) {
            $resultSet[$row['agent']][] = $row;
        }
        $stmt = null;
        return $resultSet;
    }

    public static function getUnwantedCRRCount(string $startDate, string $endDate): int {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select count(1)
                                                from db_asterisk.CRR_V2 crr
                                                inner join db_asterisk.CRR_reasons crr_reasons on crr.reason = crr_reasons.id
                                                where crr_reasons.isunwanted = 'YES'
                                                  and crr.inserted between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')");
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $count = $stmt->fetch(\PDO::FETCH_NUM)[0];
        $stmt = null;
        return $count;
    }

    public static function getAllCRRCount(string $startDate, string $endDate): int {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select count(1)
                                                from db_asterisk.CRR_V2 crr
                                                where crr.inserted between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')");
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $count = $stmt->fetch(\PDO::FETCH_NUM)[0];
        $stmt = null;
        return $count;
    }

}
