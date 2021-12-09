<?php

namespace App\Http\Requests\CRR;

use App\CRRReason;
use App\Rules\CRR\ReasonNotExists;
use App\Rules\CRR\SameReasonNotAllowed;
use App\Rules\CRR\UniqueReason;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Foundation\Http\FormRequest;

class EditReason extends FormRequest {
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
        $skill = CRRReason::find($this->input('id'))->skill ?? null;
        return [
            'id' => ['bail', 'required', 'integer', new ReasonNotExists()],
            'newReason' => ['bail', 'required', 'string', new SameReasonNotAllowed($skill), new UniqueReason($skill, $this->input('id'))],
            'isUnwanted' => ['required']
        ];
    }

    public function messages() {
        return [
            'id.required' => 'ID აუცილებელი ველია!'
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
