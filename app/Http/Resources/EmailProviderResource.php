<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Definitions\EmailProviderDefinition;
use App\Enums\Status;
use App\Models\EmailProvider;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use RuntimeException;

class EmailProviderResource extends ScaffoldResource
{
    // ================================================================
    // REQUIRED METHOD
    // ================================================================

    protected function definition(): ScaffoldDefinition
    {
        return new EmailProviderDefinition;
    }

    protected function baseAttributeKeys(): ?array
    {
        if (! $this->isIndexPayloadRequest()) {
            return parent::baseAttributeKeys();
        }

        return [
            'name',
            'sender_email',
            'smtp_host',
            'status',
            'order',
        ];
    }

    protected function formattedDateKeys(): array
    {
        if (! $this->isIndexPayloadRequest()) {
            return parent::formattedDateKeys();
        }

        return ['updated_at'];
    }

    protected function getFormattedDates(): array
    {
        if (! $this->isIndexPayloadRequest()) {
            return parent::getFormattedDates();
        }

        if (! $this->resource->updated_at) {
            return [];
        }

        return [
            'updated_at' => $this->resource->updated_at->toISOString(),
        ];
    }

    // ================================================================
    // CUSTOM FIELDS FOR DATAGRID
    // ================================================================

    protected function customFields(): array
    {
        $provider = $this->emailProvider();
        $status = $provider->getAttribute('status');
        $statusValue = $status instanceof Status ? $status->value : (string) ($status ?? 'inactive');
        $isIndexPayload = $this->isIndexPayloadRequest();

        $data = [
            'show_url' => route($this->scaffold()->getRoutePrefix().'.show', $provider->getKey()),
            'sender_name' => $provider->getAttribute('sender_name'),
            'smtp_encryption' => $provider->getAttribute('smtp_encryption')
                ? strtoupper((string) $provider->getAttribute('smtp_encryption'))
                : 'None',
            'status_label' => $this->getStatusLabel($statusValue),
            'status_class' => $this->getStatusClass($statusValue),
        ];

        if (! $isIndexPayload) {
            $data = [
                ...$data,
                'description' => $provider->getAttribute('description'),
                'smtp_user' => $provider->getAttribute('smtp_user'),
                'smtp_port' => $provider->getAttribute('smtp_port'),
                'reply_to' => $provider->getAttribute('reply_to'),
                'bcc' => $provider->getAttribute('bcc'),
                'signature' => $provider->getAttribute('signature'),
            ];
        }

        return $data;
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
