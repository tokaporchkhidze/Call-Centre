<?php

namespace App\Rules\BlackList;

use App\BlackList\BlackList;
use App\BlackList\BlackListReason;
use Illuminate\Contracts\Validation\Rule;

class IfReasonExists implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value) {
        $reason = null;
        if(strtolower($attribute) == "reasonid") {
            $reason = BlackListReason::find($value);
        } else if(strtolower($attribute) == "reasonName") {
            $reason = BlackListReason::where("name", $value)->first();
        }
        if(isset($reason)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The validation error message.';
    }
}
