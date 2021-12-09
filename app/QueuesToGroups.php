<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class QueuesToGroups extends Model {

    protected $table = "queues_to_groups";

    protected $guarded = [];

    public $timestamps = [];

    public static function ifAllreadyExists(int $queueID, array $groupIDArr) {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection()->getPDO();
        $stmt = $pdoHandler->prepare("select qtg.queues_id, qtg.queue_groups_id
                                                from call_centre_interface.queues_to_groups qtg 
                                                where queues_id = :queueID and queue_groups_id = :groupID");
        $stmt->bindValue(":queueID", $queueID, \PDO::PARAM_INT);
        $stmt->bindParam(":groupID", $groupID, \PDO::PARAM_INT);
        foreach($groupIDArr as $id) {
            $groupID = $id;
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if($row !== false) {
                $stmt = null;
                return true;
            }
        }
        $stmt = null;
        return false;
    }

}
