<?php

namespace App\Rules\PauseSip;

use App\AsteriskHandlers\AsteriskManager;
use App\Sip;
use App\RestrictedBreaks\RestrictedBreak;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Rule;

class CheckSipStatus implements Rule
{

    private $sipStatus;

    private $breakReason;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($breakReason) {
        $this->breakReason = $breakReason;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $sipData = Sip::getSipsWithTemplatesAndOperatorsAndQueues($value);
        $operatorID = $sipData[0]['operators_id'];
        $sipId = $sipData[0]['sip_id'];
        $queueName = $sipData[0]['queues'][0]['name'] ?? null;
        $asteriskManager = new AsteriskManager();
        $sipStatus = $asteriskManager->getSipStatuses($queueName, $value);
        $this->sipStatus = $sipStatus;
        $restrictedBreak = new RestrictedBreak();
        $restrictedBreak->sipsID = $sipId;
        $restrictedBreak->operatorsID = $operatorID;
        $restrictedBreak->breakReason = $this->breakReason;
        $res = true;
        if($sipStatus['active'] === 0) {
            $restrictedBreak->reason = config('asterisk.RESTRICTED_BREAK_NOTACTIVE');
            $res = false;
        } else if($sipStatus['active'] === 1) {
            if( ($this->sipStatus['inCallCurrQueue'] ?? null) === 1 or ($this->sipStatus['inCallOtherQueue'] ?? null) === 1) {
                $restrictedBreak->reason = config('asterisk.RESTRICTED_BREAK_INCALL');
                $res = false;
            } else if(($this->sipStatus['ringing'] ?? null) === 1) {
                $restrictedBreak->reason = config('asterisk.RESTRICTED_BREAK_RINGING');
                $res = false;
            }
        } else {
            throw new \RuntimeException(sprintf("Unknown sip status in %s, Status: %s", __FUNCTION__, $sipStatus['active']));
        }
        if(!$res) {
            $restrictedBreak->save();
        }
        return $res;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        $msg = "";
        if($this->sipStatus['active'] === 0) {
            $msg = "სიპ არ არის აქტიური!";
        } else if($this->sipStatus['active'] === 1) {
            if( ($this->sipStatus['inCallCurrQueue'] ?? null) === 1) {
                $msg = "მიმდინარე ზარის დროს არ შეიძლება პაუზის აღება!";
            }
            if(($this->sipStatus['inCallOtherQueue'] ?? null) === 1) {
                $msg = "მიმდინარე ზარის დროს არ შეიძლება პაუზის აღება!";
            }
            if(($this->sipStatus['ringing'] ?? null) === 1) {
                $msg = "ზარი შემოდის!";
            }
        } else {
            throw new \RuntimeException(sprintf("Unknown sip status in %s, Status: %s", __FUNCTION__, $this->sipStatus['active']));
        }
        return $msg;
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
