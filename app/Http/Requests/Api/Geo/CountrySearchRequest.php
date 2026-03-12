<?php

namespace App\Http\Requests\Api\Geo;

use Illuminate\Foundation\Http\FormRequest;

class CountrySearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:1', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'offset' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
