<?php

namespace App\Http\Requests\CRR;


use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class GetCallsWithCRR extends FormRequest
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

            'startDate' => ['required', 'date_format:Y-m-d H:i:s'],
            'endDate' => ['required', 'date_format:Y-m-d H:i:s']
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
