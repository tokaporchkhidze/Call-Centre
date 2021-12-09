<?php

namespace App\RestrictedBreaks;

use Illuminate\Database\Eloquent\Model;

class RestrictedBreak extends Model {

    protected $connection = "mysql_asterisk_stats";

    private static $connName = "mysql_asterisk_stats";

    protected $table = "restrictedBreaks";

    public $timestamps = false;

}
