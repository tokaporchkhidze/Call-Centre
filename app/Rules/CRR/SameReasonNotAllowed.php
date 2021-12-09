<?php

namespace App\Rules\CRR;

use Illuminate\Contracts\Validation\Rule;

class SameReasonNotAllowed implements Rule {

    private $reason;

    /**
     * Create a new rule instance.
     * @param $reason
     * @return void
     */
    public function __construct($reason) {
        $this->reason = $reason;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value) {
        if($value == $this->reason) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message() {
        return 'მიზეზის ველი არ შეცვლილა!';
    }
}
