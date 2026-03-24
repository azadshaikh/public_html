<?php

declare(strict_types=1);

namespace Modules\Agency\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DomainStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isSubdomain = $this->input('domain_type') === 'subdomain';

        return [
            'domain_type' => ['required', Rule::in(['subdomain', 'custom'])],
            'subdomain' => $isSubdomain
                ? [
                    'required',
                    'string',
                    'min:3',
                    'max:63',
                    'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9]$|^[a-zA-Z0-9]$/',
                ]
                : ['nullable'],
            'custom_domain' => $isSubdomain
                ? ['nullable']
                : [
                    'required',
                    'string',
                    'max:255',
                    'regex:/^[a-zA-Z0-9][a-zA-Z0-9\-_.]+\.[a-zA-Z]{2,}$/',
                ],
            'dns_mode' => $isSubdomain
                ? ['nullable']
                : ['required', Rule::in(['managed', 'external'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'domain_type.required' => 'Please select a domain option.',
            'subdomain.required' => 'Please enter a subdomain name.',
            'subdomain.min' => 'Subdomain must be at least 3 characters.',
            'subdomain.max' => 'Subdomain may not exceed 63 characters.',
            'subdomain.regex' => 'Subdomain may only contain letters, numbers, and hyphens, and cannot start or end with a hyphen.',
            'custom_domain.required' => 'Please enter your domain name.',
            'custom_domain.regex' => 'Please enter a valid domain (e.g., example.com).',
            'dns_mode.required' => 'Please select how you want to manage DNS for your domain.',
            'dns_mode.in' => 'Invalid DNS management option.',
        ];
    }

    /**
     * Resolve the full domain from the validated input.
     */
    public function resolvedDomain(): string
    {
        if ($this->input('domain_type') === 'subdomain') {
            $freeSubdomain = config('agency.free_subdomain', '');

            return strtolower((string) $this->input('subdomain')).'.'.$freeSubdomain;
        }

        return strtolower((string) $this->input('custom_domain'));
    }
}
