<?php

namespace App\Services;

use App\Enums\ActivityAction;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class ActivityLogger
{
    /**
     * Maximum identical activities allowed per minute before rate limiting
     */
    private const int RATE_LIMIT_MAX_ATTEMPTS = 10;

    /**
     * Rate limit window in seconds
     */
    private const int RATE_LIMIT_WINDOW_SECONDS = 60;

    public function __construct(
        private ?Request $request = null
    ) {
        $this->request = $request ?? request();
    }

    /**
     * Log an activity with enhanced context and optional queueing
     */
    public function log(
        Model $model,
        ActivityAction $action,
        string $message,
        array $extraProperties = [],
        bool $queue = false,
        ?Model $causer = null
    ): void {
        if (! config('activitylog.enabled', true)) {
            return;
        }

        // Rate limiting check
        if ($this->shouldRateLimit($model, $action, $message)) {
            return;
        }

        $logData = $this->prepareLogData($model, $action, $message, $extraProperties, $causer);

        if ($queue) {
            $this->queueActivity($logData);
        } else {
            $this->logActivity($logData);
        }
    }

    /**
     * Log with previous values for better audit trail
     *
     * Computes changed fields automatically by comparing previous and current values.
     * The result includes:
     * - changed_fields: Array of field names that changed
     * - changes: Array with 'old' and 'new' values for each changed field
     * - previous_values: Full previous state (for reference)
     * - current_values: Full current state (for reference)
     */
    public function logWithPreviousValues(
        Model $model,
        ActivityAction $action,
        string $message,
        array $previousValues = [],
        array $extraProperties = [],
        ?Model $causer = null
    ): void {
        $currentValues = $model->getAttributes();

        // Compute what actually changed
        $changes = $this->computeChanges($previousValues, $currentValues);

        $properties = array_merge($extraProperties, [
            'changed_fields' => array_keys($changes),
            'changes' => $changes,
            'previous_values' => $previousValues,
            'current_values' => $currentValues,
        ]);

        $this->log($model, $action, $message, $properties, false, $causer);
    }

    /**
     * Bulk logging for multiple activities
     */
    public function logBulk(array $activities): void
    {
        foreach ($activities as $activity) {
            $this->log(
                $activity['model'],
                $activity['action'],
                $activity['message'],
                $activity['properties'] ?? [],
                $activity['causer'] ?? null
            );
        }
    }

    /**
     * Log with custom log channel
     */
    public function logToChannel(
        string $channel,
        Model $model,
        ActivityAction $action,
        string $message,
        array $extraProperties = [],
        ?Model $causer = null
    ): void {
        $logData = $this->prepareLogData($model, $action, $message, $extraProperties, $causer);

        activity($channel)
            ->performedOn($logData['model'])
            ->causedBy($logData['causer'] ?? auth()->user())
            ->withProperties($logData['properties'])
            ->event($logData['action']->value)
            ->log($logData['message']);
    }

    /**
     * Prepare log data with enhanced context
     */
    private function prepareLogData(Model $model, ActivityAction $action, string $message, array $extraProperties, ?Model $causer = null): array
    {
        $properties = array_merge([
            'action' => $action->value,
            'model' => class_basename($model),
            'model_id' => $model->getKey(),
            'ip_address' => $this->request?->ip(),
            'user_agent' => $this->request?->userAgent(),
            'request_url' => $this->request?->fullUrl(),
            'request_method' => $this->request?->method(),
            'timestamp' => now()->toISOString(),
        ], $extraProperties);

        return [
            'model' => $model,
            'action' => $action,
            'message' => $message,
            'properties' => $properties,
            'causer' => $causer,
        ];
    }

    /**
     * Actually log the activity using Spatie's activity log
     */
    private function logActivity(array $logData): void
    {
        $activity = activity(class_basename($logData['model']))
            ->performedOn($logData['model']);

        $causer = $logData['causer'] ?? auth()->user();
        if ($causer) {
            $activity->causedBy($causer);
        }

        $activity
            ->withProperties($logData['properties'])
            ->event($logData['action']->value)
            ->log($logData['message']);
    }

    /**
     * Queue the activity for high-traffic scenarios
     */
    private function queueActivity(array $logData): void
    {
        Queue::push(function () use ($logData): void {
            $this->logActivity($logData);
        });
    }

    /**
     * Rate limiting to prevent excessive logging
     *
     * @return bool True if rate limited (should skip logging)
     */
    private function shouldRateLimit(Model $model, ActivityAction $action, string $message): bool
    {
        $userId = auth()->id() ?? 'guest';
        $modelClass = class_basename($model);
        $modelId = $model->getKey() ?? 'new';

        $key = sprintf(
            'activity_rate_limit:%s:%s:%s:%s',
            $userId,
            $modelClass,
            $modelId,
            $action->value
        );

        $attempts = Cache::get($key, 0);

        if ($attempts >= self::RATE_LIMIT_MAX_ATTEMPTS) {
            // Log warning when rate limited
            Log::warning('Activity log rate limited', [
                'user_id' => $userId,
                'model' => $modelClass,
                'model_id' => $modelId,
                'action' => $action->value,
                'message' => $message,
                'attempts' => $attempts,
                'max_attempts' => self::RATE_LIMIT_MAX_ATTEMPTS,
                'window_seconds' => self::RATE_LIMIT_WINDOW_SECONDS,
                'ip_address' => $this->request?->ip(),
            ]);

            return true;
        }

        Cache::put($key, $attempts + 1, self::RATE_LIMIT_WINDOW_SECONDS);

        return false;
    }

    /**
     * Compute the differences between previous and current values
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function computeChanges(array $previousValues, array $currentValues): array
    {
        $changes = [];

        // Fields to exclude from change tracking (system fields)
        $excludeFields = ['updated_at', 'updated_by', 'remember_token'];

        // Check all fields in current values
        foreach ($currentValues as $field => $newValue) {
            if (in_array($field, $excludeFields, true)) {
                continue;
            }

            $oldValue = $previousValues[$field] ?? null;

            // Compare values (handle type coercion for database values)
            if (! $this->valuesAreEqual($oldValue, $newValue)) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        // Check for removed fields (in previous but not in current)
        foreach ($previousValues as $field => $oldValue) {
            if (in_array($field, $excludeFields, true)) {
                continue;
            }

            if (! array_key_exists($field, $currentValues)) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => null,
                ];
            }
        }

        return $changes;
    }

    /**
     * Compare two values for equality, handling type coercion
     */
    private function valuesAreEqual(mixed $oldValue, mixed $newValue): bool
    {
        // Normalize BackedEnum to their underlying values
        if ($oldValue instanceof BackedEnum) {
            $oldValue = $oldValue->value;
        }

        if ($newValue instanceof BackedEnum) {
            $newValue = $newValue->value;
        }

        // Handle null comparisons
        if ($oldValue === null && $newValue === null) {
            return true;
        }

        // Handle Carbon/DateTime objects
        if ($oldValue instanceof DateTimeInterface && $newValue instanceof DateTimeInterface) {
            return $oldValue->getTimestamp() === $newValue->getTimestamp();
        }

        // Handle arrays/JSON
        if (is_array($oldValue) && is_array($newValue)) {
            return $oldValue === $newValue;
        }

        // Handle boolean values stored as 0/1 in database
        if (is_bool($oldValue) || is_bool($newValue)) {
            return (bool) $oldValue === (bool) $newValue;
        }

        // Default string comparison
        return (string) $oldValue === (string) $newValue;
    }
}
