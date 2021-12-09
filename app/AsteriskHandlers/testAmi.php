<?php

/**
 * This script is used to output specified queue statistics using Asterisk AMI gateway
 * We'll use AMI QueueStatus command to get information about specific queue, queue members and queue callers
 * Queue name should be passed as an argument. For example, cdmaqueue
 * Should return json output on request
 */

// Set queue name
// if (!isset($_GET['requested_queue'])) {
//     echo 'Please specify queue name as a GET request method' . PHP_EOL;
//     exit(0);
// }
// $requestedQueue = $_GET['requested_queue'];
$requestedQueue = '100100';

// if (!isset($_GET['requested_info'])) {
//     echo 'Please specify queue info as a GET request method' . PHP_EOL;
//     exit(0);
// }
// $requestedInfo = $_GET['requested_info'];
$requestedInfo = 'QueueMember';

// Set variables for future parsing
$mm = array();
$k = 0;
$amiRecord = array();
$amiOutput = '';

// Create AMI socket
$socket = fsockopen('10.240.0.29', 5038, $errno, $errstr, 10);
if (!$socket) {
    echo 'Asterisk AMI connection error, please check parameters. ' . PHP_EOL;
    exit(0);
}

// Generate random ID for AMI session
$randomId = rand(10000000000000000, 99999999900000000);

// Login to AMI and get specified queue information
fwrite($socket, "Action: Login\r\nUsername: portal\r\nSecret: xashlama\r\n\r\nAction: QueueStatus\r\nActionID: $randomId\r\nQueue: $requestedQueue\r\n\r\nAction: Logoff\r\n\r\n");

// echo '#######################' . PHP_EOL;

// Parse AMI output start
while (!feof($socket)) {
    $amiOutput .= fread($socket, 1024);
}

$mm = explode("\r\n", $amiOutput);

for ($i = 0; $i < count($mm); $i++) {
    if ($mm[$i] == "") {
        $k++;
    }

    $m = explode(":", $mm[$i]);
    if (isset($m[1])) {
        $amiRecord[$k][trim($m[0])] = trim($m[1]);
    }
}
// Parse AMI output end

// Count $amiRecord array values for further loop
$arrCount = count($amiRecord);

// General queue information
$queueStats = array();
$queue = array();

// Queue members information
$queueMembers = array();
$members = array();

// Queue calls information
$queueCalls = array();
$calls = array();

/**
 * Loop through $amiRecord array and get specific values like:
 *  QueueParams - general queue information, like:
 *      Queue name
 *      Active calls which are not answered yet
 *      Completed calls
 *      Abandoned calls
 *      Queue weight
 *  QueueMember - queue members information, like:
 *      Queue member name, for example:
 *          SIP/399
 *      Calls taken
 *      Queue member status codes:
 *          1 - Not in use (Is online and is available)
 *          2 - In use (Is online and has active call)
 *          3 - Busy
 *          4 - Not found in system
 *          5 - Unavailable (Is offline)
 *          6 - Ringing
 *  QueueEntry - queue calls information
 *      Caller position inside queue
 *      Caller ID, or caller number, or A number
 *      Wait time inside queue
 */
for ($i = 0; $i < $arrCount; $i++) {
    if (in_array('QueueParams', $amiRecord[$i])) {
        $queueStats['queueName'] = $amiRecord[$i]['Queue'];
        $queueStats['calls'] = $amiRecord[$i]['Calls'];
        $queueStats['completed'] = $amiRecord[$i]['Completed'];
        $queueStats['abandoned'] = $amiRecord[$i]['Abandoned'];
        $queueStats['weight'] = $amiRecord[$i]['Weight'];

        array_push($queue, $queueStats);
        $queueStats = array();
        continue;
    } else if (in_array('QueueMember', $amiRecord[$i])) {
        $queueMembers['member'] = $amiRecord[$i]['Name'];
        $queueMembers['callsTaken'] = $amiRecord[$i]['CallsTaken'];

        $queueMembers['status'] = $amiRecord[$i]['Status'];
        // Change status code for human readable format
        switch ($queueMembers['status']) {
            case 1:
                $queueMembers['status'] = 'Available';
                break;
            case 2:
                $queueMembers['status'] = 'OnCall';
                break;
            case 3:
                $queueMembers['status'] = 'Busy';
                break;
            case 4:
                $queueMembers['status'] = 'NotFound';
                break;
            case 5:
                $queueMembers['status'] = 'Offline';
                break;
            case 6:
                $queueMembers['status'] = 'Ringing';
                break;
            default:
                $queueMembers['status'] = 'StatusError';
        }

        // 1 - On call, 0 - Not on call
        $queueMembers['inCall'] = $amiRecord[$i]['InCall'];

        array_push($members, $queueMembers);
        $queueMembers = array();
        continue;
    } else if (in_array('QueueEntry', $amiRecord[$i])) {
        $queueCalls['callerId'] = $amiRecord[$i]['CallerIDNum'];
        $queueCalls['position'] = $amiRecord[$i]['Position'];
        $queueCalls['waitTime'] = $amiRecord[$i]['Wait'];

        array_push($calls, $queueCalls);
        $queueCalls = array();
        continue;
    }
}

// print_r($queue);
// echo "\n\n";
// print_r($members);
// echo "\n\n";
// print_r($calls);
// echo "\n\n";

header('Content-Type: application/json');

switch ($requestedInfo) {
    case 'QueueParams':
        echo json_encode($queue);
        break;
    case 'QueueMember':
        echo json_encode($members);
        break;
    case 'QueueEntry':
        echo json_encode($calls);
        break;
}

echo "\n";

// EOF
