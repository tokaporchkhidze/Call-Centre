<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SipToQueue extends Model {

    protected $table = "sips_to_queues";

    public $timestamps = false;

    protected $guarded = [];

    public static function checkIfExistsBySipAndQueue(int $sipId, int $queueId) {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare("SELECT count(1) as count
                                                FROM sips_to_queues
                                                WHERE sips_id = :sipId AND queues_id = :queueId");
        $stmt->bindValue(":sipId", $sipId, \PDO::PARAM_INT);
        $stmt->bindValue(":queueId", $queueId, \PDO::PARAM_INT);
        $stmt->execute();
        $count = $stmt->fetch(\PDO::FETCH_NUM)[0];
        unset($stmt);
        if($count === 0) {
            return false;
        } else {
            return true;
        }
    }

    public static function checkIfExistsBySipAndQueueBulk(array $sipArr, int $queueId) {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection()->getPdo();
        $bindMarks = implode(',', array_fill(0, count($sipArr), '?'));
        $stmt = $pdoHandler->prepare("SELECT count(1) as count
                                                FROM sips_to_queues stq
                                                inner join sips s on s.id = stq.sips_id
                                                WHERE s.sip in ($bindMarks) AND stq.queues_id = ?");
        $index = 0;
        foreach($sipArr as $key => $sipNum) {
            $index = $key + 1;
            $stmt->bindValue($index, $sipNum, \PDO::PARAM_INT);
        }
        $stmt->bindValue($index+1, $queueId, \PDO::PARAM_INT);
        $stmt->execute();
        $count = intval($stmt->fetch(\PDO::FETCH_NUM)[0]);
        unset($stmt);
        if($count === 0) {
            return false;
        } else {
            return true;
        }
    }


}
