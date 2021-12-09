<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SipTemplate extends Model {

    protected $table = "sip_templates";

    public $timestamps = false;

    protected $guarded = [];

    public static function checkIfSipTemplateExistsByName(string $templateName) {
        $template = self::where('name', $templateName)->first();
        if($template != null) {
            return true;
        } else {
            return false;
        }
    }

}
