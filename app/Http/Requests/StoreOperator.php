<?php

namespace App\Http\Requests;

use App\Rules\SipExists;
use App\Rules\UserNameCheck;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class StoreOperator extends FormRequest
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
            'firstName' => ['required', 'string'],
            'lastName' => ['required', 'string'],
            'trainee' => ['required', 'integer', 'max:1', 'min:0'],
            'operatorCardNum' => ['nullable', 'string', 'size:11'],
            'sipNumber' => ['nullable', 'string', 'max:4', new SipExists()],
            'userName' => ['required_without:userID', 'min:4', 'max:45', new UserNameCheck()],
            'password' => ['required_without:userID', 'min:8', 'max:45', 'confirmed'],
            'email' => ['required_without:userID', 'email'],
            'userID' => ['nullable']
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
