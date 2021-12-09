<?php

namespace App\Http\Requests\CRR;

use App\Rules\CRR\UniqueReason;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class StoreReason extends FormRequest {
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
            'reason' => ['bail', 'required', 'string', new UniqueReason($this->input('skill') ?? null)],
            'skill' => ['required', 'string'],
            'isUnwanted' => ['required']
        ];
    }

    public function messages() {
        return [
            'reason.required' => 'მიზეზი აუცილებელი ველია!',
            'skill.required' => 'სქილი აუცილებელი ველია!',
            'isUnwanted.required' => 'მიზეზის კატეგორია აუცილებელია!'
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
