<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class QueueTemplate extends Model {

    protected $table = 'queue_templates';

    public $timestamps = false;

    protected $guarded = [];

    public static function getTemplateByName(string $templateName) {
        return self::where('name', $templateName)->first();
    }

    public static function checkIfExistsByName(string $templateName) {
        $template = self::where('name', $templateName)->first();
        if($template != null) {
            return true;
        } else {
            return false;
        }
    }

}
