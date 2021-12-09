<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class B2BMailSupport extends Model {

    protected $connection = "mysql_asterisk_stats";

    private static $connName = "mysql_asterisk_stats";

    protected $table = "B2BMailSupport";

    public $timestamps = false;

    protected $guarded = [];

    public static function insertB2BMail(array $values) {
        return self::create([
            'email' => $values['email'],
            'companyName' => $values['companyName'],
            'gsm' => $values['gsm'],
            'comment' => $values['comment'] ?? null,
            'reasonID' => $values['reasonID'],
            'operatorID' => $values['operatorID'],
            'inserted' => Carbon::now(),
        ]);
    }

    public static function updateB2BMail(array $values) {
        $b2bMail = self::find($values['id']);
        $b2bMail->email = $values['email'];
        $b2bMail->companyName = $values['companyName'];
        $b2bMail->gsm = $values['gsm'];
        $b2bMail->reasonID = $values['reasonID'];
        $b2bMail->operatorID = $values['operatorID'];
        if(isset($values['comment'])) $b2bMail->comment = $values['comment'];
        $b2bMail->save();
        return $b2bMail;
    }
    public static function getB2BMailsByOperators(array $operatorIDs, string $startDate, string $endDate):array {
        $bindMarks = implode(",", array_fill(0, count($operatorIDs), "?"));
        /**
         * @var \PDO $pdoHandler
         */
        $pdoHandler = DB::connection(self::$connName)->getPdo();
        $stmt = $pdoHandler->prepare(sprintf("select
                                                       b2bMail.id, 
                                                       b2bMail.email,
                                                       b2bMail.companyName,
                                                       b2bMail.gsm,
                                                       reasons.reason,
                                                       b2bMail.operatorID,
                                                       ifnull(b2bMail.comment, 'No comment')  comment,
                                                       b2bMail.inserted,
                                                       CONCAT(o.first_name, ' ', o.last_name) name,
                                                       o.personal_id,
                                                       u.username
                                                from db_asterisk.B2BMailSupport b2bMail
                                                inner join db_asterisk.B2BMailReasons reasons on reasons.id = b2bMail.reasonID
                                                left join call_centre_interface.operators o on o.id = b2bMail.operatorID
                                                left join call_centre_interface.users u on u.operators_id = o.id
                                                where b2bMail.inserted between STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s') and STR_TO_DATE(?, '%%Y-%%m-%%d %%H:%%i:%%s')
                                                  and b2bMail.operatorID in (%s)", $bindMarks));
        $stmt->bindValue(1, $startDate, \PDO::PARAM_STR);
        $stmt->bindValue(2, $endDate, \PDO::PARAM_STR);
        $index = 3;
        foreach($operatorIDs as $operatorID) {
            $stmt->bindValue($index++, $operatorID, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $resultSet = [];
        while(($row = $stmt->fetch(\PDO::FETCH_ASSOC)) !== false) {
            $id = $row['operatorID'];
            unset($row['operatorID']);
            $resultSet[$id][] = $row;
        }
        $stmt = null;
        return $resultSet;
    }

}
