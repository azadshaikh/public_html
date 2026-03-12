<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SiteAccessProtectionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.required' => __('general.password_required'),
        ];
    }

    /**
     * Validate the password against the configured site access protection password.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $enteredPassword = $validator->getData()['password'] ?? '';
            $configuredPassword = setting(
                'site_access_protection_password',
                setting('password_protected_password', '')
            );

            if ($enteredPassword !== $configuredPassword) {
                $validator->errors()->add('password', __('general.invalid_password'));
            }
        });
    }
}
