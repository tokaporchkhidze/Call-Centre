<?php

namespace App\Rules;

use App\Queue;
use Illuminate\Contracts\Validation\Rule;

class QueueExists implements Rule
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
        if(Queue::where('name', $value)->first() == null) {
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
        return 'ასეთი რიგი ბაზაში არ არსებობს!';
    }
}
