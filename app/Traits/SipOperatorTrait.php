<?php
/**
 * Created by PhpStorm.
 * User: tporchkhidze
 * Date: 1/30/2019
 * Time: 2:55 PM
 */

namespace App\Traits;

use App\Operator;
use App\Sip;
use App\SipToOperatorHistory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;

trait SipOperatorTrait {

    private function createOperatorSipBridge($operator, $sip) {
        if($sip->operators_id != null) {
            throw new \RuntimeException('Sip is already owned');
        }
        $sip->operators_id = $operator->id;
        $sip->save();
        $this->logger->addLogInfo(__METHOD__, [
            'operator' => $operator->toArray(),
            'sip' => $sip->toArray(),
            'message' => 'Operator has been added on sip!'
        ]);
        return true;
    }

    private function deleteOperatorSipBridge($operator, $sip) {
        if($sip->operators_id != $operator->id) {
            throw new \RuntimeException("Given sip and operator combination is not valid, nothhing to delete!");
        }
        $sip->operators_id = null;
        $sip->save();
        $this->logger->addLogInfo(__METHOD__, [
            'operator' => $operator->toArray(),
            'sip' => $sip->toArray(),
            'message' => 'Operator has been removed from sip!'
        ]);
        return true;
    }

    private function addOperatorSipLogEntry($operator, $sip) {
        $sipToOperatorBySip = SipToOperatorHistory::where('sip', $sip->sip)->whereNull('removed_at')->first();
        if($sipToOperatorBySip != null) {
            $sipOwner = Operator::where('personal_id', $sipToOperatorBySip->personal_id)->first();
            throw new \RuntimeException(sprintf('Sip is already owned by operator: %s %s', $sipOwner->first_name, $sipOwner->last_name));
        }
        $sipToOPeratorByOperator = SipToOperatorHistory::where('personal_id', $operator->personal_id)->whereNull('removed_at')->first();
        if($sipToOPeratorByOperator != null) {
            throw new \RuntimeException(sprintf('Operator already has sip: %s', $sipToOPeratorByOperator->sip));
        }
        SipToOperatorHistory::create([
            'personal_id' => $operator->personal_id,
            'sip' => $sip->sip,
            'first_name' => $operator->first_name,
            'last_name' => $operator->last_name,
            'username' => $operator->user->username,
            'paired_at' => Carbon::now()
        ]);
        $this->logger->addLogInfo(__METHOD__, [
            'operator' => $operator->toArray(),
            'sip' => $sip->toArray(),
            'message' => 'Created record in operators_to_sips_history table'
        ]);
        return true;
    }

    private function updateOperatorSipLogEntry($operator, $sip) {
        $sipToOperator = SipToOperatorHistory::where('personal_id', $operator->personal_id)->where('sip', $sip->sip)->whereNull('removed_at')->first();
        if($sipToOperator == null) {
            throw new \RuntimeException("Operator-sip history record doesnt exist, nothing to update");
        }
        $sipToOperator->removed_at = Carbon::now();
        $sipToOperator->save();
        $this->logger->addLogInfo(__METHOD__, [
            'operator' => $operator->toArray(),
            'sip' => $sip->toArray(),
            'message' => 'Operator-sip history updated!'
        ]);
        return true;
    }

}