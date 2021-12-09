<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 3/13/2019
 * Time: 5:01 PM
 */

namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Traits\CommonFuncs;

class Audio extends Model {

    use CommonFuncs;

    protected $connection = "mysql";

    private static $conn = "mysql";

    protected $table = "db_asterisk.tbl_queue_log";

    public static function getRecordedInCalls($queueName, $sip, $caller, $startDate, $endDate, $uniqueID): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$conn)->getPdo();
        $isQueueName = isset($queueName);
        $isSip = isset($sip);
        $isCaller = isset($caller);
        $isUniqueID = isset($uniqueID);
        $stmt = $pdoHandler->prepare(sprintf("select cdr.calldate as time, cdr.src as caller, LEFT(cdr.lastdata, INSTR(cdr.lastdata, ',')-1) as queuename, cdr.uniqueid as callid, cdr.userfield as file_path,
                                                       tql.event, LEFT(cdr.dstchannel, INSTR(cdr.dstchannel, '-')-1) as agent,
                                                       case when tql.event in ('COMPLETECALLER', 'COMPLETEAGENT') then tql.data2 when tql.event = 'BLINDTRANSFER' then tql.data4 else 0 end as duration
                                                from db_asterisk.tbl_cdr cdr force index (tbl_cdr_calldate_index, tbl_cdr_uniqueid_index)
                                                inner join db_asterisk.tbl_queue_log tql on tql.callid = cdr.uniqueid and event in ('COMPLETECALLER', 'COMPLETEAGENT', 'BLINDTRANSFER', 'ATTENDEDTRANSFER') and tql.agent = LEFT(cdr.dstchannel, INSTR(cdr.dstchannel, '-')-1)
                                                where cdr.calldate between STR_TO_DATE(:startDate, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(:endDate, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                  and cdr.lastapp = 'Queue'
                                                  and cdr.calltype = 'IN'
                                                  and cdr.disposition = 'ANSWERED'
                                                  %s
                                                  %s
                                                  %s
                                                  %s",
                                                         ($isQueueName) ? "and queuename = :queueName" : "",
                                                        ($isCaller) ? "and cdr.src = :caller" : "",
                                                         ($isSip) ? "and agent = :sipNumber" : "",
                                                         ($isUniqueID) ? "and cdr.uniqueid = :uniqueID" : ""));
        if($isQueueName) $stmt->bindValue(":queueName", $queueName, \PDO::PARAM_STR);
        if($isSip) $stmt->bindValue(":sipNumber", sprintf("SIP/%s", $sip), \PDO::PARAM_STR);
        if($isCaller) $stmt->bindValue(":caller", $caller, \PDO::PARAM_STR);
        if($isUniqueID) $stmt->bindValue(":uniqueID", $uniqueID, \PDO::PARAM_STR);
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

    public static function getRecordedOutCalls($sip, $dstNumber, $startDate, $endDate, $uniqueID): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$conn)->getPdo();
        $isSip = isset($sip);
        $isDstNumber = isset($dstNumber);
        $isUniqueID = isset($uniqueID);
        $stmt = $pdoHandler->prepare(sprintf("select cdr.uniqueid,cdr.calldate,cdr.src,cdr.dst,LEFT(cdr.channel, INSTR(cdr.channel, '-') - 1) as sip,cdr.userfield as file_path
                                                        from db_asterisk.tbl_cdr cdr
                                                        where cdr.calldate between STR_TO_DATE(:startDate, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(:endDate, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                          %s
                                                          %s
                                                          %s
                                                          and cdr.calltype = 'OUT'",
                                             ($isDstNumber) ? "and cdr.dst = :dstNumber" : "",
                                             ($isSip) ? "and cdr.channel like :sipNumber": "",
                                             ($isUniqueID) ? "and cdr.uniqueid = :uniqueID" : ""));
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        if($isDstNumber) {
            $type = self::getCallTypeByPrefix($dstNumber);
            if($type == "mobile") {
                $stmt->bindValue(":dstNumber", sprintf("0%s", $dstNumber), \PDO::PARAM_STR);
            } else {
                $stmt->bindValue(":dstNumber", $dstNumber, \PDO::PARAM_STR);
            }
        }
        if($isSip) $stmt->bindValue(":sipNumber", sprintf("SIP/%s%%", $sip), \PDO::PARAM_STR);
        if($isUniqueID) $stmt->bindValue(":uniqueID", $uniqueID, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

}