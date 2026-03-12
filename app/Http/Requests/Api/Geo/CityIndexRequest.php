<?php

namespace App\Http\Requests\Api\Geo;

use Illuminate\Foundation\Http\FormRequest;

class CityIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'country_code' => ['nullable', 'string', 'size:2'],
            // ISO 3166-2 (e.g. "US-CA"), length varies
            'state_code' => ['nullable', 'string', 'min:3', 'max:10'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'offset' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $countryCode = $this->input('country_code');
            $stateCode = $this->input('state_code');

            if (! $countryCode && ! $stateCode) {
                $validator->errors()->add('location', 'Please provide either country_code or state_code parameter');
            }
        });
    }
}
