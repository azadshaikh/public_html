<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage_platform_settings');
    }

    public function rules(): array
    {
        return [
            'trail_server_id' => ['required', 'integer'],
            'default_sub_domain' => ['required', 'string', 'max:255'],
            'default_domain_ssl_key' => ['required', 'string'],
            'default_domain_ssl_crt' => ['required', 'string'],
            'default_ssl_expiry' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'trail_server_id.required' => 'Trial Server is required',
            'default_sub_domain.required' => 'Trial Domain is required',
            'default_domain_ssl_key.required' => 'Domain SSL Key is required',
            'default_domain_ssl_crt.required' => 'Domain SSL Certificate is required',
            'default_ssl_expiry.required' => 'Default SSL Expiry is required',
            'default_ssl_expiry.date' => 'Default SSL Expiry must be a date',
        ];
    }
}
