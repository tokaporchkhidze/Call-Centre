<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class CheckPhone implements Rule
{
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
        if( (strlen($value) > 9 and substr($value, 0, 3) == "995") or is_numeric($value) === false) {
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
        return 'ნომერი შეიყვანეთ სწორი ფორმატით!';
    }
}
