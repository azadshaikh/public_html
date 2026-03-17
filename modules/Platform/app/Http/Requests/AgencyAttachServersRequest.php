<?php

namespace Modules\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AgencyAttachServersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'server_ids' => ['required', 'array'],
            'server_ids.*' => ['integer', 'exists:platform_servers,id'],
            'primary_server_id' => ['nullable', 'integer', 'exists:platform_servers,id'],
        ];
    }
}
