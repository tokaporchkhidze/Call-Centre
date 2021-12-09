<?php

namespace App\Http\Requests\PasswordReset;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class ResetPassword extends FormRequest {
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
            'userID' => ['required', 'integer'],
            'password' => ['required', 'min:8', 'max:45', 'confirmed'],
        ];
    }

    public function messages() {
        return [
            'userID.required' => ['მომხმარებლის ID აუცილებელია!'],
            'password.required' => 'პაროლი აუცილებელი ველია',
            'password.min' => 'პაროლი უნდა იყოს მინიმუმ 8 სიმბოლო',
            'password.max' => 'პაროლი უნდა იყოს მაქსიმუმ 45 სიმბოლო',
            'password.confirmed' => 'პაროლი არ ემთხვევა!',
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
