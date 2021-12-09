<?php

namespace App\Http\Requests\CRR;

use App\Rules\CheckLang;
use App\Rules\CheckSkill;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class StoreCRR extends FormRequest
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
            'CRRUniqueID' => ['required'],
            'reason' => ['required', 'integer'],
            'suggestion' => ['nullable', 'integer'],
            'uniqueID' => ['required'],
            'realCaller' => ['required'],
            'skill' => ['required', new CheckSkill()],
            'language' => ['required', new CheckLang()]
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
