<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 2/28/2019
 * Time: 3:06 PM
 */

namespace App\AsteriskStatistics;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SipStatusLog extends Model {

    protected $connection = "mysql_asterisk_stats";

    protected $table = "tbl_sip_status";

    private static $conn = "mysql_asterisk_stats";

    public static function getSipsLastStatus(array $sipArr) {
        $bindMarks = implode(",", array_fill(0, count($sipArr), "?"));
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$conn)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("WITH
                                         tql as (select max(tss.time) max_time, tss.sip_member
                                    from db_asterisk.tbl_sip_status tss
                                    where tss.time  between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                    and sip_member in (%s)
                                    group by tss.sip_member
                                    order by tss.sip_member, max_time desc)
                                    select tql.max_time as time,tql.sip_member as sip,tql2.sip_status as status
                                    from tql
                                    inner join db_asterisk.tbl_sip_status tql2 on tql2.time = tql.max_time and tql.sip_member = tql2.sip_member", $bindMarks));
        $index = 1;
        $stmt->bindValue($index++, date("Y-m-d H:i:s", strtotime("-1 week")), \PDO::PARAM_STR);
        $stmt->bindValue($index++, date("Y-m-d H:i:s", strtotime("tomorrow")), \PDO::PARAM_STR);
        foreach($sipArr as $sip) {
            $stmt->bindValue($index++, $sip, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $resultSet = [];
        $stmt->fetchAll(\PDO::FETCH_FUNC, function($time, $sip, $status) use (&$resultSet) {
            $resultSet[$sip]["time"] = $time;
            $resultSet[$sip]["status"] = $status;
            $resultSet[$sip]["timeInCurrState"] = time() - strtotime($time);
        });
        return $resultSet;
    }

    public static function getSipLastStatus(int $sipNumber) {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$conn)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select tss.sip_status
                                                from db_asterisk.tbl_sip_status tss
                                                where tss.sip_member = :sipNumber
                                                  and tss.sip_status in ('%s', '%s')
                                                order by tss.id desc limit 1",
                                                    config('asterisk.REGISTER'),
                                                    config('asterisk.UNREGISTER')));
        $stmt->bindValue(":sipNumber", $sipNumber, \PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_NUM);
        $stmt = null;
        return ($row) ? $row : [];
    }

    public static function getRegisterUnregisterTime(int $sipNumber, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$conn)->getPdo();
        $stmt = $pdoHandler->prepare("select tss.time,date_format(tss.time, '%d') days,tss.sip_member,tss.sip_status
                                                from db_asterisk.tbl_sip_status tss
                                                where tss.time between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                                  and tss.sip_member = :sipNumber
                                                  and tss.sip_status in ('Registered', 'Unregistered')
                                                order by tss.id asc");
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->bindValue(":sipNumber", $sipNumber, \PDO::PARAM_INT);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if(empty($resultSet)) {
            return [];
        }
        $currDay = "";
        $lastStatus = "";
        $lastStatusTime = "";
        $registeredTime = 0;
        $unRegisteredTime = 0;
        $calculatedData = [];
        foreach($resultSet as $row) {
            if($currDay == "") $currDay = $row['days'];
            if($currDay != $row['days']) {
                $calculatedData[$currDay]['registeredTime'] = $registeredTime;
                $calculatedData[$currDay]['unRegisteredTime'] = $unRegisteredTime;
                $registeredTime = 0;
                $unRegisteredTime = 0;
                $currDay = $row['days'];
            }
            if($lastStatus == "Registered") {
                $registeredTime += strtotime($row['time']) - strtotime($lastStatusTime);
            }
            if($lastStatus == "Unregistered") {
                $unRegisteredTime += strtotime($row['time']) - strtotime($lastStatusTime);
            }
            $lastStatus = $row['sip_status'];
            $lastStatusTime = $row['time'];
        }
        return $calculatedData;
    }

    public static function getSipLogins(array $sipArr, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$conn)->getPdo();
        $stmt = $pdoHandler->prepare("select sip_member sipNumber, sip_status status, time
                                                    from db_asterisk.tbl_sip_status tss
                                                    where tss.time between str_to_date(:startDate, '%Y-%m-%d %H:%i:%s') and str_to_date(:endDate, '%Y-%m-%d %H:%i:%s')
                                                      and tss.sip_member = :sipNumber
                                                      and tss.sip_status in ('Registered', 'Unregistered') order by id asc");
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->bindParam(":sipNumber", $sipNumber, \PDO::PARAM_INT);
        $resultSet = [];
        foreach($sipArr as $sip) {
            $sipNumber = $sip;
            $stmt->execute();
            $resultSet[$sip] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }
        return $resultSet;
    }

    public static function getLastStatusBeforeDate(int $sipNumber, string $status, string $date): ?string {
        static $pdoHandler = null;
        static $stmt = null;
        /**
         * @var \PDO $pdoHandler
         */
        if(isset($pdoHandler) === false) {
            $pdoHandler = DB::connection(self::$conn)->getPdo();
            $stmt = $pdoHandler->prepare("select tss.time
                                                from db_asterisk.tbl_sip_status tss
                                                where id = (select max(id)
                                                from db_asterisk.tbl_sip_status tss2
                                                where tss2.time <= str_to_date(:startDate, '%Y-%m-%d %H:%i:%s')
                                                and tss2.sip_status = :status
                                                and tss2.sip_member = :sipNumber)");
        }
        $stmt->bindValue(":startDate", $date, \PDO::PARAM_STR);
        $stmt->bindValue(":sipNumber", $sipNumber, \PDO::PARAM_INT);
        $stmt->bindValue(":status", $status, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return ($row === false) ? null : $row['time'];
    }

    public static function getFirstStatusAfterDate(string $sipNumber, string $status, string $date): ?string {
        static $pdoHandler = null;
        static $stmt = null;
        /**
         * @var \PDO $pdoHandler
         */
        if(isset($pdoHandler) === false) {
            $pdoHandler = DB::connection(self::$conn)->getPdo();
            $stmt = $pdoHandler->prepare("select tss.time
                                                from db_asterisk.tbl_sip_status tss
                                                where id = (select min(id)
                                                from db_asterisk.tbl_sip_status tss2
                                                where tss2.time >= str_to_date(:startDate, '%Y-%m-%d %H:%i:%s')
                                                and tss2.sip_member = :sipNumber
                                                and tss2.sip_status = :status)");
        }
        $stmt->bindValue(":startDate", $date, \PDO::PARAM_STR);
        $stmt->bindValue(":sipNumber", $sipNumber, \PDO::PARAM_INT);
        $stmt->bindValue(":status", $status, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return ($row === false) ? null : $row['time'];
    }

}
