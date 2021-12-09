<?php

namespace App\Rules;

use App\Operator;
use Illuminate\Contracts\Validation\Rule;

class OperatorExists implements Rule
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
        if(strtolower($attribute) == "pid") {
            $operator = Operator::where('personal_id', $value)->first();
        } else {
            $operator = Operator::find($value);
        }
        if($operator == null) return false;
        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message() {
        return 'ასეთი ოპერატორი არ არსებობს!';
    }
}
