#!/usr/bin/php
<?php

/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 4/23/2019
 * Time: 11:22 AM
 */

//namespace App\TestScripts;

//use Illuminate\Support\Facades\Config;

//require_once '/var/www/callCentre/vendor/autoload.php';


//$startDate = new DateTime('2019-04-01');
//$endDdate = new DateTime('2019-04-23');
//
//$interval = new DateInterval('PT1M');
//
//$period = new DatePeriod($startDate, $interval, $endDdate);
//
//foreach($period as $date) {
//    printf("%s\n", $date->format('Y-m-d H:i'));
//}


function gponifnametoarray($name) {
    $retr = array();
    if (preg_match("/^GPON0\/(\d+)\/(\d+)\/(\d+)$/",$name,$m)) {
        $slot=$m[1];$port=$m[2];$ont=$m[3];
        $ifindex = bindec(sprintf("11111010000000%05b%05b00000000",$slot,$port));
        $zifindex = hexdec(sprintf("10%02x%02x00",$slot,$port));
        $sn  = sprintf("000000000%02d%02d%03d",$slot,$port,$ont);
        $zsn = sprintf("463632300%02d%02d%03d",$slot,$port,$ont);
        $retr['slot']=$slot;
        $retr['port']=$port;
        $retr['ont']=$ont;
        $retr['ifindex']=$ifindex;
        $retr['zifindex']=$zifindex;
        $retr['defaultsn']=$sn;
        $retr['defaultzsn']=$zsn;
        return $retr;
    } else return false;
}


print_r(gponifnametoarray('GPON0/11/5/7'));


//$testArr = [
//    '2019-11-06' => [
//            'cdmaqueue' => 0
//    ]
//];
//
//if(in_array(array('cdmaqueue'), $testArr)) {
//    print('ariiiiiiiis');
//}
