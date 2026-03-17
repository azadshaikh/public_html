<?php

namespace Modules\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServerAttachAgenciesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agency_ids' => ['required', 'array'],
            'agency_ids.*' => ['integer', 'exists:platform_agencies,id'],
            'primary_agency_id' => ['nullable', 'integer', 'exists:platform_agencies,id'],
        ];
    }
}
