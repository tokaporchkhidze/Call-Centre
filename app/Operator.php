<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Operator extends Model {

    protected $table = "operators";

    public $timestamps = false;

    protected $guarded = [];

    public function user() {
        return $this->hasOne('App\User', 'operators_id', 'id');
    }

    public static function getOperatorsWithSips() {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->query("SELECT
                                      o.id,
                                      o.personal_id,
                                      o.first_name,
                                      o.last_name,
                                      o.trainee,
                                      o.created_at,
                                      IFNULL(s.sip, 'Not assigned') AS sip,
                                      u.username as userName,
                                      u.id as userID
                                    FROM operators o
                                      LEFT JOIN sips s ON o.id = s.operators_id
                                      LEFT JOIN users u on u.operators_id = o.id
                                    where s.id in (select stq.sips_id from sips_to_queues stq where stq.queues_id in (4,5,6,7,8,9,10,11,12))");
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        unset($stmt);
        return $resultSet;
    }

    public static function getOperatorWithSip(int $operatorID) {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare("SELECT
                                      o.id,
                                      o.personal_id,
                                      o.first_name,
                                      o.last_name,
                                      o.trainee,
                                      o.created_at,
                                      IFNULL(s.sip, 'Not assigned') AS sip,
                                      u.username as userName
                                    FROM operators o
                                      LEFT JOIN sips s ON o.id = s.operators_id
                                      LEFT JOIN users u on u.operators_id = o.id
                                    WHERE o.id = :operatorID");
        $stmt->bindValue(":operatorID", $operatorID, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        unset($stmt);
        return $result;
    }

    public static function getOperatorIDsByPersonalIDs(array $personalIDs): array {
        $bindMarks = implode(",", array_fill(0, count($personalIDs), "?"));
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select id
                                                from call_centre_interface.operators o
                                                where o.personal_id in (%s)", $bindMarks));
        $index = 1;
        foreach($personalIDs as $personalID) {
            $stmt->bindValue($index++, $personalID, \PDO::PARAM_STR);
        }
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_FUNC, function($counter) {return $counter;});
        $stmt = null;
        return $resultSet;
    }

}
