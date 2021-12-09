<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OperatorActivity extends Model {

    protected $table = "operatorActivities";

    public $timestamps = false;

    protected $guarded = [];

    public static function getLastActivity(int $sip) {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare("select o.first_name,o.last_name,s.sip,oa.started,oa.ended,oa.activity
                                                from call_centre_interface.operatorActivities oa
                                                left join call_centre_interface.sips s on s.sip = oa.sip
                                                left join call_centre_interface.operators o on o.id = s.operators_id
                                                where oa.sip = :sipNumber
                                                order by oa.id desc limit 1");
        $stmt->bindValue(":sipNumber", $sip, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if($result === false) {
            $result = [];
        }
        return $result;
    }

    public static function getActivityStats(array $sipArr, string $startDate, string $endDate): array {
        $currTime = time();
        $bindMarks = implode(",", array_fill(0, count($sipArr), "?"));
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select o.first_name,o.last_name,s.sip,oa.started,oa.ended,oa.activity
                                            from call_centre_interface.operatorActivities oa
                                            left join call_centre_interface.sips s on s.sip = oa.sip
                                            left join call_centre_interface.operators o on o.id = s.operators_id
                                            where oa.sip in (%s)
                                              and oa.started between str_to_date(?, '%%Y-%%m-%%d %%H:%%i:%%s') and str_to_date(?, '%%Y-%%m-%%d %%H:%%i:%%s')",
                                            $bindMarks));
        $index = 1;
        foreach($sipArr as $sip) {
            $stmt->bindValue($index++, $sip, \PDO::PARAM_INT);
        }
        $stmt->bindValue($index++, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue($index++, $endDate, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = [];
        while(($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
            if(!isset($resultSet[$row['sip']]['first_name']) and isset($row['first_name'])) {
                $resultSet[$row['sip']]['firstName'] = $row['first_name'];
                $resultSet[$row['sip']]['lastName'] = $row['last_name'];
            }
            if(!isset($resultSet[$row['sip']]['totalDuration'])) {
                $resultSet[$row['sip']]['totalDuration'] = 0;
            }
            $endDateTimestamp = strtotime($endDate);
            if(!isset($row['ended'])) {
                $endedTimestamp = ($endDateTimestamp > $currTime) ? $currTime: $endDateTimestamp;
            } else {
                $endedTimestamp = strtotime($row['ended']);
            }
            $row['duration'] = ($endedTimestamp - strtotime($row['started']));
            $resultSet[$row['sip']]['totalDuration'] += $row['duration'];
            $resultSet[$row['sip']]['stats'][] = $row;
        }
        $stmt = null;
        return $resultSet;
    }

}
