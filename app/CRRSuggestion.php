<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CRRSuggestion extends Model {

    protected $connection = "mysql_asterisk_stats";

    private static $connName = "mysql_asterisk_stats";

    protected $table = "CRR_suggestions";

    public $timestamps = false;

    protected $guarded = [];

}
