<?php

namespace App\Http\Requests\B2BMailSupport;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class StoreB2BMail extends FormRequest {
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
            'email' => ['required', 'email'],
            'gsm' => ['required'],
            'companyName' => ['required', 'string'],
            'comment' => ['nullable', 'string'],
            'reasonID' => ['required', 'integer'],
            'operatorID' => ['required', 'integer']
        ];
    }

    public function messages() {
        return [
            'email.required' => 'ელ.ფოსტა აუცილებელია!',
            'email.email' => 'ელ.ფოსტის ფორმატი არასწორია!',
            'gsm.required' => 'მობილურის ნომერი აუცილებელია!',
            'companyName.required' => 'კომპანიის სახელი აუცილებელია!',
            'reasonID.required' => 'მიზეზი აუცილებელია!',
            'operatorID.required' => 'ოპერატორი აუცილებელია!',
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
