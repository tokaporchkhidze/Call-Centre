<?php

namespace App\Http\Requests\PasswordReset;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class InitiatePasswordReset extends FormRequest {
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
            'userName' => ['bail', 'required_without:email'],
            'email' => ['bail', 'required_without:userName', 'email']
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
