<?php

namespace Modules\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ServerTestConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ip' => ['nullable', 'ip'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_user' => ['nullable', 'string', 'max:50'],
            'ssh_private_key' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasDraftSshInput = $this->hasAny(['ip', 'ssh_port', 'ssh_user', 'ssh_private_key']);

            if (! $hasDraftSshInput) {
                return;
            }

            if (! $this->filled('ip')) {
                $validator->errors()->add('ip', 'The IP field is required when testing draft SSH credentials.');
            }

            if (! $this->filled('ssh_private_key')) {
                $validator->errors()->add('ssh_private_key', 'The SSH private key field is required when testing draft SSH credentials.');
            }
        });
    }
}
