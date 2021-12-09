<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 10/25/2018
 * Time: 7:04 PM
 */

namespace App\Logging;

use MongoDB\Driver\Manager;
use MongoDB\Driver\BulkWrite;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\Exception\Exception;
use MongoDB\Driver\Query;
use MongoDB\Driver\Command;


class Logger {

    /**
     * this instance will be used
     * for whole application during logging.
     * Only one connection to mongodb must be
     * instantiated during request
     *
     * @var Logger
     */
    private static $instance;

    private static $logData = array();

    private static $sameKeyCounter = 1;

    private $commited = false;

    /**
     * this object is responsible for maintaing
     * connection to mongodb
     *
     * @var Manager
     */
    private $mongoDBHandler;

    public static function instance() {
        if(!isset(self::$instance)) {
            self::$instance = new Logger();
        }
        return self::$instance;
    }


    /**
     * constructor is private function because
     * this class should be created only once for one request,
     * it's called from static function instance only once.
     *
     * Logger constructor.
     */
    private function __construct() {
        $connectionURI = sprintf('mongodb://%s:%s/%s',
            config('logging.mongo_log.host'),
            config('logging.mongo_log.port'),
            config('logging.mongo_log.db'));

        $uriOptions = array(
            'appname' => config('app.name'),
            'username' => config('logging.mongo_log.user'),
            'password' => config('logging.mongo_log.password'),
        );
        $this->mongoDBHandler = new Manager($connectionURI, $uriOptions);
    }


    public function addLogInfo($key, $data) {
        if(array_key_exists($key, self::$logData)) {
            $key = sprintf("%s_%s", $key, self::$sameKeyCounter);
            self::$sameKeyCounter += 1;
        }
        self::$logData[$key] = $data;
        $this->commited = false;
    }

    /**
     * this function generates default data
     * for all incoming log records
     *
     * @param array $logData
     * @param bool $isAPI
     */
    private function generateCommonData() {
        self::$logData['time'] = new UTCDateTime();
//        logger()->error(self::$logData['time']);
        self::$logData['webInfo']['ip'] = request()->ip();
        self::$logData['webInfo']['method'] = request()->method();
        self::$logData['webInfo']['URL'] = urldecode(request()->fullUrl());
//        self::$logData['webInfo']['headers'] = request()->header();
        $inputArr = request()->all();
        if(!empty($inputArr)) {
            self::$logData['webInfo']['params'] = $inputArr;
        } else {
            self::$logData['webInfo']['params'] = "Empty";
        }
        $userInfo = auth('api')->user();
        if($userInfo === null) {
            self::$logData['userInfo'] = null;
        } else {
            self::$logData['userInfo'] = array(
                'id' => $userInfo['id'],
                'username' => $userInfo['username'],
                'email' => $userInfo['email']
            );
        }
    }


    public function commitLog($msg, $level) {
        if($this->commited) {
            return;
        }
        $writer = new BulkWrite();
        $this->generateCommonData();
        $this->addLogInfo("commit_msg", $msg);
        $this->addLogInfo("log_level", $level);
        $writer->insert(self::$logData);
        $this->mongoDBHandler->executeBulkWrite(sprintf('livegeDev.%s', config('logging.mongo_log.api_collection')), $writer);
        $this->commited = true;
        self::$logData = array();
        self::$sameKeyCounter = 1;
    }

    public function getLogsByParameterType($filters) {
//        $filters = [
//            '$and' => [
//                ["userInfo.username" => "toka"],
//                ["API" => "deleteSip"]
//            ]
//        ];
        $options = [
            'sort' => ['time' => -1],
            'projection' => [
                'displayInfo' => 1,
                'API' => 1,
                'time' => 1,
                'webInfo.ip' => 1,
                'userInfo.username' => 1,
                '_id' => 0]
        ];
        $query = new Query($filters, $options);
        $cursor = $this->mongoDBHandler->executeQuery('livegeDev.apiLog', $query);
        return $cursor->toArray();
    }

    public function getLogsByGivenFilters(array $inputArr) {
        $filters = $this->generateFilters($inputArr);
        $commandArr = [
            'aggregate' => config('logging.mongo_log.api_collection'),
            'pipeline' => array(
                ['$match' => $filters,],
                ['$sort' => ['time' => -1]],
                ['$skip' => intval($inputArr['offset'])],
                ['$limit' => intval($inputArr['limit'])],
                [
                    '$project' => [
                        'displayInfo' => 1,
                        'API' => 1,
                        'time' => ['$dateToString' => ['format' => "%Y-%m-%d %H:%M:%S", 'date' => '$time', 'timezone' => 'Asia/Tbilisi']],
                        'webInfo.ip' => 1,
                        'userInfo.username' => 1,
                        '_id' => 0,
                    ]
                ],
            ),
            'cursor' => (object)[]
        ];
        $cmd = new Command($commandArr);
        $cursor = $this->mongoDBHandler->executeCommand("livegeDev", $cmd);
        return $cursor->toArray();
    }

    private function generateFilters(array $inputArr) {
        $filters = [
            '$and' => []
        ];
        $filters['$and'][] = ['displayInfo' => ['$exists' => true]];
        foreach($inputArr as $key => $value) {
            switch($key) {
                case "userName":
                    $filters['$and'][] = ["userInfo.username" => $value];
                    break;
                case "IP":
                    $filters['$and'][] = ["webInfo.ip" => $value];
                    break;
                case "action":
                    $filters['$and'][] = ["API" => $value];
                    break;
                case "startDate":
                    $dateTime = new \Datetime($value);
                    $filters['$and'][] = ["time" => ['$gt' => new UTCDateTime($dateTime->getTimestamp() * 1000)]];
                    break;
                case "endDate":
                    $dateTime = new \Datetime($value);
                    $filters['$and'][] = ["time" => ['$lt' => new UTCDateTime($dateTime->getTimestamp() * 1000)]];
                    break;
                default:
                    break;
            }
        }
        return $filters;
    }

    private function sendMails() {
        // TODO
        // function should send mails to authorized people for critical errors
    }


}