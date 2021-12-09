<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExcelFileCreator extends Model
{

    protected $connection = "mysql";

    protected $table = 'excel_file_creators';

    public $timestamps = false;

    public function user()
    {
        return $this->belongsTo('App\User', 'users_id', 'id');
    }

    public static function createUserReportBridge($userID, $fileName) {
        self::query()->updateOrInsert(
            [
                'users_id' => $userID,
                'file' => $fileName
            ],
            [
                'users_id' => $userID,
                'file' => $fileName
            ]);
    }

}
