<?php

namespace App\Http\Requests\Pause;

use App\Rules\PauseSip\CheckSipStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class StorePause extends FormRequest {
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
        $rules = [
            'sipNumber' => ['required'],
            'pauseReason' => ['required_if:paused,true'],
            'paused' => ['required']
        ];
        if(strtolower($this->input('paused')) == "true") {
            $rules['sipNumber'][] = new CheckSipStatus($this->input('pauseReason'));
        }
        return $rules;
    }

    public function messages() {
        return [
            'sipNumber.required' => 'სიპის ნომერი აუცილებელია!',
            'pauseReason.required_if' => 'დაპაუზებისთვის მიზეზი აუცილებელია!',
            'paused.required' => 'ფუნქციისთვის ქმედების ტიპი აუცილებელია!'
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
