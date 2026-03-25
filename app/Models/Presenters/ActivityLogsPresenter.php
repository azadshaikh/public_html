<?php

namespace App\Models\Presenters;

/**
 * ActivityLogsPresenter
 *
 * Enhanced presenter trait for ActivityLog model with improved formatting,
 * enum-based badge styling, and comprehensive presentation methods.
 */
trait ActivityLogsPresenter
{
    /**
     * Get event badge class based on action type
     */
    public function getEventBadgeClass(string $event): string
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

    /**
     * Check if activity has tracked changes
     */
    public function hasTrackedChanges(): bool
    {
        if (! empty($this->attribute_changes)) {
            return true;
        }

        // Check for the new 'changes' structure first (from updated ActivityLogger)
        if (! empty(data_get($this->properties, 'changes'))) {
            return true;
        }

        // Fallback: check for previous_values/current_values (legacy format)
        return ! empty(data_get($this->properties, 'previous_values'))
            || ! empty(data_get($this->properties, 'current_values'));
    }

    /**
     * Get changes summary with old and new values
     *
     * Uses the pre-computed 'changes' structure from ActivityLogger which includes
     * old/new values for each changed field. Falls back to computing from
     * previous_values/current_values for legacy logs.
     *
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public function getChangesSummary(): array
    {
        $attributeChanges = $this->attribute_changes ?? [];

        if (! empty($attributeChanges)) {
            $result = [];
            $newAttributes = $attributeChanges['attributes'] ?? [];
            $oldAttributes = $attributeChanges['old'] ?? [];

            foreach ($newAttributes as $field => $value) {
                $result[$field] = [
                    'from' => $this->formatChangeValue($oldAttributes[$field] ?? null),
                    'to' => $this->formatChangeValue($value),
                ];
            }

            foreach ($oldAttributes as $field => $value) {
                if (array_key_exists($field, $result)) {
                    continue;
                }

                $result[$field] = [
                    'from' => $this->formatChangeValue($value),
                    'to' => $this->formatChangeValue(null),
                ];
            }

            return $result;
        }

        // Use pre-computed changes from ActivityLogger (new format with old/new)
        $precomputedChanges = data_get($this->properties, 'changes', []);

        if (! empty($precomputedChanges)) {
            // Convert from {field: {old: X, new: Y}} to {field: {from: X, to: Y}}
            $result = [];
            foreach ($precomputedChanges as $field => $change) {
                $result[$field] = [
                    'from' => $this->formatChangeValue($change['old'] ?? null),
                    'to' => $this->formatChangeValue($change['new'] ?? null),
                ];
            }

            return $result;
        }

        // Fallback: compute changes from previous_values and current_values (legacy)
        $previous = data_get($this->properties, 'previous_values', []);
        $current = data_get($this->properties, 'current_values', []);

        if (empty($previous) || empty($current)) {
            return [];
        }

        $changes = [];
        foreach ($current as $key => $value) {
            if (isset($previous[$key]) && $previous[$key] !== $value) {
                $changes[$key] = [
                    'from' => $this->formatChangeValue($previous[$key]),
                    'to' => $this->formatChangeValue($value),
                ];
            }
        }

        return $changes;
    }

    /**
     * Get formatted event badge with proper styling
     */
    protected function getEventFormattedAttribute(): string
    {
        if (! $this->event) {
            return '<span class="badge text-bg-secondary">Unknown</span>';
        }

        $badgeClass = match (strtolower($this->event)) {
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
        $eventLabel = ucwords(str_replace('_', ' ', $this->event));

        return sprintf('<span class="badge %s">%s</span>', $badgeClass, $eventLabel);
    }

    /**
     * Get formatted description with truncation
     */
    protected function getDescriptionFormattedAttribute(): string
    {
        if (! $this->description) {
            return '<em class="text-muted">No description</em>';
        }

        return strlen($this->description) > 100
            ? substr($this->description, 0, 100).'...'
            : $this->description;
    }

    /**
     * Get human-readable time difference
     */
    protected function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Get formatted created at date
     */
    protected function getCreatedAtFormattedAttribute(): string
    {
        return app_date_time_format($this->created_at, 'datetime');
    }

    /**
     * Get causer name with fallback
     */
    protected function getCauserNameAttribute(): string
    {
        if (! $this->causer) {
            return 'System';
        }

        return $this->causer->name ?? $this->causer->email ?? 'Unknown User';
    }

    /**
     * Get subject name with model info
     */
    protected function getSubjectDisplayAttribute(): string
    {
        if (! $this->subject) {
            return $this->subject_type ? class_basename($this->subject_type).' (Deleted)' : 'Unknown';
        }

        $modelName = class_basename($this->subject_type);
        $identifier = $this->subject->name ?? $this->subject->title ?? $this->subject->id ?? 'Unknown';

        return sprintf('%s: %s', $modelName, $identifier);
    }

    /**
     * Get IP address from properties
     */
    protected function getIpAddressAttribute(): ?string
    {
        return data_get($this->properties, 'ip_address');
    }

    /**
     * Get user agent from properties
     */
    protected function getUserAgentAttribute(): ?string
    {
        return data_get($this->properties, 'user_agent');
    }

    /**
     * Get request URL from properties
     */
    protected function getRequestUrlAttribute(): ?string
    {
        return data_get($this->properties, 'request_url');
    }

    /**
     * Get browser name from user agent
     */
    protected function getBrowserAttribute(): ?string
    {
        $userAgent = $this->getUserAgentAttribute();

        if (! $userAgent) {
            return null;
        }

        // Simple browser detection
        if (str_contains((string) $userAgent, 'Chrome')) {
            return 'Chrome';
        }

        if (str_contains((string) $userAgent, 'Firefox')) {
            return 'Firefox';
        }

        if (str_contains((string) $userAgent, 'Safari')) {
            return 'Safari';
        }

        // Simple browser detection
        if (str_contains((string) $userAgent, 'Edge')) {
            return 'Edge';
        }

        return 'Unknown';
    }

    /**
     * Get activity context (additional info from properties)
     */
    protected function getContextAttribute(): array
    {
        $context = [];
        $properties = $this->properties ?? [];

        // Extract meaningful context
        if (isset($properties['module_name'])) {
            $context['Module'] = $properties['module_name'];
        }

        if (isset($properties['action'])) {
            $context['Action'] = ucwords(str_replace('_', ' ', $properties['action']));
        }

        if (isset($properties['ip_address'])) {
            $context['IP Address'] = $properties['ip_address'];
        }

        if (isset($properties['request_method'])) {
            $context['Method'] = strtoupper($properties['request_method']);
        }

        return $context;
    }

    /**
     * Get activity severity level
     */
    protected function getSeverityAttribute(): string
    {
        if (! $this->event) {
            return 'info';
        }

        return match ($this->event) {
            'delete', 'deleted', 'force_delete', 'bulk_delete', 'bulk_force_delete' => 'danger',
            'create', 'created', 'restore', 'restored', 'bulk_restore' => 'success',
            'update', 'updated', 'edited' => 'info',
            'view', 'viewed' => 'secondary',
            default => 'primary',
        };
    }

    /**
     * Format activity for display in lists
     */
    protected function getDisplaySummaryAttribute(): string
    {
        $causer = $this->getCauserNameAttribute();
        $action = $this->event ? ucwords(str_replace('_', ' ', $this->event)) : 'performed an action';
        $subject = $this->getSubjectDisplayAttribute();
        $timeAgo = $this->getTimeAgoAttribute();

        return sprintf('%s %s on %s %s', $causer, $action, $subject, $timeAgo);
    }

    /**
     * Format a value for display in changes summary
     */
    private function formatChangeValue(mixed $value): string
    {
        if ($value === null) {
            return '(empty)';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        $stringValue = (string) $value;

        // Truncate very long values
        if (strlen($stringValue) > 100) {
            return substr($stringValue, 0, 100).'...';
        }

        return $stringValue;
    }
}
