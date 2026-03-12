<?php

namespace App\Http\Resources;

use App\Models\ActivityLog;
use App\Traits\DateTimeFormattingTrait;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ActivityLogsResource extends JsonResource
{
    use DateTimeFormattingTrait;

    public function toArray(Request $request): array
    {
        /** @var ActivityLog $activity */
        $activity = $this->resource;
        $event = $activity->event ?? '';

        $data = [
            'id' => $activity->id,
            'checkbox' => true,
            'show_url' => route('app.logs.activity-logs.show', $activity->id),
            'event' => $event,
            'event_label' => $event !== '' ? ucwords(str_replace('_', ' ', $event)) : 'Unknown',
            'event_class' => $event !== '' ? $this->getEventBadgeClass($event) : 'bg-secondary-subtle text-secondary',
            'description' => $activity->description
                ? Str::limit($activity->description, 100)
                : 'No description',
            'causer_name' => $activity->causer_name,
            'subject_display' => $activity->subject_display,
            'ip_address' => $activity->ip_address ?? '-',
            'user_agent' => $activity->browser ?? 'Unknown',
            'created_at' => $activity->created_at,
            'time_ago' => $activity->time_ago,
            'severity' => $activity->severity,
            'actions' => $this->buildActions($activity),
        ];

        // Add additional context if available
        if ($activity->hasTrackedChanges()) {
            $data['has_changes'] = true;
            $data['changes_count'] = count($activity->getChangesSummary());
        }

        // Add context information
        $context = $activity->context;
        if (! empty($context)) {
            $data['context'] = $context;
        }

        // Add request details if available
        if ($activity->request_url) {
            $data['request_details'] = [
                'url' => $activity->request_url,
                'method' => $activity->getProperty('request_method', 'GET'),
            ];
        }

        // Format datetime fields
        return $this->formatDateTimeFields(
            $data,
            datetimeFields: ['created_at']
        );
    }

    /**
     * Build actions array based on context
     */
    private function buildActions(ActivityLog $activity): array
    {
        $actions = [];
        $isTrashed = $activity->trashed();

        // View action
        $actions['view'] = [
            'url' => route('app.logs.activity-logs.show', $activity->id),
            'label' => 'View Details',
            'icon' => 'ri-eye-line',
            'class' => 'dropdown-item',
            'method' => 'GET',
        ];

        if ($isTrashed) {
            $actions['restore'] = [
                'url' => route('app.logs.activity-logs.restore', $activity->id),
                'label' => 'Restore',
                'icon' => 'ri-refresh-line',
                'class' => 'dropdown-item text-success',
                'method' => 'PATCH',
                'confirm' => 'Restore this activity log?',
            ];

            $actions['force_delete'] = [
                'url' => route('app.logs.activity-logs.force-delete', $activity->id),
                'label' => 'Delete Permanently',
                'icon' => 'ri-delete-bin-fill',
                'class' => 'dropdown-item text-danger',
                'method' => 'DELETE',
                'confirm' => '⚠️ PERMANENT: This activity log will be deleted forever and cannot be recovered.',
            ];
        } else {
            // Delete action (permission will be checked by datagrid/middleware)
            $actions['delete'] = [
                'url' => route('app.logs.activity-logs.destroy', $activity->id),
                'label' => 'Delete',
                'icon' => 'ri-delete-bin-line',
                'class' => 'dropdown-item text-danger',
                'method' => 'DELETE',
                'confirm' => 'Are you sure you want to delete this activity log?',
            ];
        }

        return $actions;
    }

    /**
     * Get event badge class based on action type
     */
    private function getEventBadgeClass(string $event): string
    {
        return match (strtolower($event)) {
            'create', 'created', 'stored', 'add', 'added' => 'bg-success-subtle text-success',
            'update', 'updated', 'edited', 'modify', 'modified' => 'bg-info-subtle text-info',
            'delete', 'deleted', 'trashed', 'remove', 'removed', 'bulk_delete' => 'bg-danger-subtle text-danger',
            'force_delete', 'bulk_force_delete' => 'bg-danger-subtle text-danger',
            'restore', 'restored', 'recover', 'recovered', 'bulk_restore' => 'bg-warning-subtle text-warning',
            'view', 'viewed', 'access', 'accessed' => 'bg-secondary-subtle text-secondary',
            'approve', 'approved', 'accept', 'accepted' => 'bg-success-subtle text-success',
            'reject', 'rejected', 'deny', 'denied' => 'bg-danger-subtle text-danger',
            'archive', 'archived' => 'bg-dark-subtle text-dark',
            'publish', 'published' => 'bg-primary-subtle text-primary',
            'send', 'sent', 'email', 'emailed' => 'bg-info-subtle text-info',
            'import', 'imported', 'upload', 'uploaded' => 'bg-secondary-subtle text-secondary',
            'export', 'exported', 'download', 'downloaded' => 'bg-primary-subtle text-primary',
            'duplicate', 'duplicated', 'copy', 'copied' => 'bg-warning-subtle text-warning',
            default => 'bg-primary-subtle text-primary',
        };
    }
}
