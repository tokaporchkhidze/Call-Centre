<?php

namespace App\Http\Requests\Pause;


use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class StorePauseInPause extends FormRequest
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
            'sipNumber' => ['required'],
            'pauseReason' => ['required'],
        ];
    }

    public function messages() {
        return [
            'sipNumber.required' => 'სიპის ნომერი აუცილებელია!',
            'pauseReason.required' => 'დაპაუზებისთვის მიზეზი აუცილებელია!',
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
