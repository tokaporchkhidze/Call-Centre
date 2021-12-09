<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 2/25/2019
 * Time: 1:44 PM
 */

namespace App\AsteriskHandlers;

use App\AsteriskStatistics\QueueLog;

class AsteriskManager {

    private $amiHandler;

    private $sleep = 500000;

    private function login() {
    }

    public function __construct() {
        $this->amiHandler = fsockopen(config("asterisk.AMI_CONNECT_PARAMS.host"), config("asterisk.AMI_CONNECT_PARAMS.port"), $errno, $errstr, 10);
        stream_set_timeout($this->amiHandler, 1.5);
        fwrite($this->amiHandler, sprintf("Action:login\r\nUsername:%s\r\nSecret:%s\r\nEvents:off\r\n\r\n", config('asterisk.AMI_CONNECT_PARAMS.username'), config('asterisk.AMI_CONNECT_PARAMS.secret')));
        $this->readSocketData();
    }

    public function __destruct() {
        fwrite($this->amiHandler, "Action:Logoff\r\n\r\n");
        fclose($this->amiHandler);
    }

    private function generateActionID() {
        return rand(10000000000000000, 99999999900000000);
    }

    public function getSipStatuses($queue, $sip) {
        $actionID = $this->generateActionID();
        fwrite($this->amiHandler, sprintf("ActionID:%s\r\nAction:QueueStatus\r\nQueue:%s\r\nMember:SIP/%s\r\n\r\n", $actionID, $queue, $sip));
        $this->sleep = 50000;
        $responseStr = $this->readSocketData();
        $packetsArr = explode("\r\n\r\n", $responseStr);
        if(empty($packetsArr)) {
            throw new \RuntimeException(sprintf("No Data in socket after executing %s !!!", __FUNCTION__));
        }
        $this->checkActionStatus($packetsArr[0]);
        unset($packetsArr[0]);
        $sipStatus = [];
        foreach($packetsArr as $packet) {
            $parsedPacket = $this->readResponsePacket($packet);
            if(empty($parsedPacket)) continue;
            if($parsedPacket['Event'] != "QueueMember") continue;
            if($parsedPacket['Status'] == "5") {
                $sipStatus['active'] = 0;
            } else {
                $sipStatus['active'] = 1;
            }
            if($parsedPacket['InCall'] == "1") {
                $sipStatus['inCallCurrQueue'] = 1;
            }
            if($parsedPacket['Status'] == "2" and $parsedPacket['InCall'] != "1") {
                $sipStatus['inCallOtherQueue'] = 1;
            }
            if($parsedPacket['Status'] == "6") {
                $sipStatus['ringing'] = 1;
            }
            if($parsedPacket['Paused'] == "1") {
                $sipStatus['paused'] = 1;
            }
        }
        return $sipStatus;
    }

    public function pauseSip($sipNumber, $pauseReason, $paused) {
        $actionID = $this->generateActionID();
        $event = QueueLog::getLastPauseStatus($sipNumber)['event'] ?? "UNPAUSE";
        if($paused == "true") {
            if($event == "PAUSE") {
                throw new \RuntimeException("Already Paused!");
            }
            fwrite($this->amiHandler, sprintf("ActionID:%s\r\nAction:QueuePause\r\nInterface:sip/%s\r\nPaused:%s\r\nReason:%s\r\n\r\n",
                                              $actionID, $sipNumber, $paused, $pauseReason));

        } else {
            if($event == "UNPAUSE") {
                throw new \RuntimeException("Already Unpaused!");
            }
            fwrite($this->amiHandler, sprintf("ActionID:%s\r\nAction:QueuePause\r\nInterface:sip/%s\r\nPaused:%s\r\n\r\n",
                                              $actionID, $sipNumber, $paused));
        }
        $responseStr = $this->readSocketData();
        $packetsArr = explode("\r\n\r\n", $responseStr);
        $this->checkActionStatus($packetsArr[0]);
        return true;
    }

