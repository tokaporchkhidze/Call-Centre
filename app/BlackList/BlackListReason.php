<?php

namespace App\BlackList;

use Illuminate\Database\Eloquent\Model;

class BlackListReason extends Model {

    protected $connection = "mysql_asterisk_stats";

    private static $connName = "mysql_asterisk_stats";

    protected $table = "blackListReasons";

    public $timestamps = false;

    protected $guarded = [];

}
