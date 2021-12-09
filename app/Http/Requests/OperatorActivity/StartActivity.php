<?php

namespace App\Http\Requests\OperatorActivity;

use App\Rules\OperatorActivity\CheckActivity;
use App\Rules\OperatorActivity\IfOperatorOnline;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Rules\OperatorActivity\AlreadyInActivity;
use App\Rules\SipExists;
use Illuminate\Foundation\Http\FormRequest;

class StartActivity extends FormRequest
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
            'sipNumber' => ['required', 'numeric', new SipExists(), new AlreadyInActivity(), new IfOperatorOnline()],
            'activity' => ['required', 'string', new CheckActivity()]
        ];
    }

    public function messages() {
        return [
            'sipNumber.required' => 'სიპის ნომერი აუცილებელი ველია!',
            'sipNumber.numeric' => 'სიპის ნომერი უნდა იყოს რიცხვი!',
            'activity.required' => 'მიზეზი აუცილებელი ველია!',
        ];
    }

    protected function failedValidation(Validator $validator) {
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
