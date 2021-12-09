<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TemplateToTask extends Model
{

    protected $table = 'templates_to_tasks';

    public $timestamps = false;

    protected $guarded = [];

    public static function createTemplateTaskBridge($tasks, $templateId) {
        /* @var \PDO $pdoHandler */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare('INSERT INTO templates_to_tasks (templates_id, tasks_id, permission) VALUES (:templateId, :taskId, :permission)');
        $stmt->bindParam(":templateId", $templateId, \PDO::PARAM_INT);
        $stmt->bindParam(":taskId", $taskId, \PDO::PARAM_INT);
        $stmt->bindParam(":permission", $permission, \PDO::PARAM_STR);
        foreach($tasks as $taskArr) {
            $taskId = $taskArr['id'];
            $permission = $taskArr['permission'];
            $stmt->execute();
        }
        $stmt = null;
    }

    public static function deleteTemplateTaskBridge($templateId) {
        self::where('templates_id', $templateId)->delete();
    }

}
