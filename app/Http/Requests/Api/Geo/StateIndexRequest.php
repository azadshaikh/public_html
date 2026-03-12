<?php

namespace App\Http\Requests\Api\Geo;

use Illuminate\Foundation\Http\FormRequest;

class StateIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'country_code' => ['required', 'string', 'size:2'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'offset' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
