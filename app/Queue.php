<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class Queue extends Model {

    protected $table = "queues";

    protected $guarded = [];

    public $timestamps = false;

    /**
     * this function returns all queues on which user has access
     * @param $userId
     * @return array
     */
    public static function getQueuesByUserId($userId) {
        /* @var \PDO $pdoHandler */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare('SELECT
                              q.id,
                              q.name,
                              q.display_name
                            FROM users_to_templates ut
                              INNER JOIN templates t ON ut.templates_id = t.id
                              INNER JOIN templates_to_queues tq ON t.id = tq.templates_id
                              INNER JOIN queues q ON tq.queues_id = q.id
                            WHERE ut.users_id = :userId');
        $stmt->execute(['userId' => $userId]);
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if(empty($resultSet)) {
            throw new ModelNotFoundException("No queues found on given user id: $userId");
        }
        $stmt = null;
        return $resultSet;
    }

    public static function getQueuesByTemplateId($templateID) {
        /* @var \PDO $pdoHandler */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare('SELECT
                                              q.id,
                                              q.name,
                                              q.display_name
                                            FROM templates t
                                              INNER JOIN templates_to_queues tq ON t.id = tq.templates_id
                                              INNER JOIN queues q ON q.id = tq.queues_id
                                            WHERE t.id = :templateId');
        $stmt->execute(['templateId' => $templateID]);
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if(empty($resultSet)) {
            throw new ModelNotFoundException("Incorrect template id or template has no queue assigned, id: $templateID");
        }
        return $resultSet;
    }

    public static function ifBelongsToTemplate(int $templateID, string $queueName): bool {
        /* @var \PDO $pdoHandler */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare("SELECT q.id,
                                                       q.name,
                                                       q.display_name
                                                FROM templates t
                                                       INNER JOIN templates_to_queues tq ON t.id = tq.templates_id
                                                       INNER JOIN queues q ON q.id = tq.queues_id
                                                WHERE t.id = :templateID and q.name = :queueName");
        $stmt->bindValue(":templateID", $templateID, \PDO::PARAM_INT);
        $stmt->bindValue(":queueName", $queueName, \PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
//        logger()->error($row);
        if($row === false) {
            return false;
        }
        return true;
    }

    public static function getQueuesWithTemplates() {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->query("SELECT
                                      q.id,
                                      q.name,
                                      q.display_name,
                                      q.description,
                                      q.queue_templates_id,
                                      qt.name as template_name
                                    FROM queues q
                                      INNER JOIN queue_templates qt ON qt.id = q.queue_templates_id");
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        unset($stmt);
        return $resultSet;
    }


    public static function getQueuesCountByIds($queuesIds) {
        return self::whereIn('id', $queuesIds)->count();
    }

    public static function getSipsByQueueName($queueName) {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare("SELECT s.id,
                                               s.sip,
                                               ifnull(s.operators_id, 'Not assigned') AS operators_id,
                                               ifnull(o.first_name, '') AS first_name,
                                               ifnull(o.last_name, '') AS last_name,
                                               st.id AS template_id,
                                               st.name AS template_name,
                                               stq.priority
                                        FROM sips s
                                                    INNER JOIN sip_templates st ON s.sip_templates_id = st.id
                                                   LEFT JOIN operators o ON s.operators_id = o.id
                                                    inner JOIN sips_to_queues stq ON s.id = stq.sips_id
                                                    inner JOIN queues q ON q.id = stq.queues_id
                                        WHERE q.name = :queueName");
        $stmt->bindValue("queueName", $queueName, \PDO::PARAM_STR);
        $stmt->execute();
        $sips = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        unset($stmt);
        return $sips;
    }

    public static function getDistinctSipsByQueues(array $queueArr): array {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection()->getPdo();
        $bindMarks = implode(",", array_fill(0, count($queueArr), "?"));
        $stmt = $pdoHandler->prepare(sprintf("select distinct s.sip, IFNULL(o.first_name, '') as first_name, IFNULL(o.last_name, '') as last_name
                                                from queues q
                                                inner join sips_to_queues stq on stq.queues_id = q.id
                                                inner join sips s on s.id = stq.sips_id
                                                inner join operators o on o.id = s.operators_id
                                                where q.name in (%s)", $bindMarks));
        $index = 1;
        foreach($queueArr as $queueName) {
            $stmt->bindValue($index, $queueName, \PDO::PARAM_STR);
            $index++;
        }
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt = null;
        return $resultSet;
    }
    public static function getQueuesBySip($sipNumber) {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare("SELECT
                                                  q.id,
                                                  q.name,
                                                  q.display_name,
                                                  q.description,
                                                  q.queue_templates_id
                                                FROM queues q
                                                  INNER JOIN sips_to_queues stq ON stq.queues_id = q.id
                                                  INNER JOIN sips s ON s.id = stq.sips_id
                                                WHERE s.sip = :sipNumber order by q.id desc");
        $stmt->bindValue(":sipNumber", $sipNumber, \PDO::PARAM_INT);
        $stmt->execute();
        $queues = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        unset($stmt);
        return $queues;
    }

    public static function checkIfExistsByName($queueName) {
        $queue = self::where('name', $queueName)->first();
        if($queue != null) {
            return true;
        } else {
            return false;
        }
    }

    public static function getQueues(){
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare("SELECT
                                                  q.id,
                                                  q.name,
                                                  q.display_name
                                                FROM queues q

                                               ");
        $stmt->execute();
        $queues = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        unset($stmt);
        return $queues;
    }
}
