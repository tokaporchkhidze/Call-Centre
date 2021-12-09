<?php

namespace App\Http\Requests\CRR;

use App\Rules\CRR\SuggestionNotExists;
use App\Rules\CRR\UniqueSuggestion;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class EditSuggestion extends FormRequest
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
            'id' => ['required', 'integer', new SuggestionNotExists()],
            'newSuggestion' => ['required', 'string', new UniqueSuggestion()]
        ];
    }

    public function messages() {
        return [
            'id.required' => 'ID აუცილებელი ველია!',
            'newSuggestion.required' => 'ახალი მიზეზი აუცილებელი ველია!'
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }
}
