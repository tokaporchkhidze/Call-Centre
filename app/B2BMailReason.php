<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class B2BMailReason extends Model {

    protected $connection = "mysql_asterisk_stats";

    private static $connName = "mysql_asterisk_stats";

    protected $table = "B2BMailReasons";

    public $timestamps = false;

    protected $guarded = [];



}
