<?php

namespace App\Http\Requests;

use App\Logging\Logger;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class GetTemplate extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'templateId' => ['required', 'numeric', 'gt:0']
        ];
    }

    public function messages() {
        return [
            'templateId.required' => 'template id is necessary field',
            'templateId.numeric' => 'template id must be integer',
            'templateId.gt' => 'template id must be > 0'
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
