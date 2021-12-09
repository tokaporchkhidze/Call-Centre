<?php

namespace App\Http\Requests;

use App\Rules\UserNameCheck;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;


class StoreUser extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'userName' => ['required', 'min:4', 'max:45', new UserNameCheck()],
            'password' => ['required', 'min:8', 'max:45', 'confirmed'],
            'email' => ['required', 'email'],
            'firstName' => ['required'],
            'lastName' => ['required'],
            'templateID' => ['required', 'gt:0', 'numeric']
        ];
    }

    public function messages() {
        return [
            'userName.required' => 'მომხმარებლის სახელი აუცილებელი ველია',
            'userName.min' => 'მომხმარებლის სახელი უნდა იყოს მინიმუმ 4 სიმბოლო',
            'userName.max' => 'მომხმარებლის სახელი უნდა იყოს მაქსიმუმ 45 სიმბოლო',
            'email.required' => 'ელ-ფოსტა აუცილებელი ველია',
            'email.email' => 'არასწორი ელ-ფოსტის ფორმატი',
            'firstName.required' => 'სახელი აუცილებელი ველია',
            'firstName.min' => 'მინიმუმ 4 სიმბოლო',
            'firstName.max' => 'მაქსიმუმ 45',
            'lastName.required' => 'გვარი აუცილებელია',
            'lastName.min' => 'მინიმუმ 4 სიმბოლო',
            'lastName.max' => 'მაქსიმუმ 45 სიმბოლო',
            'password.required' => 'პაროლი აუცილებელი ველია',
            'password.min' => 'პაროლი უნდა იყოს მინიმუმ 8 სიმბოლო',
            'password.max' => 'პაროლი უნდა იყოს მაქსიმუმ 45 სიმბოლო',
            'password.confirmed' => 'პაროლი არ ემთხვევა!',
            'templateID.required' => 'template id is necessary field',
            'templateID.numeric' => 'template id must be integer',
            'templateID.gt' => 'template id must be > 0'
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
