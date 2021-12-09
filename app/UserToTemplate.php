<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UserToTemplate extends Model {

    protected $table = 'users_to_templates';

    public $timestamps = false;

    protected $guarded = [];

    public function templates() {
        return $this->hasMany(Template::class, 'id', 'templates_id');
    }

    public function users() {
        return $this->hasMany(User::class, 'id', 'users_id');
    }

    public static function createBridgeUserTemplate($userId, $templateId) {
        /* @var \PDO $pdoHandler */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare('INSERT INTO users_to_templates (users_id, templates_id) VALUES (:userId, :templateId)');
        $stmt->execute(['userId' => $userId, 'templateId' => $templateId]);
    }

    public static function deleteUserTemplateBridge($templateId) {
        self::where('templates_id', $templateId)->delete();
    }

}
