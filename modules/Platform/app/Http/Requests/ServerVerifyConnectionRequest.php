<?php

namespace Modules\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServerVerifyConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ip' => ['required', 'ip'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_private_key' => ['required', 'string'],
        ];
    }
}
