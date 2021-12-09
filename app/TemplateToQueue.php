<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TemplateToQueue extends Model {

    protected $table = "templates_to_queues";

    protected $guarded = [];

    public $timestamps = false;

    public static function createTemplateQueueBridge($queues, $templateId) {
        /* @var \PDO $pdoHandler */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare('INSERT INTO templates_to_queues (templates_id, queues_id) VALUES (:templateId, :queueId)');
        $stmt->bindParam(":templateId", $templateId, \PDO::PARAM_INT);
        $stmt->bindParam(":queueId", $queueId, \PDO::PARAM_INT);
        foreach($queues as $id) {
            $queueId = $id;
            $stmt->execute();
        }
        $stmt = null;
    }

    public static function deleteTemplateQueueBridge($templateId) {
        self::where('templates_id', $templateId)->delete();
    }

}
