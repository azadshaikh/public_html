<?php

namespace App\Traits;

use App\Enums\ActivityAction;
use App\Services\ActivityLogger;
use App\Support\Activity\ActivityLogBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * ActivityTrait provides a simple interface for activity logging across the application.
 *
 * This trait now uses the centralized ActivityLogger service which provides:
 * - Enhanced context logging (IP, user agent, request URL, etc.)
 * - Rate limiting to prevent excessive logging
 * - Support for queueing in high-traffic scenarios
 * - Bulk logging capabilities
 * - Custom log channels
 * - Previous values tracking for audit trails
 */
trait ActivityTrait
{
    /**
     * Log an activity for any model with action, message, and extra properties.
     *
     * @param  Model  $model
     * @param  bool  $queue  Whether to queue the activity log (useful for high-traffic)
     */
    public function logActivity(
        $model,
        ActivityAction $action,
        string $message,
        array $extraProperties = [],
        bool $queue = false
    ): void {
        $this->activity($model)
            ->queue($queue)
            ->extra($extraProperties)
            ->write($action, $message);
    }

    /**
     * Log activity with previous values for better audit trail
     */
    public function logActivityWithPreviousValues(
        $model,
        ActivityAction $action,
        string $message,
        array $previousValues = [],
        array $extraProperties = []
    ): void {
        $this->activity($model)
            ->extra($extraProperties)
            ->write($action, $message, [
                'previous_values' => $previousValues,
                'current_values' => $model->getAttributes(),
            ]);
    }

    /**
     * Log activity to a specific channel
     */
    public function logActivityToChannel(
        string $channel,
        Model $model,
        ActivityAction $action,
        string $message,
        array $extraProperties = []
    ): void {
        resolve(ActivityLogger::class)->logToChannel($channel, $model, $action, $message, $extraProperties);
    }

    /**
     * Log a create action with optional message override.
     */
    protected function logCreated(
        Model $model,
        ?string $message = null,
        array $extraProperties = [],
        bool $queue = false
    ): void {
        $this->activity($model)
            ->queue($queue)
            ->extra($extraProperties)
            ->created($message);
    }

    /**
     * Log an update action with optional message override.
     */
    protected function logUpdated(
        Model $model,
        ?string $message = null,
        array $extraProperties = [],
        bool $queue = false
    ): void {
        $this->activity($model)
            ->queue($queue)
            ->extra($extraProperties)
            ->updated($message);
    }

    /**
     * Log a delete action with optional message override.
     */
    protected function logDeleted(
        Model $model,
        ?string $message = null,
        array $extraProperties = [],
        bool $queue = false
    ): void {
        $this->activity($model)
            ->queue($queue)
            ->extra($extraProperties)
            ->deleted($message);
    }

    /**
     * Log a permanent delete action with optional message override.
     */
    protected function logForceDeleted(
        Model $model,
        ?string $message = null,
        array $extraProperties = [],
        bool $queue = false
    ): void {
        $this->activity($model)
            ->queue($queue)
            ->extra($extraProperties)
            ->forceDeleted($message);
    }

    /**
     * Log a restore action with optional message override.
     */
    protected function logRestored(
        Model $model,
        ?string $message = null,
        array $extraProperties = [],
        bool $queue = false
    ): void {
        $this->activity($model)
            ->queue($queue)
            ->extra($extraProperties)
            ->restored($message);
    }

    /**
     * Create a reusable activity log builder with common defaults.
     */
    protected function activity(Model $model): ActivityLogBuilder
    {
        $builder = ActivityLogBuilder::for($model);

        if ($module = $this->resolveActivityLogModule()) {
            $builder->module($module);
        }

        if ($entityName = $this->resolveActivityEntityName($model)) {
            $builder->entityName($entityName);
        }

        if ($defaults = $this->activityLogDefaults()) {
            $builder->extra($defaults);
        }

        if ($causer = $this->activityLogCauser()) {
            $builder->causedBy($causer);
        }

        return $builder;
    }

    /**
     * Resolve a consistent module label for logging.
     *
     * Tries to infer module name from:
     * 1. Explicit override (method or property)
     * 2. Controller Namespace (App\Modules\{Name} or App\Http\Controllers\{Name})
     * 3. Fallback to Entity Name (Model)
     */
    protected function resolveActivityLogModule(): ?string
    {
        $explicitModule = $this->callOptionalStringMethod('getActivityLogModule');
        if ($explicitModule !== null) {
            return $explicitModule;
        }

        $moduleProperty = Arr::get(get_object_vars($this), 'activityLogModule');
        if (is_string($moduleProperty) && $moduleProperty !== '') {
            return $moduleProperty;
        }

        // Infer from Namespace
        $className = $this::class;

        // Pattern 1: App\Modules\{ModuleName}\...
        if (preg_match('/^App\\\Modules\\\([^\\\]+)/', $className, $matches)) {
            return Str::headline($matches[1]);
        }

        // Pattern 2: App\Http\Controllers\{ModuleName}\... (only if in a subdirectory)
        if (preg_match('/^App\\\Http\\\Controllers\\\([^\\\]+)\\\/', $className, $matches)) {
            return Str::headline($matches[1]);
        }

        return $this->callOptionalStringMethod('getEntityName'); // or generic 'System'
    }

    /**
     * Resolve a custom entity label for the given model if the controller provides one.
     */
    protected function resolveActivityEntityName(Model $model): ?string
    {
        if (method_exists($this, 'getActivityEntityName')) {
            return $this->getActivityEntityName($model);
        }

        $entityAttribute = Arr::get(get_object_vars($this), 'activityEntityAttribute');
        if (is_string($entityAttribute) && $entityAttribute !== '') {
            $value = Arr::get($model, $entityAttribute);

            if ($value) {
                return $value;
            }
        }

        if (method_exists($this, 'getPrimaryFieldName')) {
            $primaryField = $this->getPrimaryFieldName();
            $value = Arr::get($model, $primaryField);
            if ($value) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Provide default extra properties for every activity log.
     */
    protected function activityLogDefaults(): array
    {
        if (method_exists($this, 'getActivityLogDefaults')) {
            return $this->getActivityLogDefaults();
        }

        $defaultsProperty = Arr::get(get_object_vars($this), 'activityLogDefaults');
        if (is_array($defaultsProperty)) {
            return $defaultsProperty;
        }

        return [];
    }

    /**
     * Allow controllers/services to override the causer context.
     */
    protected function activityLogCauser(): ?Model
    {
        if (method_exists($this, 'getActivityLogCauser')) {
            return $this->getActivityLogCauser();
        }

        return null;
    }

    private function callOptionalStringMethod(string $method, mixed ...$arguments): ?string
    {
        if (! method_exists($this, $method)) {
            return null;
        }

        $value = $this->{$method}(...$arguments);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
