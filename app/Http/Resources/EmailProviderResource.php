<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Definitions\EmailProviderDefinition;
use App\Enums\Status;
use App\Models\EmailProvider;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use App\Traits\DateTimeFormattingTrait;
use RuntimeException;

class EmailProviderResource extends ScaffoldResource
{
    use DateTimeFormattingTrait;

    // ================================================================
    // REQUIRED METHOD
    // ================================================================

    protected function definition(): ScaffoldDefinition
    {
        return new EmailProviderDefinition;
    }

    // ================================================================
    // CUSTOM FIELDS FOR DATAGRID
    // ================================================================

    protected function customFields(): array
    {
        $provider = $this->emailProvider();
        $status = $provider->getAttribute('status');
        $statusValue = $status instanceof Status ? $status->value : (string) ($status ?? 'inactive');

        $data = [
            // URL for row link to show page
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $provider->getKey()),

            // Basic fields
            'name' => $provider->getAttribute('name'),
            'description' => $provider->getAttribute('description'),
            'sender_name' => $provider->getAttribute('sender_name'),
            'sender_email' => $provider->getAttribute('sender_email'),

            // SMTP configuration
            'smtp_host' => $provider->getAttribute('smtp_host'),
            'smtp_user' => $provider->getAttribute('smtp_user'),
            'smtp_port' => $provider->getAttribute('smtp_port'),
            'smtp_encryption' => $provider->getAttribute('smtp_encryption')
                ? strtoupper((string) $provider->getAttribute('smtp_encryption'))
                : 'None',

            // Email settings
            'reply_to' => $provider->getAttribute('reply_to'),
            'bcc' => $provider->getAttribute('bcc'),
            'signature' => $provider->getAttribute('signature'),

            // Status fields (for badge template)
            'status' => $statusValue,
            'status_label' => $this->getStatusLabel($statusValue),
            'status_class' => $this->getStatusClass($statusValue),

            // Order
            'order' => $provider->getAttribute('order') ?? 0,

            // Datetime fields
            'created_at' => $provider->getAttribute('created_at'),
            'updated_at' => $provider->getAttribute('updated_at'),
        ];

        return $this->formatDateTimeFields(
            $data,
            datetimeFields: ['created_at', 'updated_at']
        );
    }

    // ================================================================
    // HELPER METHODS
    // ================================================================

    /**
     * Get status label for display
     */
    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            'inactive' => 'Inactive',
            default => 'Unknown',
        };
    }

    /**
     * Get status CSS class for badges
     */
    protected function getStatusClass(string $status): string
    {
        return match ($status) {
            'active' => 'bg-success-subtle text-success',
            'inactive' => 'bg-secondary-subtle text-secondary',
            default => 'bg-secondary-subtle text-secondary',
        };
    }

    private function emailProvider(): EmailProvider
    {
        throw_unless($this->resource instanceof EmailProvider, RuntimeException::class, 'EmailProviderResource expects an EmailProvider model instance.');

        return $this->resource;
    }
}
