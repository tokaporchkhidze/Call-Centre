<?php

namespace App\Rules\CRR;

use App\CRRReason;
use Illuminate\Contracts\Validation\Rule;

class UniqueReason implements Rule {

    private $skill;

    private $reasonID;

    /**
     * Create a new rule instance.
     *
     * @param $skill
     * @param $reasonID
     * @return void
     */
    public function __construct($skill, $reasonID=null) {
        $this->skill = $skill;
        $this->reasonID = $reasonID;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value) {
        $reason = CRRReason::where("reason", $value)->where("skill", $this->skill)->first();
        if(isset($this->reasonID) && isset($reason) && $reason->id == $this->reasonID) {
            return true;
        }
        if($reason != null) {
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
