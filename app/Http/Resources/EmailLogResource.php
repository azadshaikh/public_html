<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\EmailLog;
use App\Models\EmailProvider;
use App\Models\EmailTemplate;
use App\Support\Auth\SuperUserAccess;
use App\Traits\DateTimeFormattingTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RuntimeException;

class EmailLogResource extends JsonResource
{
    use DateTimeFormattingTrait;

    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $emailLog = $this->emailLog();
        $template = $emailLog->relationLoaded('template') ? $emailLog->getRelation('template') : null;
        $provider = $emailLog->relationLoaded('provider') ? $emailLog->getRelation('provider') : null;
        $status = (string) ($emailLog->getAttribute('status') ?? '');

        $data = [
            'id' => $emailLog->getKey(),
            'show_url' => route('app.masters.email.logs.show', $emailLog->getKey()),
            'subject' => $emailLog->getAttribute('subject') ?? '(No Subject)',
            'template_name' => $emailLog->getAttribute('template_name') ?? $template?->name,
            'template' => $template instanceof EmailTemplate ? [
                'id' => $template->id,
                'name' => $template->name,
            ] : null,
            'provider_name' => $emailLog->getAttribute('provider_name') ?? $provider?->name,
            'provider' => $provider instanceof EmailProvider ? [
                'id' => $provider->id,
                'name' => $provider->name,
            ] : null,
            'status' => $status,
            'status_label' => $this->getStatusLabel($status),
            'status_badge' => $this->getStatusBadge($status),
            'status_class' => self::getStatusClass($status),
            'recipients' => $emailLog->recipient_list,
            'error_message' => $emailLog->getAttribute('error_message'),
            'actions' => $this->actions($emailLog),

            // Datetime fields
            'sent_at' => $emailLog->getAttribute('sent_at'),
            'created_at' => $emailLog->getAttribute('created_at'),
        ];

        return $this->formatDateTimeFields(
            $data,
            datetimeFields: ['sent_at', 'created_at']
        );
    }

    /**
     * Get status CSS classes for DataGrid badge template
     * ⚠️ CRITICAL: Must return full Bootstrap badge classes
     */
    public static function getStatusClass(string $status): string
    {
        return match ($status) {
            'sent', 'success' => 'bg-success-subtle text-success',
            'queued' => 'bg-warning-subtle text-warning',
            'failed', 'error' => 'bg-danger-subtle text-danger',
            default => 'bg-secondary-subtle text-secondary',
        };
    }

    /**
     * Get status label
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'sent', 'success' => 'Sent',
            'queued' => 'Queued',
            'failed', 'error' => 'Failed',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * Get status badge type (for old badge format)
     */
    private function getStatusBadge(string $status): string
    {
        return match ($status) {
            'sent', 'success' => 'success',
            'queued' => 'warning',
            'failed', 'error' => 'danger',
            default => 'secondary',
        };
    }

    /**
     * Get available actions for this email log
     */
    private function actions(EmailLog $emailLog): array
    {
        $actions = [];

        if (SuperUserAccess::allows(auth()->user())) {
            $actions['view'] = [
                'url' => route('app.masters.email.logs.show', $emailLog->getKey()),
                'label' => 'View Details',
                'icon' => 'ri-eye-line',
                'method' => 'GET',
            ];
        }

        return $actions;
    }

    private function emailLog(): EmailLog
    {
        throw_unless($this->resource instanceof EmailLog, RuntimeException::class, 'EmailLogResource expects an EmailLog model instance.');

        return $this->resource;
    }
}
