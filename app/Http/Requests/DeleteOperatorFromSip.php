<?php

namespace App\Http\Requests;

use App\Rules\OperatorExists;
use App\Rules\SipExists;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class DeleteOperatorFromSip extends FormRequest
{
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
            'sipNumber' => ['required', new SipExists(), 'string', 'digits_between:2,4', 'numeric']
        ];
    }

    public function messages() {
        return [
            'operatorID.required' => 'ოპერატორის ID აუცილებელი ველია!',
            'sipNumber.required' => 'სიპის ნომერი აუცილებელი ველია!',
            'sipNumber.max' => 'სიპის ნომერი მაქსიმუმ 4 სიმბოლო!'
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
