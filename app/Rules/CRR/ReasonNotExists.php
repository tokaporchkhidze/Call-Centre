<?php

namespace App\Rules\CRR;

use App\CRRReason;
use Illuminate\Contracts\Validation\Rule;

class ReasonNotExists implements Rule {

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
        $reason = CRRReason::find($value);
        if(isset($reason) === false || $reason->isactive == "NO") {
            return false;
        }
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'ასეთი მიზეზი არ არსებობს!';
    }
}
