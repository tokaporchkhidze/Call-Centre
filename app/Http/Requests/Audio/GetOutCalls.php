<?php

namespace App\Http\Requests\Audio;

use App\Rules\QueueExists;
use App\Rules\SipExists;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class GetOutCalls extends FormRequest
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
            'queueName' => ['nullable', 'string', new QueueExists()],
            'sipNumber' => ['nullable', 'string', new SipExists()],
            'caller' => ['nullable', 'string'],
            'startDate' => ['required', 'date_format:Y-m-d H:i:s'],
            'endDate' => ['required', 'date_format:Y-m-d H:i:s'],
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
