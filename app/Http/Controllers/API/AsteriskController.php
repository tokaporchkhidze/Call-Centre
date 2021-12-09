<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 2/25/2019
 * Time: 12:55 PM
 */

namespace App\Http\Controllers\API;

use App\AsteriskHandlers\AsteriskManager;
use App\AsteriskStatistics\QueueLog;
use App\BlackList\BlackList;
use App\BlackList\BlackListReason;
use App\Common\SSH2;
use App\Http\Controllers\Controller;
use App\Http\Requests\BlackList\AddNumberInBlackList;
use App\Http\Requests\BlackList\RemoveNumberFromBlackList;
use App\Http\Requests\Pause\StorePause;
use App\Http\Requests\Pause\UpdatePause;
use App\Http\Requests\Pause\StorePauseInPause;
use App\Logging\Logger;
use App\Policies\BlackListPolicy;
use App\Sip;
use Carbon\Carbon;
use http\Exception\RuntimeException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AsteriskController extends Controller {

    /**
     * @var Logger
     */
    private $logger;

    public function __construct() {
        $this->logger = Logger::instance();
    }

    public function pauseSip(StorePause $request) {
        $inputArr = $request->input();
//        logger()->error($inputArr);
        $asteriskManager = new AsteriskManager();
        $queueName = Sip::getSipsWithTemplatesAndOperatorsAndQueues($inputArr['sipNumber'])[0]['queues'][0]['name'] ?? null;
        $asteriskManager->pauseSip($inputArr["sipNumber"], $inputArr["pauseReason"] ?? null, $inputArr["paused"]);
        if($inputArr['paused'] == "true") {
            $this->logger->addLogInfo("API", config('logging.mongo_mapping.პაუზაში შესვლა'));
            $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელი %s შევიდა პაუზაში - %s",
                $request->user('api')->username, $inputArr['pauseReason']));
        } else {
            $this->logger->addLogInfo("API", config('logging.mongo_mapping.პაუზიდან გამოსვლა'));
            $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელი %s გავიდა პაუზიდან",
                $request->user('api')->username));
        }
        return response()->json([
            'message' => (strtolower($inputArr["paused"] == "true")) ? "დაპაუზდა!" : "პაუზა მოიხსნა!"
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function addPauseInPause(StorePauseInPause $request) {
        $inputArr = $request->input();
//        logger()->error($inputArr);
        $asteriskManager = new AsteriskManager();
        $asteriskManager->pauseSip($inputArr['sipNumber'], null, "false");
        $asteriskManager->pauseSip($inputArr['sipNumber'], $inputArr['pauseReason'], "true");
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.პაუზის რედაქტირება'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s დაარედაქტირა პაუზა",
            $request->user('api')->username));
        return response()->json([
                                    'message' => "დაპაუზდა!"
                                ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function updatePauseReason(UpdatePause $request) {
        $inputArr = $request->input();
        $res = QueueLog::updatePauseReason($inputArr['rowID'], $inputArr['pauseReason']);
        return response()->json(['message' => 'პაუზის მიზეზი შეიცვალა!'], config('errorCodes.HTTP_SUCCESS'));
    }

    public function getBlackListReasons() {
        return BlackListReason::all()->toArray();
    }

    public function getNumbersFromBlackList() {
        if(Gate::allows('getNumbersFromBlackList') === false) {
            return response()->json([
                'message' => 'unathorized'
            ], config('errorCodes.HTTP_UNAUTHORIZED'));
        }
        return BlackList::getBlackListWithReasons();
    }

    public function addNumberInBlackList(AddNumberInBlackList $request) {
        $inputArr = $request->input();
        if(Gate::allows('addNumberInBlackList') === false) {
            return response()->json([
                'message' => 'unathorized'
            ], config('errorCodes.HTTP_UNAUTHORIZED'));
        }
        $reason = BlackListReason::find($inputArr['reasonID']);
        if(!isset($reason)) {
            throw new \RuntimeException("Such reason doesn't exist!");
        }
        $currDate = new Carbon();
        $nextMonth = new Carbon();
        $nextMonth->addMonth(1);
        DB::beginTransaction();
        BlackList::create([
            'number' => $inputArr['number'],
            'description' => $inputArr['description'] ?? null,
            'reasonID' => $inputArr['reasonID'],
            'inserted' => $currDate,
            'insertedUserID' => $request->user('api')->id,
            'toBeRemoved' => $inputArr['toBeRemovedDate'] ?? $nextMonth
        ]);
        $ssh2 = new SSH2(config('asterisk.HOST'), config('asterisk.USERNAME'),
            config('asterisk.PASSWORD'), config('asterisk.PORT'), true);
        $ssh2->cmd(sprintf(config('asterisk.ADD_NUMBER_IN_BLACK_LIST'), $inputArr['number']));
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.ნომრის შავ სიაში დამატება'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s ნომერი დაამატა შავ სიაში - %s",
            $request->user('api')->username, $inputArr['number']));
        DB::commit();
        return response()->json([
            'message' => 'success'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function removeNumberFromBlackList(RemoveNumberFromBlackList $request) {
        $inputArr = $request->input();
        if(Gate::allows('removeNumberFromBlackList') === false) {
            return response()->json([
                'message' => 'unathorized'
            ], config('errorCodes.HTTP_UNAUTHORIZED'));
        }
        DB::beginTransaction();
        $blackList = BlackList::where('number', $inputArr['number'])->where('removed', null)->first();
        $blackList->removed = Carbon::now();
        $blackList->removedUserID = $request->user('api')->id;
        $blackList->save();
        $ssh2 = new SSH2(config('asterisk.HOST'), config('asterisk.USERNAME'),
            config('asterisk.PASSWORD'), config('asterisk.PORT'), true);
        $ssh2->cmd(sprintf(config('asterisk.DELETE_NUMBER_IN_BLACK_LIST'), $inputArr['number']));
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.ნომრის შავ სიიდან წაშლა'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა %s ნომერი წაშალა შავი სიიდან - %s",
            $request->user('api')->username, $inputArr['number']));
        DB::commit();
        return response()->json([
            'message' => 'success'
        ], config('errorCodes.HTTP_SUCCESS'));
    }

    public function getBlackListHistory(Request $request) {
        if(Gate::allows('getBlackListHistory') === false) {
            return response()->json([
                'message' => 'unathorized'
            ], config('errorCodes.HTTP_UNAUTHORIZED'));
        }
        $inputArr = $request->input();
        return BlackList::getBlackListHistory($inputArr['number']);
    }

    public function getCurrentSipStatus(Request $request) {
        $inputArr = $request->input();
        $asteriskManager = new AsteriskManager();
        $queueName = Sip::getSipsWithTemplatesAndOperatorsAndQueues($inputArr['sipNumber'])[0]['queues'][0]['name'] ?? null;
        if(!isset($queueName)) {
            throw new \RuntimeException('სიპი არც ერთ რიგში არ არის!');
        }
        $sipStatus = $asteriskManager->getSipStatuses($queueName, $inputArr['sipNumber']);
        return $sipStatus;
    }

}