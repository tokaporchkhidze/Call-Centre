<?php

namespace App\Http\Requests;

use App\Rules\OperatorExists;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class DeleteOperator extends FormRequest {
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
            'userID' => ['required'],
            'needRemove' => ['required']
        ];
    }

    public function messages () {
        return [
            'operatorID.required' => 'ოპერატორის ID აუცილებელი ველია!'
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
