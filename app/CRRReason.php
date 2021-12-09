<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CRRReason extends Model {

    protected $connection = "mysql";

    private static $connName = "mysql";

    protected $table = "db_asterisk.CRR_reasons";

    public $timestamps = false;

    protected $guarded = [];

}
