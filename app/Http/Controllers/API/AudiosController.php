<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 3/13/2019
 * Time: 3:48 PM
 */

namespace App\Http\Controllers\API;


use App\Audio;
use App\Common\SSH2;
use App\Http\Requests\Audio\GetOutCalls;
use App\Http\Requests\Audio\GetInCalls;
use App\Http\Controllers\Controller;
use App\Logging\Logger;
use Illuminate\Http\Request;

class AudiosController extends Controller {

    /**
     * @var Logger
     */
    private $logger;

    public function __construct() {
        $this->logger = Logger::instance();
    }

    public function getRecordedInCalls(GetInCalls $request)  {
        $inputArr = $request->input();
        $this->authorize("view", [Audio::class, $inputArr['sipNumber'] ?? null]);
        return Audio::getRecordedInCalls($inputArr['queueName'] ?? null, $inputArr['sipNumber'] ?? null,
                                       $inputArr['caller'] ?? null, $inputArr['startDate'],
                                         $inputArr['endDate'], $inputArr['uniqueID'] ?? null);

    }

    public function getRecordedOutCalls(GetOutCalls $request) {
        $inputArr = $request->input();
        $this->authorize("view", [Audio::class, $inputArr['sipNumber'] ?? null]);
        $inputArr = $request->input();
        return Audio::getRecordedOutCalls($inputArr['sipNumber'] ?? null, $inputArr['dstNumber'] ?? null,
                                          $inputArr['startDate'], $inputArr['endDate'], $inputArr['uniqueID'] ?? null);
    }

    public function getAudioFile(Request $request) {
        $inputArr = $request->input();
//        logger()->error($inputArr);
        if($inputArr['callType'] == "IN") {
            $callsArr = Audio::getRecordedInCalls($inputArr['queueName'] ?? null, $inputArr['sipNumber'] ?? null,
                                                  $inputArr['caller'] ?? null, $inputArr['startDate'],
                                                  $inputArr['endDate'], $inputArr['uniqueID'] ?? null);
        } else {

            $callsArr = Audio::getRecordedOutCalls($inputArr['sipNumber'] ?? null, $inputArr['dstNumber'] ?? null,
                                                   $inputArr['startDate'], $inputArr['endDate'], $inputArr['uniqueID'] ?? null);
        }
        if(empty($callsArr) === false) {
            $callsArr = $callsArr[0];
        } else {
            throw new \RuntimeException(sprintf("მოთხოვნილი ჩანაწერი არ არსებობს, უნიკალური კოდი - %s!!!", $inputArr['uniqueID']));
        }
//        logger()->error($callsArr);
        $remotePath = $callsArr['file_path'];
        if(empty($remotePath)) {
            throw new \RuntimeException(sprintf("მოთხოვნილი ჩანაწერი არ არსებობს, უნიკალური კოდი - %s!!!", $inputArr['uniqueID']));
        }
//        logger()->error($inputArr);
        if(strtolower($inputArr['action']) == "siponly") {
            throw new \RuntimeException(sprintf("დროებითი ტექნიკური შეფერხება!"));
        } else if(strtolower($inputArr['action']) == "play") {
            if($inputArr['callType'] == "IN") {
                $this->authorize("play", [Audio::class, $callsArr['queuename']]);
            }
        } else if(strtolower($inputArr['action']) == "download") {
            if($inputArr['callType'] == "IN") {
                $this->authorize("download", [Audio::class, $callsArr['queuename']]);
            }
        }
//        logger()->error($remotePath);
        $ssh2 = new SSH2(config('asterisk.HOST'), config('asterisk.USERNAME'),
                         config('asterisk.PASSWORD'), config('asterisk.PORT'), true);
        $localPath = sprintf(config('asterisk.TMP_LOCAL_AUDIO_PATH'), $inputArr['uniqueID']);
        if($ssh2->downloadFile($remotePath, $localPath) === false) {
            $err = error_get_last();
            throw new \RuntimeException(sprintf("Cannot Get Audio File, reason: %s", $err['message']));
        }
        $this->logger->addLogInfo("API", config('logging.mongo_mapping.ზარის ჩანაწერის გადმოწერა/მოსმენა'));
        $this->logger->addLogInfo("displayInfo", sprintf("მომხმარებელმა - %s %s ჩანაწერი - %s",
                                                         $request->user("api")->username,
                                                         (strtolower($inputArr['action']) == "play") ? "მოისმინა" : "გადმოწერა",
                                                         $inputArr['uniqueID']));
        return response()->download($localPath, sprintf("%s", $inputArr['uniqueID']))->deleteFileAfterSend();

    }

}
