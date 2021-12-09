<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 3/15/2019
 * Time: 12:43 PM
 */

namespace App\Traits;


trait CommonFuncs {

    private static function getCallTypeByPrefix($number) {
        if( preg_match("/^9955[0-9]{8}/", $number) === 1 || (preg_match("/^5[0-9]{8}/", $number) === 1)) {
            $type = "mobile";
        } else if( preg_match("/^322[0-9]{6}/", $number) === 1) {
            $type = "city";
        } else if( preg_match("/^322[0-9]{6}/", $number) === 0 && (preg_match("/^3[0-9]{8}/", $number) === 1 || preg_match("/^4[0-9]{8}/", $number)) ) {
            $type = "region";
        } else {
            $type = "other";
        }
        return $type;
    }

}