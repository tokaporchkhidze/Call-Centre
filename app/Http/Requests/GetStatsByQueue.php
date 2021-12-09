<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class GetStatsByQueue extends FormRequest
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
            'queueArr' => ['required', 'array'],
            'startDate' => ['required', 'date_format:Y-m-d H:i:s'],
            'endDate' => ['required', 'date_format:Y-m-d H:i:s'],
        ];
    }

    public function messages() {
        return [
            'queueName.required' => 'რიგი აუცილებელი ველია!',
            'startDate.required' => 'საწყისი თარიღი აუციელებელი ველია!',
            'startDate.date_format' => 'საწყისი თარიღის ფორმატი უნდა იყოს - Y-m-d H:i:s!',
            'endDate.required' => 'დასრულების თარიღი აუცილებელი ველია!',
            'endDate.date_format' => 'დასრულების თარიღის ფორმატი უნდა იყოს - Y-m-d H:i:s!'
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
