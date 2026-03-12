<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Definitions\EmailTemplateDefinition;
use App\Models\EmailTemplate;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;

class EmailTemplateRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        $this->prepareBooleanField('is_raw');

        return [
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'send_to' => ['nullable', 'string', 'max:500'],
            'provider_id' => ['required', $this->existsRule('email_providers', 'id')],
            'is_raw' => ['nullable', 'boolean'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'Template Name',
            'subject' => 'Email Subject',
            'message' => 'Message Content',
            'send_to' => 'Send To',
            'provider_id' => 'Email Provider',
            'is_raw' => 'Send as Raw HTML',
            'status' => 'Status',
        ];
    }

    protected function definition(): ScaffoldDefinition
    {
        return new EmailTemplateDefinition;
    }

    protected function getModelClass(): string
    {
        return EmailTemplate::class;
    }
}
