<?php

namespace App\Http\Requests\BlackList;


use App\Rules\BlackList\CheckNumberInBlackList;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;


class RemoveNumberFromBlackList extends FormRequest
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
            'number' => ['required', new CheckNumberInBlackList("remove")]
        ];
    }

    public function messages() {
        return [
            'number.required' => 'აბონენტის ნომერი აუცილებელია!'
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
