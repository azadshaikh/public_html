<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Definitions\EmailProviderDefinition;
use App\Models\EmailProvider;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;

class EmailProviderRequest extends ScaffoldRequest
{
    // ================================================================
    // VALIDATION RULES
    // ================================================================

    public function rules(): array
    {
        return [
            // Always required fields
            'name' => ['required', 'string', 'max:255'],
            'sender_name' => ['required', 'string', 'max:255'],
            'sender_email' => ['required', 'email', 'max:255'],
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_user' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'string', 'max:10'],
            'smtp_encryption' => ['required', 'string', 'in:tls,ssl,none'],

            // Password - required on create, optional on update
            'smtp_password' => $this->isUpdate()
                ? ['nullable', 'string', 'max:255']
                : ['required', 'string', 'max:255'],

            // Optional fields
            'description' => ['nullable', 'string', 'max:1000'],
            'reply_to' => ['nullable', 'email', 'max:255'],
            'bcc' => ['nullable', 'email', 'max:255'],
            'signature' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    // ================================================================
    // FRIENDLY FIELD NAMES (for error messages)
    // ================================================================

    public function attributes(): array
    {
        return [
            'name' => 'Provider Name',
            'description' => 'Description',
            'sender_name' => 'Sender Name',
            'sender_email' => 'Sender Email',
            'smtp_host' => 'SMTP Host',
            'smtp_user' => 'SMTP Username',
            'smtp_password' => 'SMTP Password',
            'smtp_port' => 'SMTP Port',
            'smtp_encryption' => 'Encryption Type',
            'reply_to' => 'Reply-To Email',
            'bcc' => 'BCC Email',
            'signature' => 'Email Signature',
            'status' => 'Status',
            'order' => 'Sort Order',
        ];
    }

    // ================================================================
    // REQUIRED METHODS
    // ================================================================

    protected function definition(): ScaffoldDefinition
    {
        return new EmailProviderDefinition;
    }

    protected function getModelClass(): string
    {
        return EmailProvider::class;
    }

    // ================================================================
    // PREPARE DATA BEFORE VALIDATION
    // ================================================================

    protected function prepareForValidation(): void
    {
        // Trim whitespace from text fields
        $this->trimField('name');
        $this->trimField('description');
        $this->trimField('sender_name');
        $this->trimField('sender_email');
        $this->trimField('smtp_host');
        $this->trimField('smtp_user');
        $this->trimField('smtp_password');
        $this->trimField('smtp_port');
        $this->trimField('smtp_encryption');
        $this->trimField('reply_to');
        $this->trimField('bcc');
        $this->trimField('signature');
    }
}
