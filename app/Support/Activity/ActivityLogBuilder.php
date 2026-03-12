<?php

namespace App\Support\Activity;

use App\Enums\ActivityAction;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ActivityLogBuilder
{
    private array $extra = [];

    private bool $queue = false;

    private ?Model $causer = null;

    private ?string $module = null;

    private ?string $entityName = null;

    public function __construct(
        private readonly Model $model
    ) {}

    public static function for(Model $model): self
    {
        return new self($model);
    }

    public function module(?string $module): self
    {
        $this->module = $module;

        return $this;
    }

    public function extra(array $extra): self
    {
        $this->extra = array_merge($this->extra, $extra);

        return $this;
    }

    public function queue(bool $shouldQueue = true): self
    {
        $this->queue = $shouldQueue;

        return $this;
    }

    public function causedBy(?Model $causer): self
    {
        $this->causer = $causer;

        return $this;
    }

    public function entityName(?string $entityName): self
    {
        $this->entityName = $entityName;

        return $this;
    }

    public function created(?string $message = null, array $extra = []): void
    {
        $this->write(ActivityAction::CREATE, $message, $extra);
    }

    public function updated(?string $message = null, array $extra = []): void
    {
        $this->write(ActivityAction::UPDATE, $message, $extra);
    }

    public function deleted(?string $message = null, array $extra = []): void
    {
        $this->write(ActivityAction::DELETE, $message, $extra);
    }

    public function forceDeleted(?string $message = null, array $extra = []): void
    {
        $this->write(ActivityAction::FORCE_DELETE, $message, $extra);
    }

    public function restored(?string $message = null, array $extra = []): void
    {
        $this->write(ActivityAction::RESTORE, $message, $extra);
    }

    public function write(ActivityAction $action, ?string $message = null, array $extra = []): void
    {
        $payload = array_merge($this->extra, $extra);

        if ($this->module && ! Arr::has($payload, 'module')) {
            $payload['module'] = $this->module;
        }

        if (! Arr::has($payload, 'entity')) {
            $payload['entity'] = $this->makeEntityName();
        }

        $logger = resolve(ActivityLogger::class);
        $logMessage = $message ?? $this->makeDefaultMessage($action);

        // Check if this is an update with previous values - use specialized method
        if (Arr::has($payload, 'previous_values') && Arr::has($payload, 'current_values')) {
            $previousValues = Arr::pull($payload, 'previous_values');
            Arr::forget($payload, 'current_values');

            // Use logWithPreviousValues which computes changes automatically
            $logger->logWithPreviousValues(
                $this->model,
                $action,
                $logMessage,
                $previousValues,
                $payload,
                $this->causer
            );

            return;
        }

        // Standard log for non-update operations
        $logger->log(
            $this->model,
            $action,
            $logMessage,
            $payload,
            $this->queue,
            $this->causer
        );
    }

    private function makeDefaultMessage(ActivityAction $action): string
    {
        $entity = $this->makeEntityName();

        $actionLabel = match ($action) {
            ActivityAction::CREATE => 'created',
            ActivityAction::UPDATE => 'updated',
            ActivityAction::DELETE => 'deleted',
            ActivityAction::FORCE_DELETE => 'permanently deleted',
            ActivityAction::RESTORE => 'restored',
            default => Str::replace('_', ' ', $action->value),
        };

        return sprintf('%s %s successfully.', $entity, $actionLabel);
    }

    private function makeEntityName(): string
    {
        if ($this->entityName) {
            return $this->entityName;
        }

        $preferredAttributes = ['title', 'name', 'label', 'subject', 'display_name'];

        foreach ($preferredAttributes as $attribute) {
            $value = data_get($this->model, $attribute);
            if (is_string($value) && $value !== '') {
                $this->entityName = $value;

                return $this->entityName;
            }
        }

        $this->entityName = Str::headline(class_basename($this->model));

        return $this->entityName;
    }
}
