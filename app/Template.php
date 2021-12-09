<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class Template extends Model {

    protected $table = 'templates';

    public $timestamps = false;

    protected $guarded = [];

    public function templates() {
        return $this->hasMany(UserToTemplate::class, 'templates_id', 'id');
    }

    /**
     * return template object which is assigned to user
     * @param $userId
     * @return mixed
     */
    public static function getTemplateByUserId($userId) {
        /* @var \PDO $pdoHandler */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare('SELECT
                                              t.id,
                                              t.name,
                                              t.display_name,
                                              t.priority
                                            FROM users_to_templates ut
                                              INNER JOIN templates t ON t.id = ut.templates_id
                                            WHERE ut.users_id = :userId');
        $stmt->execute(['userId' => $userId]);
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if(empty($resultSet)) {
            throw new ModelNotFoundException("No template assigned to given user id: $userId");
        }
        $stmt = null;
        return $resultSet[0];
    }

    public static function getUsersByTemplateNames($templateNames) {
        if(!is_array($templateNames)) {
            $templateNames = array($templateNames);
        }
        $templates = self::with('templates.users')->whereIn('name', $templateNames)->get();
        $users = collect();
        foreach($templates as $template) {
            foreach($template->templates as $userToTemplate) {
                foreach($userToTemplate->users as $user) {
                    $users->push($user);
                }
            }
        }
        return $users;
    }

}
