<?php

namespace App\Rules\OperatorActivity;

use App\AsteriskStatistics\SipStatusLog;
use Illuminate\Contracts\Validation\Rule;

class IfOperatorOnline implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct() {

    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value) {
        $sipLastStatus = SipStatusLog::getSipLastStatus($value);
        if(empty($sipLastStatus) || $sipLastStatus[0] == config('asterisk.UNREGISTER')) {
            return true;
        }
        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message() {
        return 'F.A.Q_ის გამოსაყენებლად გათიშეთ Xlite!';
    }
}
