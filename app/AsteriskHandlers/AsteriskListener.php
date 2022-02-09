#!/usr/bin/php
<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 2/27/2019
 * Time: 8:13 PM
 */

namespace App\AsteriskHandlers;

require_once '/var/www/callCentre/vendor/autoload.php';

use App\Common\MailHandler;
use PHPMailer\PHPMailer\Exception;
use PDO;
use PDOStatement;
use PDOException;

error_reporting(E_ALL | E_NOTICE);
$beforeExitFunc = function() {
    $mail = new MailHandler();
    $body = sprintf("Asterisk Sip status listener died, located at %s/%s", $_SERVER['PWD'], str_replace("./", "",$_SERVER['PHP_SELF']));
    $mail->configureSMTP()->addRecipients([])->addContent("Asterisk Script Died", $body)->sendMail();
    print("Shutdown Function executed\n");
};

// before shutdown send email to people responsible for Asterisk...
register_shutdown_function($beforeExitFunc);

$constArr = require_once '/var/www/callCentre/config/asteriskListenerConsts.php';

function connectToMysql() {
    global  $constArr;
    try {
        $pdoHandler = new PDO(sprintf("mysql:host=%s", $constArr['mysqlHost']), $constArr['mysqlUser'], $constArr['mysqlPassword'],
                              [
                                  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                                  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                              ]);
    } catch(PDOException $e) {
        die(sprintf("Cannot connect to MySQL: %s\n", $e->getMessage()));
    }
    return $pdoHandler;
}

function pingMysql(PDO &$pdoHandler): bool {
    try {
        $res = $pdoHandler->query("select 1")->fetch(\PDO::FETCH_NUM);
    } catch(\PDOException $e) {
        $errCode = $e->getCode();
        if($errCode == "HY000") {
            printf("MySQL lost connection, try reconnect!\n");
            $pdoHandler = connectToMysql();
            printf("MySQL reconnected\n");
            return false;
        } else {
            throw new \RuntimeException(sprintf("MySQL error, code: %s, msg: %s", $errCode, $e->getMessage()));
        }
    }
    return true;
}

$insertStmt = null;
$checkStmt = null;
$pdoHandler = connectToMysql();
$insertStmt = $pdoHandler->prepare("INSERT INTO db_asterisk.tbl_sip_status (sip_member, sip_status) VALUES (:sipNumber, :sipStatus)");
$insertStmt->bindParam(":sipNumber", $sipNumber, \PDO::PARAM_INT);
$insertStmt->bindParam(":sipStatus", $sipStatus, \PDO::PARAM_STR);
$checkStmt = $pdoHandler->prepare("select tss.sip_member, tss.sip_status
                                                from db_asterisk.tbl_sip_status tss
                                                where tss.sip_member = :sipNumber
                                                  and tss.time > STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s')
                                                  order by tss.id desc limit 1");
$checkStmt->bindParam(":sipNumber", $sipNumber, \PDO::PARAM_INT);
$checkStmt->bindParam(":startDate", $startDate, \PDO::PARAM_STR);


$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if($socket === false) {
    die(sprintf("Cannot create TCP socket: %s\n", socket_strerror(socket_last_error())));
}
$res = socket_connect($socket, $constArr['asteriskHost'], 5038);
if($res === false) {
    die(sprintf("Cannot connect to Asterisk AMI via TCP socket: %s\n", socket_strerror(socket_last_error())));
}
$command = "Action: Login\r\nUsername: portal\r\nSecret: xashlama\r\nEvents: system\r\n\r\n";
$commandRes = socket_write($socket, $command, strlen($command));
if($commandRes === false) {
    die(sprintf("Cannot write command to Asterisk via TCP socket: %s\n", socket_strerror(socket_last_error())));
}
while(true) {
    $recievedData = "";
    $bytes = socket_recv($socket, $recievedData, 1024, MSG_DONTWAIT);
    if($bytes === false) {
        usleep($constArr['WAIT_TIME_BEFORE_READING_SOCKET']);
        continue;
    }
    if($bytes === 0) {
        die("Remote Connection Died\n");
    }
    $packetsArr = explode("\r\n\r\n", $recievedData);
//    print($recievedData."\n");
    foreach($packetsArr as $packet) {
        $tmpArr = readResponsePacket($packet);
        if(empty($tmpArr)) continue;
        if(!isset($tmpArr["Event"]) or $tmpArr["Event"] != "PeerStatus") {
            printf("Incorrect Response: %s\n", implode(",", $tmpArr));
            continue;
        }
        $retrievedDate = date("Y-m-d H:i:s");
        printf("%s: Received Event: %s\n", $retrievedDate, implode(",", $tmpArr));
        $sipNumber = substr($tmpArr['Peer'], strpos($tmpArr['Peer'], "/")+1);
        $sipStatus = $tmpArr['PeerStatus'];
        if($sipStatus == "Reachable") $sipStatus = "Registered";
        if($sipStatus != "Registered" and $sipStatus != "Unregistered") {
            usleep($constArr['WAIT_TIME_BEFORE_READING_SOCKET']);
            continue;
        }
        $startDate = date("Y-m-d H:i:s", strtotime("-1 week"));
        if(pingMysql($pdoHandler) === false) {
            $insertStmt = $pdoHandler->prepare("INSERT INTO db_asterisk.tbl_sip_status (sip_member, sip_status, time) VALUES (:sipNumber, :sipStatus, str_to_date(:retrievedDate, '%Y-%m-%d %H:%i:%s'))");
            $insertStmt->bindParam(":sipNumber", $sipNumber, \PDO::PARAM_INT);
            $insertStmt->bindParam(":sipStatus", $sipStatus, \PDO::PARAM_STR);
            $insertStmt->bindParam(":retrievedDate", $retrievedDate, \PDO::PARAM_STR);
            $checkStmt = $pdoHandler->prepare("select tss.sip_member, tss.sip_status
                                                from db_asterisk.tbl_sip_status tss
                                                where tss.sip_member = :sipNumber
                                                  and tss.time > STR_TO_DATE(:startDate, '%Y-%m-%d %H:%i:%s')
                                                  order by tss.id desc limit 1");
            $checkStmt->bindParam(":sipNumber", $sipNumber, \PDO::PARAM_INT);
            $checkStmt->bindParam(":startDate", $startDate, \PDO::PARAM_STR);
        }
        $checkStmt->execute();
        $row = $checkStmt->fetch();
        if($row !== false && $row['sip_status'] == $sipStatus) {
            printf("%s: Skipped sip status event, DB: %s, Asterisk:%s, SIP:%s \n",$retrievedDate, $row['sip_status'], $sipStatus, $sipNumber);
            continue;
        }
        $insertStmt->execute();
        printf("%s: Inserted Sip status event: %s - %s\n",$retrievedDate, $sipNumber, $sipStatus);
    }
    usleep($constArr['WAIT_TIME_BEFORE_READING_SOCKET']);
}


function readResponsePacket(string $packet) {
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
