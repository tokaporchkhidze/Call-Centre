<?php

namespace App\Rules\OperatorActivity;

use App\OperatorActivity;
use Illuminate\Contracts\Validation\Rule;

class AlreadyInActivity implements Rule {
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
        $activityArr = OperatorActivity::getLastActivity($value);
        logger()->error($activityArr);
        if(empty($activityArr) || $activityArr['ended'] != null) {
            return true;
        }
        return false;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'უკვე აქტიურია!';
    }
}
