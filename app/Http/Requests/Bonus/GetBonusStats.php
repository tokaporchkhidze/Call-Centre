<?php

namespace App\Http\Requests\Bonus;

use App\Rules\OperatorExists;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class GetBonusStats extends FormRequest
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
            'pid' => ['required', 'string', new OperatorExists()],
            'start' => ['required', 'date_format:Y-m-d H:i'],
            'end' => ['required', 'date_format:Y-m-d H:i'],
        ];
    }

    public function messages() {
        return [
            'pid.required' => 'Need personal ID',
            'start.required' => 'Need start date',
            'start.date_format' => 'correct format is: Y-m-d H:i',
            'end.required' => 'Need end date',
            'end.date_format' => 'correct format is: Y-m-d H:i',
        ];
    }

    protected function failedValidation(Validator $validator) {
        $responseArr = [
          'status' => false,
          'message' => $validator->errors()->first(),
          'data' => array(),
        ];
        throw new HttpResponseException(response()->json($responseArr, config('errorCodes.HTTP_SUCCESS')));
    }

}
