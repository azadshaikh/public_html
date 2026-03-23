<?php

declare(strict_types=1);

namespace Modules\Helpdesk\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Models\Settings;
use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Helpdesk\Definitions\TicketDefinition;
use Modules\Helpdesk\Http\Resources\TicketResource;
use Modules\Helpdesk\Models\Department;
use Modules\Helpdesk\Models\Ticket;
use Modules\Helpdesk\Models\TicketReplies;

class TicketService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    public function getInertiaConfig(): array
    {
        $config = $this->scaffold()->toInertiaConfig();

        $config['columns'] = $this->getColumnsConfig();
        $config['filters'] = $this->getFiltersConfig();

        if ($this->scaffold()->shouldIncludeActionConfigInInertia()) {
            $config['actions'] = $this->getActionsConfig();
        }

        return $config;
    }

    protected function getFiltersConfig(): array
    {
        return collect($this->scaffold()->filters())
            ->map(function ($filter): array {
                if ($filter->key === 'department_id') {
                    $filter->options($this->normalizeFilterOptionMap($this->getDepartmentOptions()));
                }

                if ($filter->key === 'assigned_to' || $filter->key === 'user_id') {
                    $filter->options($this->normalizeFilterOptionMap($this->getUserOptions()));
                }

                if ($filter->key === 'priority') {
                    $filter->options($this->normalizeFilterOptionMap($this->getPriorityOptions()));
                }

                return $filter->toArray();
            })
            ->toArray();
    }

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new TicketDefinition;
    }

    // =========================================================================
    // CRUD OVERRIDES (attachments + closed fields)
    // =========================================================================

    public function update(Model $model, array $data): Model
    {
        throw_unless($model instanceof Ticket, InvalidArgumentException::class, 'Expected Ticket model instance.');

        return DB::transaction(function () use ($model, $data) {
            $ticket = $model;

            $prepared = $this->prepareUpdatePayload($data, $ticket);

            if ($auditUserId = $this->resolveAuditUserId()) {
                $prepared['updated_by'] = $auditUserId;
            }

            $ticket->update($prepared);

            $this->afterUpdate($ticket, $data);

            return $ticket->fresh();
        });
    }

    // =========================================================================
    // FORM OPTIONS
    // =========================================================================

    public function getDepartmentOptions(): array
    {
        return Department::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Department $dept): array => ['value' => (string) $dept->id, 'label' => $dept->name])
            ->all();
    }

    public function getUserOptions(): array
    {
        return User::getActiveUsersForSelect();
    }

    public function getPriorityOptions(): array
    {
        return config('helpdesk.priority_options', [
            ['label' => 'Low', 'value' => 'low'],
            ['label' => 'Medium', 'value' => 'medium'],
            ['label' => 'High', 'value' => 'high'],
            ['label' => 'Critical', 'value' => 'critical'],
        ]);
    }

    public function getStatusOptions(): array
    {
        return config('helpdesk.ticket_status_options', [
            ['label' => 'Open', 'value' => 'open'],
            ['label' => 'Pending', 'value' => 'pending'],
            ['label' => 'Resolved', 'value' => 'resolved'],
            ['label' => 'On Hold', 'value' => 'on_hold'],
            ['label' => 'Closed', 'value' => 'closed'],
            ['label' => 'Cancelled', 'value' => 'cancelled'],
        ]);
    }

    public function generateTicketNumber(): string
    {
        $prefix = setting('helpdesk_ticket_prefix', 'TK');
        $ticketDigitLength = (int) setting('helpdesk_ticket_digit_length', 4);
        $configuredSequence = max(1, (int) setting('helpdesk_ticket_serial_number', 1));

        $lastTicket = Ticket::query()->orderByDesc('id')->first();
        $lastSequence = 0;

        if ($lastTicket !== null) {
            $suffix = preg_replace('/^'.preg_quote((string) $prefix, '/').'/', '', (string) $lastTicket->ticket_number);
            $lastSequence = (int) preg_replace('/\D+/', '', (string) $suffix);
        }

        $sequence = max($configuredSequence, $lastSequence + 1);

        return $prefix.str_pad((string) $sequence, $ticketDigitLength, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<UploadedFile>  $files
     * @return array<int, array<string, mixed>>
     */
    public function uploadAttachments(array $files): array
    {
        $attachments = [];
        $disk = get_storage_disk();
        $rootFolder = get_storage_root_folder();

        foreach ($files as $file) {
            $ulid = strtolower((string) Str::ulid());
            $safeName = $this->sanitizeAttachmentFilename(
                $file->getClientOriginalName(),
                $file->getClientOriginalExtension()
            );

            $directory = trim($rootFolder.'/helpdesk/'.$ulid, '/');
            $path = Storage::disk($disk)->putFileAs($directory, $file, $safeName);

            $attachments[] = [
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'file_extension' => $file->getClientOriginalExtension(),
            ];
        }

        return $attachments;
    }

    protected function getResourceClass(): ?string
    {
        return TicketResource::class;
    }

    protected function afterCreate(Model $model, array $data): void
    {
        if (! $model instanceof Ticket) {
            return;
        }

        $numericPart = preg_replace('/\D+/', '', (string) $model->ticket_number);
        $nextSequence = max(1, (int) $numericPart + 1);

        Settings::query()->updateOrCreate(
            ['key' => 'helpdesk_ticket_serial_number'],
            [
                'value' => (string) $nextSequence,
                'type' => 'integer',
                'updated_by' => $this->resolveAuditUserId(),
            ],
        );
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'department:id,name',
            'user:id,first_name,last_name,name',
            'assignedTo:id,first_name,last_name,name',
            'createdBy:id,first_name,last_name,name',
            'updatedBy:id,first_name,last_name,name',
        ];
    }

    // =========================================================================
    // INTERNAL DATA PREP
    // =========================================================================

    protected function prepareCreateData(array $data): array
    {
        $ticketNumber = (string) ($data['ticket_number'] ?? $this->generateTicketNumber());
        $status = (string) ($data['status'] ?? 'open');

        $attachments = [];
        if (! empty($data['attachments']) && is_array($data['attachments'])) {
            $attachments = $this->uploadAttachments($data['attachments']);
        }

        $payload = [
            'ticket_number' => $ticketNumber,
            'department_id' => $data['department_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'subject' => $data['subject'] ?? null,
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'low',
            'assigned_to' => $data['assigned_to'] ?? null,
            'status' => $status,
            'opened_at' => now(),
            'attachments' => $attachments !== [] ? $attachments : null,
            'metadata' => $data['metadata'] ?? null,
        ];

        if ($status === 'closed') {
            $payload['closed_at'] = now();
            $payload['closed_by'] = $this->resolveAuditUserId();
        }

        return $payload;
    }

    protected function prepareUpdatePayload(array $data, Ticket $ticket): array
    {
        $status = (string) ($data['status'] ?? $ticket->status);
        $previousStatus = (string) ($ticket->status ?? '');

        $attachments = $this->mergeAttachments(
            $ticket,
            $data['attachments_urls'] ?? [],
            $data['attachments'] ?? []
        );

        $payload = [
            'ticket_number' => $data['ticket_number'] ?? $ticket->ticket_number,
            'department_id' => $data['department_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'subject' => $data['subject'] ?? null,
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'] ?? 'low',
            'assigned_to' => $data['assigned_to'] ?? null,
            'status' => $status,
            'attachments' => $attachments !== [] ? $attachments : null,
            'metadata' => $data['metadata'] ?? $ticket->metadata,
        ];

        if ($status === 'closed' && $previousStatus !== 'closed') {
            $payload['closed_at'] = now();
            $payload['closed_by'] = $this->resolveAuditUserId();
        } elseif ($status !== 'closed') {
            $payload['closed_at'] = null;
            $payload['closed_by'] = null;
        }

        return $payload;
    }

    // =========================================================================
    // DELETION HOOKS
    // =========================================================================

    /**
     * Before permanently deleting a ticket, remove all stored attachment files
     * for both the ticket itself and all of its replies.
     */
    protected function beforeForceDelete(Model $model): void
    {
        if (! $model instanceof Ticket) {
            return;
        }

        $disk = get_storage_disk();

        // Delete ticket-level attachments
        $ticketAttachments = is_array($model->attachments) ? $model->attachments : [];
        foreach ($ticketAttachments as $attachment) {
            $path = $attachment['file_path'] ?? null;
            if ($path) {
                Storage::disk($disk)->delete($path);
            }
        }

        // Delete reply attachments
        $model->replies()->each(function ($reply) use ($disk): void {
            /** @var TicketReplies $reply */
            $replyAttachments = is_array($reply->attachments) ? $reply->attachments : [];
            foreach ($replyAttachments as $attachment) {
                $path = $attachment['file_path'] ?? null;
                if ($path) {
                    Storage::disk($disk)->delete($path);
                }
            }
        });
    }

    /**
     * @param  array<int, string>  $keepPaths
     * @param  array<UploadedFile>  $newFiles
     * @return array<int, array<string, mixed>>
     */
    protected function mergeAttachments(Ticket $ticket, array $keepPaths, array $newFiles): array
    {
        $attachments = [];

        $current = $ticket->attachments;
        // @phpstan-ignore-next-line function.impossibleType
        if (is_string($current)) {
            $current = json_decode($current, true) ?: [];
        }

        $current = is_array($current) ? $current : [];

        foreach ($current as $attachment) {
            $path = $attachment['file_path'] ?? null;
            if (! $path) {
                continue;
            }

            if (in_array($path, $keepPaths, true)) {
                $attachments[] = $attachment;
            } else {
                Storage::disk(get_storage_disk())->delete($path);
            }
        }

        if ($newFiles !== []) {
            return array_merge(
                $attachments,
                $this->uploadAttachments($newFiles)
            );
        }

        return $attachments;
    }

    /**
     * Sanitize an uploaded filename for safe storage.
     * Keeps the original name readable but removes dangerous characters.
     */
    private function sanitizeAttachmentFilename(string $originalName, string $extension): string
    {
        // Get name without extension
        $name = pathinfo($originalName, PATHINFO_FILENAME);

        // Replace path separators, null bytes, and control characters
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]/', '', $name);

        // Remove leading dots (hidden files) and trailing dots/spaces
        $name = ltrim((string) $name, '.');
        $name = rtrim($name, '. ');

        // Replace sequences of dots to prevent directory traversal
        $name = preg_replace('/\.{2,}/', '.', $name);

        // Replace any remaining problematic characters (keep letters, numbers, spaces, hyphens, underscores, dots)
        $name = preg_replace('/[^\w\s.\-]/u', '_', (string) $name);

        // Collapse multiple spaces/underscores
        $name = preg_replace('/[\s_]+/', '_', (string) $name);

        // Trim to reasonable length
        $maxLength = (int) config('media.max_file_name_length', 100);
        if ($maxLength > 0 && mb_strlen((string) $name) > $maxLength) {
            $name = mb_substr((string) $name, 0, $maxLength);
        }

        // Fallback if name is empty after sanitization
        if ($name === '') {
            $name = 'attachment_'.time();
        }

        // Sanitize extension
        $extension = preg_replace('/[^a-zA-Z0-9]/', '', $extension);

        return $name.'.'.$extension;
    }
}
