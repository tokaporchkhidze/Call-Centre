<?php

namespace App\Rules\CRR;

use App\CRRSuggestion;
use Illuminate\Contracts\Validation\Rule;

class UniqueSuggestion implements Rule
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
        if(CRRSuggestion::where('suggestion', $value)->first() != null) {
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
        return 'ასეთი CRR suggestion უკვე არსებობს';
    }
}
