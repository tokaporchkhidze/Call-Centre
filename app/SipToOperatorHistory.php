<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SipToOperatorHistory extends Model {

    protected $table = 'sips_to_operators_history';

    protected $guarded = [];

    public $timestamps = false;

    public static function getHistoryByOperator(string $idNumber, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare("select stoh.personal_id,stoh.sip,stoh.paired_at,stoh.removed_at
                                                from sips_to_operators_history stoh
                                                where stoh.personal_id = :idNumber
                                                  and stoh.paired_at between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                                order by stoh.id asc");
        $stmt->bindValue(":idNumber", $idNumber, \PDO::PARAM_STR);
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }

    public static function getCurrentSipFromHistory(string $idNumber, int $sipNumber, string $startDate, string $endDate): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare("select 
                                                from sips_to_operators_history stoh
                                                where stoh.personal_id = :idNumber
                                                  and stoh.sip = :sipNumber
                                                  and stoh.paired_at between STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s') and STR_TO_DATE(:endDate, '%Y-%m-%d %H:%i:%s')
                                                  and stoh.removed_at is null");
        $stmt->bindValue(":idNumber", $idNumber, \PDO::PARAM_STR);
        $stmt->bindValue(":sipNumber", $sipNumber, \PDO::PARAM_INT);
        $stmt->bindValue(":startDate", $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(":endDate", $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt = null;
        if($row === false) {
            $row = [];
        }
        return $row;
    }

    public static function getOperatorBySipAndDate(int $sipNumber, string $date): array {
        /*** @var \PDO $pdoHandler*/
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare("select personal_id
                                    from sips_to_operators_history stoh
                                    where stoh.sip = :sipNumber
                                      and ( (stoh.paired_at < STR_TO_DATE(:CRRDate, '%Y-%m-%d %H:%i:%s') and stoh.removed_at is null) or (stoh.paired_at < STR_TO_DATE(:CRRDate, '%Y-%m-%d %H:%i:%s') and stoh.removed_at > STR_TO_DATE(:CRRDate, '%Y-%m-%d %H:%i:%s')) )");
        $stmt->bindValue(":CRRDate", $date, \PDO::PARAM_STR);
        $stmt->bindValue(":sipNumber", $sipNumber, \PDO::PARAM_INT);
        $stmt->execute();

    }

    public static function getSipsForOperatorByDate(array $personalIDsArr, string $startDate, string $endDate): array {
        /*** @var \PDO $pdoHandler*/
        $pdoHandler = DB::connection()->getPdo();
        $bindMarks = implode(",", array_fill(0, count($personalIDsArr), "?"));
        $stmt = $pdoHandler->prepare(sprintf("select personal_id, username, sip, CONCAT(first_name, ' ', last_name) name, paired_at, removed_at
                                                from sips_to_operators_history stoh
                                                where ((stoh.paired_at < STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and
                                                        stoh.removed_at > STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and
                                                        stoh.removed_at < STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s'))
                                                       or (stoh.paired_at > STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and
                                                           stoh.paired_at < STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s'))
                                                       or (stoh.paired_at < STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and stoh.removed_at is null)
                                                       or (stoh.paired_at < STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and
                                                          stoh.removed_at > STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and
                                                          stoh.removed_at > STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')))
                                                and stoh.personal_id in (%s)", $bindMarks));
        $index = 1;
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);

        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        foreach($personalIDsArr as $personalID) {
            $stmt->bindValue($index, $personalID, \PDO::PARAM_STR);
            $index++;
        }
        $stmt->execute();
        $resultSet = [];
        $tmpArr = [];
        $currOperator = "";
        while(($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
            if($currOperator == "") {
                $currOperator = $row['personal_id'];
            }
            if($currOperator != $row['personal_id']) {
                $resultSet[$currOperator] = $tmpArr;
                $tmpArr = [];
                $currOperator = $row['personal_id'];
            }
            $tmpArr[] = $row;
        }
        if($currOperator != "") {
            $resultSet[$currOperator] = $tmpArr;
        }
        $tmt = null;
        return $resultSet;
    }

}
