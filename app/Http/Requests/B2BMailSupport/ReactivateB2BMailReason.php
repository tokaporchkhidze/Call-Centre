<?php

namespace App\Http\Requests\B2BMailSupport;

use App\Rules\B2BMailSupport\ReactivateCheck;
use App\Rules\B2BMailSupport\ReasonNotExists;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class ReactivateB2BMailReason extends FormRequest {
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
            'reasonID' => ['bail', 'required', 'integer', new ReasonNotExists(), new ReactivateCheck()]
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
