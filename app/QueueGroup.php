<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class QueueGroup extends Model {

    protected $table = "queue_groups";

    public $timestamps = [];

    public static function getGroupsWithQueues($groupName=null) {
        /**
         * @var \PDO $pdoHandler
         */
        $isGroupName = (isset($groupName)) ? true : false;
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select qg.id          as group_id,
                                                       qg.name        as group_name,
                                                       qg.display_name        as group_display_name,
                                                       q.id           as queue_id,
                                                       q.name         as queue_name,
                                                       q.display_name as queue_display_name
                                        from call_centre_interface.queue_groups qg
                                               inner join call_centre_interface.queues_to_groups qtg on qtg.queue_groups_id = qg.id
                                               inner join call_centre_interface.queues q on q.id = qtg.queues_id
                                        %s order by group_name", ($groupName) ? "where qg.name = :groupName" : ""));
        if($groupName) $stmt->bindValue(":groupName", $groupName, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = [];
        $groupArr = [];
        $lastGroupName = "";
        $stmt->fetchAll(\PDO::FETCH_FUNC, function($groupID, $groupName, $groupDisplayName, $queueID, $queueName, $queueDisplayName) use (&$resultSet, &$groupArr, &$lastGroupName) {
            if($lastGroupName == "") {
                $lastGroupName = $groupName;
            }
            if($lastGroupName != $groupName) {
                $resultSet[$lastGroupName] = $groupArr;
                $lastGroupName = $groupName;
                $groupArr = [
                    'queues' => []
                ];
            }
            if(count($groupArr) === 1) {
                $groupArr['id'] = $groupID;
                $groupArr['name'] = $groupName;
                $groupArr['displayName'] = $groupDisplayName;
            }
            $groupArr['queues'][] = [
              'id' => $queueID,
              'name' => $queueName,
              'displayName' => $queueDisplayName
            ];
        });
        $resultSet[$lastGroupName] = $groupArr;
        return $resultSet;
    }

}
