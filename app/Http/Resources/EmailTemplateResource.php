<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Definitions\EmailTemplateDefinition;
use App\Enums\Status;
use App\Models\EmailProvider;
use App\Models\EmailTemplate;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use App\Traits\DateTimeFormattingTrait;
use RuntimeException;

class EmailTemplateResource extends ScaffoldResource
{
    use DateTimeFormattingTrait;

    protected function definition(): ScaffoldDefinition
    {
        return new EmailTemplateDefinition;
    }

    protected function customFields(): array
    {
        $template = $this->emailTemplate();
        $provider = $template->relationLoaded('provider') ? $template->getRelation('provider') : null;

        $status = $template->getAttribute('status');
        $statusValue = $status instanceof Status ? $status->value : (string) ($status ?? 'inactive');
        $statusLabel = $template->getAttribute('status_label');
        $statusClass = $template->getAttribute('status_class');
        $name = (string) ($template->getAttribute('name') ?? '');
        $subject = (string) ($template->getAttribute('subject') ?? '');

        $data = [
            // URL for row link to show page
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $template->getKey()),

            'name' => $template->getAttribute('name'),
            'subject' => $template->getAttribute('subject'),
            'message' => $template->getAttribute('message'),
            'send_to' => $template->getAttribute('send_to'),
            'provider' => $this->whenLoaded('provider', fn (): array => [
                'id' => $provider?->id,
                'name' => $provider?->name,
            ]),
            'provider_name' => $provider instanceof EmailProvider ? $provider->name : 'N/A',
            'status' => $statusValue,
            'status_label' => is_string($statusLabel) ? $statusLabel : ucfirst(str_replace('_', ' ', $statusValue)),
            'status_class' => is_string($statusClass) ? $statusClass : 'bg-secondary-subtle text-secondary',
            'is_raw' => (bool) $template->getAttribute('is_raw'),
            'template_info' => $name !== '' ? ($subject !== '' ? sprintf('%s - %s', $name, $subject) : $name) : $subject,

            // Datetime fields
            'created_at' => $template->getAttribute('created_at'),
            'updated_at' => $template->getAttribute('updated_at'),
        ];

        return $this->formatDateTimeFields(
            $data,
            datetimeFields: ['created_at', 'updated_at']
        );
    }

    private function emailTemplate(): EmailTemplate
    {
        throw_unless($this->resource instanceof EmailTemplate, RuntimeException::class, 'EmailTemplateResource expects an EmailTemplate model instance.');

        return $this->resource;
    }
}
