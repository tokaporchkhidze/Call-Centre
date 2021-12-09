<?php

namespace App\Rules\BlackList;

use App\BlackList\BlackList;
use Illuminate\Contracts\Validation\Rule;

class CheckNumberInBlackList implements Rule
{

    private $requestType;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($requestType) {
        $this->requestType = $requestType;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value) {
        $number = BlackList::where("number", $value)->where("removed", null)->first();
        if($this->requestType == "add") {
            if(isset($number)) {
                return false;
            } else {
                return true;
            }
        } else {
            if(isset($number)) {
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message() {
        if($this->requestType == "add") {
            return 'ნომერი უკვე შავ სიაშია!';
        } else {
            return 'ნომერი არ არის შავ სიაში!';
        }
    }
}
