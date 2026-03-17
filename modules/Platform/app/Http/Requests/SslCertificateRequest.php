<?php

namespace Modules\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Platform\Models\Secret;
use Modules\Platform\Services\DomainSslCertificateService;

class SslCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'certificate_authority' => [
                'required',
                'string',
                'in:'.implode(',', [
                    DomainSslCertificateService::CA_LETSENCRYPT,
                    DomainSslCertificateService::CA_ZEROSSL,
                    DomainSslCertificateService::CA_GOOGLE,
                    DomainSslCertificateService::CA_CUSTOM,
                ]),
            ],
            'is_wildcard' => ['nullable', 'boolean'],
            'domains' => ['nullable', 'array'],
            'domains.*' => ['nullable', 'string', 'max:255'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:issued_at'],
        ];

        // Private key and certificate are required on create, optional on update
        if ($isUpdate) {
            $rules['private_key'] = ['nullable', 'string'];
            $rules['certificate'] = ['nullable', 'string'];
        } else {
            $rules['private_key'] = ['required', 'string'];
            $rules['certificate'] = ['required', 'string'];
        }

        $rules['ca_bundle'] = ['nullable', 'string'];

        return $rules;
    }

    public function fieldLabels(): array
    {
        return [
            'name' => 'Certificate Name',
            'certificate_authority' => 'Certificate Authority',
            'is_wildcard' => 'Wildcard Certificate',
            'domains' => 'Covered Domains',
            'private_key' => 'Private Key',
            'certificate' => 'Certificate',
            'ca_bundle' => 'CA Bundle (Chain)',
            'issuer' => 'Issuer',
            'issued_at' => 'Issue Date',
            'expires_at' => 'Expiry Date',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Certificate name is required.',
            'name.max' => 'Certificate name cannot exceed 255 characters.',
            'certificate_authority.required' => 'Please select a Certificate Authority.',
            'certificate_authority.in' => 'Invalid Certificate Authority selected.',
            'private_key.required' => 'Private key is required when creating a certificate.',
            'certificate.required' => 'Certificate is required when creating a certificate.',
            'expires_at.after' => 'Expiry date must be after the issue date.',
        ];
    }

    /**
     * Get custom validation logic after standard validation passes.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            // Validate PEM format for private key
            if ($this->filled('private_key') && ! $this->isValidPemFormat($this->private_key, 'PRIVATE KEY')) {
                $validator->errors()->add('private_key', 'The private key must be in valid PEM format.');
            }

            // Validate PEM format for certificate
            if ($this->filled('certificate') && ! $this->isValidPemFormat($this->certificate, 'CERTIFICATE')) {
                $validator->errors()->add('certificate', 'The certificate must be in valid PEM format.');
            }

            // Validate CA bundle if provided
            if ($this->filled('ca_bundle') && ! $this->isValidPemFormat($this->ca_bundle, 'CERTIFICATE')) {
                $validator->errors()->add('ca_bundle', 'The CA bundle must be in valid PEM format.');
            }

            // Validate that private key matches the certificate (only if both provided)
            if ($this->filled('private_key') && $this->filled('certificate')) {
                $service = resolve(DomainSslCertificateService::class);
                if (! $service->validateKeyPair($this->private_key, $this->certificate)) {
                    $validator->errors()->add('private_key', 'The private key does not match the certificate.');
                }
            }
        });
    }

    protected function getModelClass(): string
    {
        return Secret::class;
    }

    protected function getRouteParameter(): string
    {
        return 'certificate';
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert is_wildcard checkbox to boolean
        if ($this->has('is_wildcard')) {
            $this->merge([
                'is_wildcard' => filter_var($this->is_wildcard, FILTER_VALIDATE_BOOLEAN),
            ]);
        }

        // Convert domains string to array if provided as comma-separated
        if ($this->has('domains') && is_string($this->domains)) {
            $domains = array_filter(array_map(trim(...), explode(',', $this->domains)));
            $this->merge(['domains' => $domains]);
        }
    }

    /**
     * Check if a string is in valid PEM format.
     */
    protected function isValidPemFormat(string $content, string $type): bool
    {
        $pattern = '/-----BEGIN\s+.*'.preg_quote($type, '/').'-----/';

        return preg_match($pattern, $content) === 1;
    }
}