    public function getQueueStatus($queueName) {
        $actionID = $this->generateActionID();
        if(isset($queueName)) {
            fwrite($this->amiHandler, sprintf("ActionID:%s\r\nAction:QueueStatus\r\nQueue:%s\r\n\r\n", $actionID, $queueName));
        } else {
            fwrite($this->amiHandler, sprintf("ActionID:%s\r\nAction:QueueStatus\r\n\r\n", $actionID));
        }
        $currTime = time();
        $responseStr = $this->readSocketData();
//        return $responseStr;
        $packetsArr = explode("\r\n\r\n", $responseStr);
        if(empty($packetsArr)) {
            throw new \RuntimeException("No Data in socket after executing QueueStatus command in AMI!!!");
        }
        $this->checkActionStatus($packetsArr[0]);
        unset($packetsArr[0]);
        $queueStats = [];
        foreach($packetsArr as $packet) {
            $tmpArr = $this->readResponsePacket($packet);
            if(empty($tmpArr)) continue;
            if($tmpArr["Event"] == "QueueParams") {
                $queueStats[$tmpArr["Queue"]]["inQueue"] = intval($tmpArr["Calls"]);
                $queueStats[$tmpArr["Queue"]]["inCall"] = 0;
                $queueStats[$tmpArr["Queue"]]["totalWait"] = 0;
                $queueStats[$tmpArr["Queue"]]["totalMembers"] = 0;
//                $queueStats[$tmpArr["Queue"]]["allSips"] = []; No Need of Not active sips
                $queueStats[$tmpArr["Queue"]]["activeSips"] = [];
                $queueStats[$tmpArr["Queue"]]["ringingSips"] = [];
                $queueStats[$tmpArr["Queue"]]["pausedSips"] = [];
                $queueStats[$tmpArr["Queue"]]["inCallSips"] = [];
                $queueStats[$tmpArr["Queue"]]["acwSips"] = [];
            } else if($tmpArr["Event"] == "QueueMember") {
                $sipNumber = intval(substr($tmpArr['Name'], strpos($tmpArr['Name'], "/") + 1));
                if($tmpArr["InCall"] == "1") {
                    $queueStats[$tmpArr["Queue"]]["inCall"]++;
                    $queueStats[$tmpArr["Queue"]]["inCallSips"][] = $sipNumber;
                }
                if( ($tmpArr["Status"] == "1" || $tmpArr["Status"] == "2")) {
                    $lastCallTimeStamp = $tmpArr["LastCall"] ?? null;
                    if(isset($lastCallTimeStamp) and $lastCallTimeStamp != "0" and $tmpArr['Paused'] != "1" and $tmpArr['InCall'] != "1") {
                        if(($currTime - $lastCallTimeStamp) < config('asterisk.ACW_TIME')) {
                            $queueStats[$tmpArr["Queue"]]["acwSips"][] = $sipNumber;
                        }
                    }
                    if($tmpArr["Status"] == "2" && $tmpArr["InCall"] != "1") {
                        $queueStats[$tmpArr["Queue"]]["inCallSips"][] = $sipNumber;
                    }
                    $queueStats[$tmpArr["Queue"]]["activeSips"][] = $sipNumber;
                }
                if($tmpArr["Status"] == "6") {
                    $queueStats[$tmpArr["Queue"]]["activeSips"][] = $sipNumber;
                    $queueStats[$tmpArr["Queue"]]["ringingSips"][] = $sipNumber;
                }
                if($tmpArr['Paused'] == "1" and $tmpArr["Status"] != "5" and $tmpArr["Status"] !== "2") {
                    $queueStats[$tmpArr["Queue"]]["pausedSips"][] = $sipNumber;
                }
//                $queueStats[$tmpArr["Queue"]]["allSips"][] = $sipNumber; No Need of Not active sips :)
                $queueStats[$tmpArr["Queue"]]["totalMembers"]++;
            } else if($tmpArr["Event"] == "QueueEntry") {
                if($tmpArr["Wait"] != "0") {
                    $queueStats[$tmpArr["Queue"]]["totalWait"] += intval($tmpArr["Wait"]);
                }
            }
        }
        return $queueStats;
    }

    private function checkActionStatus(string $commandStatus) {
        $responseArr = [];
        $commandStatusArr = explode("\r\n", $commandStatus);
        foreach($commandStatusArr as $line) {
            $arr = explode(":", $line, 2);
            if(count($arr) === 2) {
                $responseArr[trim($arr[0])] = trim($arr[1]);
            }
        }
        if(empty($responseArr)) {
            throw new \RuntimeException("Command execution info packet is empty!");
        }
        if($responseArr["Response"] != "Success") {
            throw new \RuntimeException(sprintf("Cannot execute command: %s", $responseArr["Message"]));
        }
    }

    private function readSocketData(): string {
        $startTime = time();
        $responseString = "";
        usleep($this->sleep);
        while(true) {
            $newContent = fread($this->amiHandler, 1024);
            if($newContent == "") break;
            $responseString .= $newContent;
            $delta = time() - $startTime;
            if($delta > 10) {
                throw new \RuntimeException("Infinite loop during reading Asterisk Manager Socket, exited!!!");
            }
        }
        return $responseString;
    }

    /**
     * in asterisk AMI api, every response consists of packets(groups) delimited by `\r\n\r\n`,
     * packet is key=value pair delimited with `\r\n`
     *
     * @param string $packet
     * @return array returns associative array or if nothing in packet empty array
     *
     */
    private function readResponsePacket(string $packet) {
        $paramsArr = explode("\r\n", $packet);
        $tmpArr = [];
        foreach($paramsArr as $paramLine) {
            $arr = explode(":", $paramLine, 2);
            if(count($arr) === 2) {
                $tmpArr[trim($arr[0])] = trim($arr[1]);
            }
        }
        return $tmpArr;
    }

}
