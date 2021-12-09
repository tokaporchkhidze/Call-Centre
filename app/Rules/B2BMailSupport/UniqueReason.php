<?php

namespace App\Rules\B2BMailSupport;

use App\B2BMailReason;
use Illuminate\Contracts\Validation\Rule;

class UniqueReason implements Rule {

    private $reason;

    /**
     * Create a new rule instance.
     *
     * @param $reason
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
        if(B2BMailReason::where('reason', $value)->first() !== null) {
            return false;
        }
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message() {
        return 'ასეთი მიზეზი უკვე არსებობს!';
    }
}
