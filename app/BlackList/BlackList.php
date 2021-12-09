<?php

namespace App\BlackList;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class BlackList extends Model {

    protected $connection = "mysql";

    private static $connName = "mysql";

    protected $table = "db_asterisk.blackList";

    public $timestamps = false;

    protected $guarded = [];

    public static function getBlackListWithReasons() {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->query("select bl.number,blr.name as reason, bl.description, bl.inserted, bl.toBeRemoved,
                                                u.username insertedUser
                                            from db_asterisk.blackList bl
                                            inner join db_asterisk.blackListReasons blr on blr.id = bl.reasonID
                                            inner join call_centre_interface.users u on bl.insertedUserID = u.id
                                            left join call_centre_interface.users u2 on bl.removedUserID = u2.id
                                            where bl.removed is null");
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $stmt = null;
        return $resultSet;
    }

    public static function getBlackListHistory(string $number) {
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare("select bl.number,blr.name as reason, bl.description, bl.inserted, bl.toBeRemoved, bl.removed,
                                                    u.username insertedUser, u2.username removedUser
                                            from db_asterisk.blackList bl
                                            inner join db_asterisk.blackListReasons blr on blr.id = bl.reasonID
                                            inner join call_centre_interface.users u on bl.insertedUserID = u.id
                                            left join call_centre_interface.users u2 on bl.removedUserID = u2.id
                                            where bl.number = :number order by bl.id");
        $stmt->bindValue(":number", $number, \PDO::PARAM_STR);
        $stmt->execute();
        $resultSet = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        $stmt = null;
        return $resultSet;
    }

}
