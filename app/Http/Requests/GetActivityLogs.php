<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class GetActivityLogs extends FormRequest
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
            'userName' => ['required_without_all:action,IP,startDate,endDate', 'string'],
            'action' => ['required_without_all:userName,IP,startDate,endDate', 'string'],
            'IP' => ['required_without_all:userName,action,startDate,endDate', 'ipv4'],
            'startDate' => ['required_without_all:userName,action,IP,endDate', 'date_format:Y-m-d H:i:s'],
            'endDate' => ['required_without_all:userName,action,IP,startDate', 'date_format:Y-m-d H:i:s'],
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
