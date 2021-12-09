<?php

namespace App\Http\Requests;

use App\Logging\Logger;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreTemplate extends FormRequest
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
        $rules = [
            'templateName' => ['required'],
            'templateDisplayName' => ['required'],
            'queues' => ['required', 'array'],
            'tasks' => ['required', 'array'],
        ];
        $inputArr = $this->request->all();
        foreach($inputArr['queues'] as $key => $value) {
            $rules["queues.".$key] = ['required', 'numeric', 'gt:0'];
        }
        foreach($inputArr['tasks'] as $key => $value) {
            $rules["tasks.".$key] = ['required', 'array'];
        }
        return $rules;
    }

    public function messages() {
        $messages = [
            'templateName.required' => 'შაბლონის ბაზის სახელი აუცილებელი ველია',
            'templateDisplayName.required' => 'შაბლონის გამოსაჩენი სახელი აუცილებელი ველია',
            'queues.required' => 'რიგი აუცილებელი ველია',
            'queues.array' => 'რიგები უნდა გადმოეცეს მასივის ფორმატში',
            'tasks.array' => 'ტასკები უნდა გადმოეცეს მასივის ფორმატში',
            'tasks.required' => 'ტასკი აუცილებელი ველია'
        ];
        $inputArr = $this->request->all();
        foreach($inputArr['queues'] as $key => $value) {
            $messages['queues.'.$key.'.required'] = 'რიგის id არ შეიძლება ცარიელი იყოს!!!';
            $messages['queues.'.$key.'.numeric'] = 'რიგის id უნდა იყოს რიცხვი/ციფრი';
            $messages['queues.'.$key.'gt'] = 'რიგის id მეტი უნდა იყოს 0_ზე';
        }
        foreach($inputArr['tasks'] as $key => $value) {
            $messages['tasks.'.$key.'.required'] = 'ტასკის id არ შეიძლება ცარიელი იყოს!!!';
            $messages['tasks.'.$key.'.array'] = 'ტასკის id უნდა იყოს რიცხვი/ციფრი';
            $messages['tasks.'.$key.'gt'] = 'ტასკის id მეტი უნდა იყოს 0_ზე';
        }
        return $messages;
    }

    protected function failedValidation(Validator $validator) {
        $extra = [
            'input' => request()->input(),
            'response_msg' => $validator->errors()->all()
        ];
        throw new HttpResponseException(response()->json($validator->errors()->all(), config('errorCodes.HTTP_UNPROCESSABLE_ENTITY')));
    }

}
