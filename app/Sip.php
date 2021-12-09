<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class Sip extends Model {

    protected $table = "sips";

    public $timestamps = false;

    protected $guarded = [];

    public static function checkIfSipExistsByNumber($sipNumber) {
        $sip = self::where('sip', $sipNumber)->first();
        if($sip != null) {
            return true;
        }
        return false;
    }

    public static function getSipsWithTemplatesAndOperatorsAndQueues($sipNumber=null) {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("SELECT
                                              s.id  AS sip_id,
                                              s.sip,
                                              ifnull(s.operators_id, 'Not assigned') AS operators_id,
                                              st.id AS template_id,
                                              st.name AS template_name,
                                              ifnull(o.first_name, '') AS first_name,
                                              ifnull(o.last_name, '') AS last_name
                                            FROM sips s
                                              INNER JOIN sip_templates st ON s.sip_templates_id = st.id
                                              LEFT JOIN operators o ON s.operators_id = o.id %s", (isset($sipNumber)) ? sprintf("where s.sip = :sipNumber") : ""));
        if(isset($sipNumber)) $stmt->bindValue(":sipNumber", $sipNumber, \PDO::PARAM_INT);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if(empty($resultSet)) {
            throw new ModelNotFoundException("No sips or templates in database!!!");
        }
        $stmt = $pdoHandler->prepare("select q.id,
                                               q.name,
                                               q.display_name,
                                               q.description,
                                               q.queue_templates_id
                                        from sips s
                                                    left join sips_to_queues stq on s.id = stq.sips_id
                                                    left join queues q on stq.queues_id = q.id
                                        where s.sip = :sipNumber");
        $stmt->bindParam(":sipNumber", $sipNumber, \PDO::PARAM_INT);
        foreach($resultSet as $key =>$row) {
            $sipNumber = $row['sip'];
            $stmt->execute();
            $queue = $stmt->fetchAll(\PDO::FETCH_ASSOC);
//            logger()->error($queue);
            if(isset($queue[0]['id'])) {
                $resultSet[$key]['queues'] = $queue;
            } else {
                $resultSet[$key]['queues'] = [];
            }
        }
        $stmt = null;
        return $resultSet;
    }

    public static function bulkInsert(array $sipNumbers, int $templateId) {
        /**
         * @var \PDO $pdo
         */
        $pdo = DB::connection()->getPdo();
        $stmt = $pdo->prepare("INSERT INTO sips (sip, sip_templates_id) VALUES (:sip, $templateId)");
        $stmt->bindParam(":sip", $sipNumber, \PDO::PARAM_INT);
        foreach($sipNumbers as $sip) {
            $sipNumber = $sip;
            $stmt->execute();
        }
    }

    public static function getSipToOperatorMapping() {
        /**
         * @var \PDO $pdo
         */
        $pdo = DB::connection()->getPdo();
        $stmt = $pdo->query("select s.sip, CONCAT(o.first_name, ' ', o.last_name) as operator
                                       from sips s
                                       left join operators o on o.id = s.operators_id");
        $resultSet = [];
        $resultSet = $stmt->fetchAll(\PDO::FETCH_COLUMN | \PDO::FETCH_GROUP);
        return $resultSet;
    }

    public static function getSipsWithQueueGroups(int $sipNumber=null): ?array {
        /**
         * @var \PDO $pdo
         */
        $pdo = DB::connection()->getPdo();
        $stmt = $pdo->prepare(sprintf("with
                                     tmpTable as (
                                            select s.sip,q.name,qg.name as groupName
                                            from call_centre_interface.sips s
                                            inner join call_centre_interface.sips_to_queues stq on stq.sips_id = s.id
                                            inner join call_centre_interface.queues q on q.id = stq.queues_id
                                            inner join call_centre_interface.queues_to_groups qtg on qtg.queues_id = q.id
                                            inner join call_centre_interface.queue_groups qg on qg.id = qtg.queue_groups_id
                                            %s
                                            order by s.id asc)
                                select distinct tmpTable.sip, tmpTable.groupName
                                from tmpTable", (isset($sipNumber)) ? "where s.sip = :sipNumber": ""));
        if(isset($sipNumber)) $stmt->bindValue(":sipNumber", $sipNumber, \PDO::PARAM_INT);
        $stmt->execute();
        $resultSet = array();
        while(($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $resultSet[$row['sip']][] = $row['groupName'];
        }
        $stmt->closeCursor();
        $stmt = null;
        return $resultSet ?? [];
    }

    public function operator() {
        return $this->hasOne('App\Operator', 'id', 'operators_id');
    }

}
