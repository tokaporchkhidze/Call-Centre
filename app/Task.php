<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 1/11/2019
 * Time: 11:24 AM
 */

namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class Task extends Model {

    protected $table = 'tasks';

    /**
     * this function returns all tasks and its permissions by user's id
     * @param $userId
     * @return array
     * @throws ModelNotFoundException
     */
    public static function getTasksByUserId($userId) {
        /* @var \PDO $pdoHandler */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare('SELECT
                                      ts.id,
                                      ts.name,
                                      ts.display_name,
                                      ts.type,
                                      ttt.permission
                                    FROM users_to_templates ut
                                      INNER JOIN templates t ON ut.templates_id = t.id
                                      INNER JOIN templates_to_tasks ttt ON t.id = ttt.templates_id
                                      INNER JOIN tasks ts ON ts.id = ttt.tasks_id
                                    WHERE ut.users_id = :userid');

        $stmt->execute(['userid' => $userId]);
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if(empty($resultSet)) {
            throw new ModelNotFoundException("No task assigned to given userId : $userId");
        }
        $stmt = null;
        return $resultSet;
    }

    /**
     * return all tasks assigned to template by template id
     * @param $templateId
     * @return array
     * @throws ModelNotFoundException
     */
    public static function getTasksByTemplateId($templateId) {
        /* @var \PDO $pdoHandler */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare('SELECT
                                              ts.id,
                                              ts.name,
                                              ts.display_name,
                                              ts.type,
                                              ttt.permission
                                            FROM templates t
                                              INNER JOIN templates_to_tasks ttt ON ttt.templates_id = t.id
                                              INNER JOIN tasks ts ON ts.id = ttt.tasks_id
                                            WHERE t.id = :templateId');
        $stmt->execute(['templateId' => $templateId]);
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if(empty($resultSet)) {
            throw new ModelNotFoundException("Incorrect template id or template has zero tasks, id: $templateId");
        }
        return $resultSet;
    }

    public static function getTaskByTemplateAndName($templateId, $taskName) {
        /* @var \PDO $pdoHandler */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare('SELECT
                                              ts.id,
                                              ts.name,
                                              ts.display_name,
                                              ts.type,
                                              ttt.permission
                                            FROM templates t
                                              INNER JOIN templates_to_tasks ttt ON ttt.templates_id = t.id
                                              INNER JOIN tasks ts ON ts.id = ttt.tasks_id
                                            WHERE t.id = :templateId and ts.name = :taskName');
        $stmt->execute(['templateId' => $templateId, 'taskName' => $taskName]);
        $resultSet = $stmt->fetch(\PDO::FETCH_ASSOC);
        if(empty($resultSet)) {
            throw new ModelNotFoundException("given template doesnt have given task: $templateId");
        }
        return $resultSet;
    }

    public static function getTaskCountByIds($taskIdArr) {
        return self::whereIn('id', $taskIdArr)->count();
    }

}