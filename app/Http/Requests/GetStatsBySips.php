<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GetStatsBySips extends FormRequest
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
            'sips' => ['required', 'array'],
            'startDate' => ['required', 'date_format:Y-m-d H:i:s'],
            'endDate' => ['required', 'date_format:Y-m-d H:i:s'],
        ];
    }

    public function messags() {
        return [
            'sips.required' => 'სიპი აუცილებელი ველია!',
            'sips.array' => 'სიპის ველი უნდა იყოს მასივის ტიპის!',
            'startDate.required' => 'საწყისი თარიღი აუციელებელი ველია!',
            'startDate.date_format' => 'საწყისი თარიღის ფორმატი უნდა იყოს - Y-m-d H:i:s!',
            'endDate.required' => 'დასრულების თარიღი აუცილებელი ველია!',
            'endDate.date_format' => 'დასრულების თარიღის ფორმატი უნდა იყოს - Y-m-d H:i:s!'
        ];
    }

}
