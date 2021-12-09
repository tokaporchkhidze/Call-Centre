<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class CheckLang implements Rule {
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct() {
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
        if($value == "ENG" or $value == "RUS" or $value == "GEO" or $value == "OTHER") {
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
    public function message() {
        return 'ენის მნიშვნელობა შეიძლება იყოს - \'ENG\',\'RUS\',\'GEO\, \'OTHER\'';
    }
}
