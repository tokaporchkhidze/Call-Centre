<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 4/23/2019
 * Time: 11:53 AM
 */

namespace App\Traits;

use DateTime;
use DateInterval;
use DatePeriod;

trait DateTimeTrait {

    private function getIntervalFormat(string $intervalBy, $format="date"): string {
        $format = strtolower($format);
        switch($intervalBy) {
            case "month":
                if($format == "date") {
                    return "Y-m";
                } else if($format == "sql") {
                    return "%Y-%m";
                } else {
                    return "P1M";
                }
            case "day":
                if($format == "date") {
                    return "Y-m-d";
                } else if($format == "sql") {
                    return "%Y-%m-%d";
                } else {
                    return "P1D";
                }
            case "hour":
                if($format == "date") {
                    return "Y-m-d H";
                } else if($format == "sql") {
                    return "%Y-%m-%d %H";
                } else {
                    return "PT1H";
                }
            default:
                if($format == "date") {
                    return "Y-m-d";
                } else if($format == "sql") {
                    return "%Y-%m-%d";
                } else {
                    return "P1D";
                }
        }
    }

    private function getPeriodArr(string $intervalBy, DateTime $startDate, DateTime $endDate): array {
        $periodFormat = $this->getIntervalFormat($intervalBy, "period");
        $dateFormat = $this->getIntervalFormat($intervalBy);
        $interval = new DateInterval($periodFormat);
        $period = new DatePeriod($startDate, $interval, $endDate);
        $datesArr = [];
        foreach($period as $date) {
            $datesArr[] = $date->format($dateFormat);
        }
        return $datesArr;
    }


}