<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Rules\OperatorExists;
use Illuminate\Foundation\Http\FormRequest;

class StoreSip extends FormRequest {
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
            'sipNumber' => ['required', 'numeric', 'digits_between:3,4'],
            'templateName' => ['required'],
            'operatorID' => ['nullable', new OperatorExists()]
        ];
    }

    public function messages() {
        return [
            'sipNumber.required' => 'სიპის ნომერი აუცილებელი ველია',
            'sipNumber.max' => 'მაქსიმუმ 4 სიმბოლო',
            'templateName.required' => 'შაბლონის სახელი აუცილებელი ველია',
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
