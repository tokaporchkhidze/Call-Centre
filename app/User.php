<?php

namespace App;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use Notifiable, HasApiTokens;

    public $timestamps = false;

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public function template() {
        return $this->hasOne('App\UserToTemplate', 'users_id', 'id');
    }

    public function findForPassport($userName) {
        return $this->where('username', $userName)->first();
    }

    public function permissions() {
        return $this->hasOne("App\Permission", "users_id", "id");
    }

    /**
     * return true if doesnt exist, return false if already exists(by username)
     * @param $userName
     * @return bool
     */
    public static function checkUserName($userName) {
        try {
            self::where('username', $userName)->firstOrFail();
            return false;
        } catch (ModelNotFoundException $e) {
            return true;
        }
    }

    /**
     * return true if doesnt exist, return false if already exists(by email)
     * @param $email
     * @return bool
     */
    public static function checkEmail($email) {
        try {
            self::where('email', $email)->firstOrFail();
            return false;
        } catch(ModelNotFoundException $e) {
            return true;
        }
    }

    public static function createUser($userArr) {
        $now = Carbon::now();
        return User::create([
            'username' => $userArr['userName'],
            'email' => $userArr['email'],
            'first_name' => $userArr['firstName'],
            'last_name' => $userArr['lastName'],
            'password' => Hash::make($userArr['password']),
            'created_at' => $now,
            'last_login' => $now,
            'user_groups_id' => 0,
            'operators_id' => $userArr['operatorsID'] ?? 0
        ]);
    }

    public static function getUsersWithTemplates() {
        /* @var \PDO $pdoHandler */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->query('SELECT
                                              u.id AS users_id,
                                              u.username,
                                              u.first_name,
                                              u.last_name,
                                              u.email,
                                              u.created_at,
                                              u.last_login,
                                              u.password,
                                              u.operators_id,
                                              t.id AS templates_id,
                                              t.name,
                                              t.display_name,
                                              t.priority
                                            FROM users u
                                              INNER JOIN users_to_templates ut ON ut.users_id = u.id
                                              INNER JOIN templates t ON ut.templates_id = t.id');
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if(empty($resultSet)) {
            throw new ModelNotFoundException("No User or template have been found");
        }
        return $resultSet;
    }

    public static function getUserWithTemplateById($userId) {
        /* @var \PDO $pdoHandler */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare('SELECT
                                              u.id AS users_id,
                                              u.username,
                                              u.first_name,
                                              u.last_name,
                                              u.email,
                                              u.created_at,
                                              u.last_login,
                                              u.password,
                                              t.id AS templates_id,
                                              t.name,
                                              t.display_name,
                                              t.priority
                                            FROM users u
                                              INNER JOIN users_to_templates ut ON ut.users_id = u.id
                                              INNER JOIN templates t ON ut.templates_id = t.id
                                            WHERE u.id = :userId');
        $stmt->execute(['userId' => $userId]);
        $resultSet = $stmt->fetch(\PDO::FETCH_ASSOC);
        if(empty($resultSet)) {
            throw new ModelNotFoundException("No User or template have been found");
        }
        return $resultSet;
    }

    public static function deleteAllTokensByUserID(int $userID) {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare("delete from oauth_access_tokens where user_id = :userID");
        $stmt->bindValue(":userID", $userID, \PDO::PARAM_INT);
        $stmt->execute();
        $rowsAffected = $stmt->rowCount();
        $stmt = null;
        return $rowsAffected;
    }

    public static function getUserWithOperatorAndSip(int $id) {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection()->getPdo();
        $stmt = $pdoHandler->prepare("select s.sip
                                                from call_centre_interface.users u 
                                                left join call_centre_interface.operators o on o.id = u.operators_id
                                                left join call_centre_interface.sips s on s.operators_id = o.id
                                                where u.id = :userID");
        $stmt->bindValue(":userID", $id, \PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        if($user === false) {
            return null;
        }
        return $user;
    }

}
