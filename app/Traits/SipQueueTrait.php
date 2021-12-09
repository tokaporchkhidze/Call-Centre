<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 2/1/2019
 * Time: 4:33 PM
 */

namespace App\Traits;


use App\Queue;
use App\Sip;
use App\SipToQueue;

trait SipQueueTrait {

    private function deleteAllSipQueuePairsFromDB($sip) {
        if(is_array($sip)) {
            SipToQueue::whereIn('sips_id', $sip)->delete();
        } else {
            SipToQueue::where('sips_id', $sip->id)->delete();
        }
        $this->logger->addLogInfo(__METHOD__, [
            'sip' => (is_array($sip) === false) ? $sip : implode(",", $sip)
        ]);
    }

    private function getQueueDisplayNamesForLog($sipNumber) {
        $queues = Queue::getQueuesBySip($sipNumber);
        $queuesNames = array();
        if(count($queues) != 0) {
            foreach($queues as $queue) {
                $queuesNames[] = $queue['display_name'];
            }
        }
        return $queuesNames;
    }

}