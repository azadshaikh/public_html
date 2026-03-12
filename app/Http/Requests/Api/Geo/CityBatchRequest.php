<?php

namespace App\Http\Requests\Api\Geo;

use Illuminate\Foundation\Http\FormRequest;

class CityBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'min:1'],
        ];
    }
}
