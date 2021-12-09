<?php

namespace App\Http\Requests;

use App\Rules\OperatorExists;
use App\Rules\SipExists;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class TransferOperatorToSip extends FormRequest {
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize() {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules() {
        return [
            'operatorID' => ['required', new OperatorExists()],
            'currentSip' => ['required', new SipExists(), 'string', 'max:4'],
            'newSip' => ['required', new SipExists()]
        ];
    }

    public function messages() {
        return [
            'operatorID.required' => 'ოპერატორის ID აუცილებელი ველია!',
            'currentSip.required' => 'არსებული სიპის ნომერი აუციებელი ველია!',
            'newSip.required' => 'ახალი სიპის ნომერი აუცილებელი ველია!'
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
