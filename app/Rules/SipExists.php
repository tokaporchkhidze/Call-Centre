<?php

namespace App\Rules;

use App\Sip;
use Illuminate\Contracts\Validation\Rule;

class SipExists implements Rule
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
        if(Sip::where('sip', $value)->first() == null) {
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
        return 'ასეთი სიპი ბაზაში არ არსებობს!';
    }
}
