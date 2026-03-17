<?php

namespace Modules\Platform\Traits;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Trait for managing website provisioning steps in the metadata JSON column.
 *
 * This trait provides methods to track the status of provisioning steps
 * (create_user, create_website, create_database, etc.) without requiring
 * a separate database table.
 *
 * Provisioning steps are stored in metadata['provisioning_steps'] as:
 * [
 *     'create_user' => ['status' => 'done', 'message' => '...', 'updated_at' => '...'],
 *     'create_website' => ['status' => 'pending', 'message' => '...', 'updated_at' => '...'],
 * ]
 *
 * Update history is stored in metadata['update_history'] as:
 * [
 *     ['meta_key' => 'update_platform', 'meta_value' => '...', 'status' => 'done', 'updated_at' => '...'],
 * ]
 */
trait HasProvisioningSteps
{
    /**
     * Get all provisioning steps from metadata.
     */
    public function getProvisioningSteps(): array
    {
        $metadata = $this->metadata ?? [];

        return $metadata['provisioning_steps'] ?? [];
    }

    /**
     * Get provisioning steps as a collection of objects for view compatibility.
     * Returns a collection keyed by step key, where each value is an object
     * with status, message (as meta_value), and updated_at properties.
     */
    public function getProvisioningStepsForView(): Collection
    {
        $steps = $this->getProvisioningSteps();

        return collect($steps)->map(fn (array $step, $key) => (object) [
            'status' => $step['status'] ?? 'pending',
            'meta_value' => $step['message'] ?? '',
            'updated_at' => isset($step['updated_at']) ? Date::parse($step['updated_at']) : null,
        ]);
    }

    /**
     * Get a specific provisioning step.
     */
    public function getProvisioningStep(string $stepKey): ?array
    {
        $steps = $this->getProvisioningSteps();

        return $steps[$stepKey] ?? null;
    }

    /**
     * Update or create a provisioning step.
     *
     * @param  string  $stepKey  The step key (e.g., 'create_user', 'create_website')
     * @param  string  $message  The status message
     * @param  string  $status  The status ('pending', 'done', 'failed', 'reverted')
     */
    public function updateProvisioningStep(string $stepKey, string $message, string $status): void
    {
        // Use database transaction with pessimistic lock to prevent race conditions
        DB::transaction(function () use ($stepKey, $message, $status): void {
            // Lock THIS specific row for update by ID
            $model = static::lockForUpdate()->find($this->id);

            if (! $model) {
                throw new RuntimeException(sprintf('Website %s not found for provisioning step update', $this->id));
            }

            $metadata = $model->metadata ?? [];
            $steps = $metadata['provisioning_steps'] ?? [];

            $steps[$stepKey] = [
                'status' => $status,
                'message' => $message,
                'updated_at' => now()->toISOString(),
            ];

            $metadata['provisioning_steps'] = $steps;

            $model->update(['metadata' => $metadata]);
        });

        // Refresh the current instance to reflect the database changes
        // This ensures polling/status checks see the updated metadata
        if ($fresh = $this->fresh()) {
            $this->setRawAttributes($fresh->getAttributes());
            $this->syncOriginal();
        }
    }

    /**
     * Mark a provisioning step as done.
     */
    public function markProvisioningStepDone(string $stepKey, string $message): void
    {
        $this->updateProvisioningStep($stepKey, $message, 'done');
    }

    /**
     * Mark a provisioning step as failed.
     */
    public function markProvisioningStepFailed(string $stepKey, string $message): void
    {
        $this->updateProvisioningStep($stepKey, $message, 'failed');
    }

    /**
     * Mark a provisioning step as reverted.
     */
    public function markProvisioningStepReverted(string $stepKey, string $message = 'Reverted'): void
    {
        $this->updateProvisioningStep($stepKey, $message, 'reverted');
    }

    /**
     * Mark a provisioning step as waiting for an external condition (e.g., DNS propagation).
     */
    public function markProvisioningStepWaiting(string $stepKey, string $message): void
    {
        $this->updateProvisioningStep($stepKey, $message, 'waiting');
    }

    /**
     * Mark multiple provisioning steps as reverted.
     *
     * @param  array  $stepKeys  Array of step keys to revert
     */
    public function markProvisioningStepsReverted(array $stepKeys): void
    {
        // Use transaction to ensure atomicity
        DB::transaction(function () use ($stepKeys): void {
            // Lock and get THIS specific instance
            $model = static::lockForUpdate()->find($this->id);

            if (! $model) {
                throw new RuntimeException(sprintf('Website %s not found for reverting steps', $this->id));
            }

            $metadata = $model->metadata ?? [];
            $steps = $metadata['provisioning_steps'] ?? [];

            foreach ($stepKeys as $stepKey) {
                // Always create or update the step to reverted status
                $steps[$stepKey] = [
                    'status' => 'reverted',
                    'message' => 'Reverted',
                    'updated_at' => now()->toISOString(),
                ];
            }

            $metadata['provisioning_steps'] = $steps;
            $model->update(['metadata' => $metadata]);
        });

        // Refresh the current instance to reflect the database changes
        if ($fresh = $this->fresh()) {
            $this->setRawAttributes($fresh->getAttributes());
            $this->syncOriginal();
        }
    }

    /**
     * Mark all provisioning steps as reverted.
     */
    public function revertAllProvisioningSteps(): void
    {
        // Use transaction to ensure atomicity
        DB::transaction(function (): void {
            // Lock and get THIS specific instance
            $model = static::lockForUpdate()->find($this->id);

            if (! $model) {
                throw new RuntimeException(sprintf('Website %s not found for reverting all steps', $this->id));
            }

            $metadata = $model->metadata ?? [];
            $steps = $metadata['provisioning_steps'] ?? [];

            foreach ($steps as $key => $step) {
                $steps[$key]['status'] = 'reverted';
                $steps[$key]['message'] = 'Reverted';
                $steps[$key]['updated_at'] = now()->toISOString();
            }

            $metadata['provisioning_steps'] = $steps;

            $model->update(['metadata' => $metadata]);
        });

        // Refresh the current instance to reflect the database changes
        if ($fresh = $this->fresh()) {
            $this->setRawAttributes($fresh->getAttributes());
            $this->syncOriginal();
        }
    }

    /**
     * Check if a provisioning step is done.
     */
    public function isProvisioningStepDone(string $stepKey): bool
    {
        $step = $this->getProvisioningStep($stepKey);

        return $step && $step['status'] === 'done';
    }

    /**
     * Check if all provisioning steps are done.
     *
     * @param  array|null  $requiredSteps  Optional list of steps to check. If null, checks all existing steps.
     */
    public function areAllProvisioningStepsDone(?array $requiredSteps = null): bool
    {
        $steps = $this->getProvisioningSteps();

        if (empty($steps)) {
            return false;
        }

        $stepsToCheck = $requiredSteps ?? array_keys($steps);

        foreach ($stepsToCheck as $stepKey) {
            if (! isset($steps[$stepKey]) || $steps[$stepKey]['status'] !== 'done') {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the status of a provisioning step.
     */
    public function getProvisioningStepStatus(string $stepKey): ?string
    {
        $step = $this->getProvisioningStep($stepKey);

        return $step['status'] ?? null;
    }

    /**
     * Clear all provisioning steps.
     */
    public function clearProvisioningSteps(): void
    {
        // Use transaction to ensure atomicity
        DB::transaction(function (): void {
            // Lock and get THIS specific instance
            $model = static::lockForUpdate()->find($this->id);

            if (! $model) {
                throw new RuntimeException(sprintf('Website %s not found for clearing steps', $this->id));
            }

            $metadata = $model->metadata ?? [];
            unset($metadata['provisioning_steps']);

            $model->update(['metadata' => $metadata]);
        });

        // Refresh the current instance to reflect the database changes
        if ($fresh = $this->fresh()) {
            $this->setRawAttributes($fresh->getAttributes());
            $this->syncOriginal();
        }
    }

    // =========================================================================
    // Update History Methods
    // =========================================================================

    /**
     * Get all update history entries from metadata.
     */
    public function getUpdateHistory(): array
    {
        $metadata = $this->metadata ?? [];

        return $metadata['update_history'] ?? [];
    }

    /**
     * Get update history as a collection of objects for view compatibility.
     */
    public function getUpdateHistoryForView(): Collection
    {
        $history = $this->getUpdateHistory();

        return collect($history)->map(fn (array $entry) => (object) [
            'id' => $entry['id'] ?? null,
            'meta_key' => $entry['meta_key'] ?? '',
            'meta_value' => $entry['meta_value'] ?? '',
            'status' => $entry['status'] ?? 'pending',
            'updated_at' => isset($entry['updated_at']) ? Date::parse($entry['updated_at']) : null,
            'owner' => isset($entry['updated_by']) ? User::query()->find($entry['updated_by']) : null,
        ]);
    }

    /**
     * Add an update history entry.
     *
     * @param  string  $metaKey  The type of update (e.g., 'update_platform')
     * @param  array  $data  The update data (old_version, new_version, message, etc.)
     * @param  string  $status  The status ('pending', 'done', 'failed', 'reverted')
     */
    public function addUpdateHistoryEntry(string $metaKey, array $data, string $status = 'done'): int
    {
        $id = 0;

        // Use transaction to ensure atomicity
        DB::transaction(function () use ($metaKey, $data, $status, &$id): void {
            // Lock and get THIS specific instance
            $model = static::lockForUpdate()->find($this->id);

            if (! $model) {
                throw new RuntimeException(sprintf('Website %s not found for adding update history', $this->id));
            }

            $metadata = $model->metadata ?? [];
            $history = $metadata['update_history'] ?? [];

            $id = count($history) + 1;
            $history[] = [
                'id' => $id,
                'meta_key' => $metaKey,
                'meta_value' => json_encode($data),
                'status' => $status,
                'updated_at' => now()->toISOString(),
                'updated_by' => $model->getAttribute('updated_by'),
            ];

            $metadata['update_history'] = $history;

            $model->update(['metadata' => $metadata]);
        });

        // Refresh the current instance to reflect the database changes
        if ($fresh = $this->fresh()) {
            $this->setRawAttributes($fresh->getAttributes());
            $this->syncOriginal();
        }

        return $id;
    }

    /**
     * Update an existing update history entry status.
     */
    public function updateHistoryEntryStatus(int $entryId, string $status): void
    {
        // Use transaction to ensure atomicity
        DB::transaction(function () use ($entryId, $status): void {
            // Lock and get THIS specific instance
            $model = static::lockForUpdate()->find($this->id);

            if (! $model) {
                throw new RuntimeException(sprintf('Website %s not found for updating history entry', $this->id));
            }

            $metadata = $model->metadata ?? [];
            $history = $metadata['update_history'] ?? [];

            foreach ($history as &$entry) {
                if (isset($entry['id']) && $entry['id'] === $entryId) {
                    $entry['status'] = $status;
                    $entry['updated_at'] = now()->toISOString();
                    break;
                }
            }

            $metadata['update_history'] = $history;

            $model->update(['metadata' => $metadata]);
        });

        // Refresh the current instance to reflect the database changes
        if ($fresh = $this->fresh()) {
            $this->setRawAttributes($fresh->getAttributes());
            $this->syncOriginal();
        }
    }

    /**
     * Get an update history entry by ID.
     */
    public function getUpdateHistoryEntry(int $entryId): ?array
    {
        $history = $this->getUpdateHistory();

        foreach ($history as $entry) {
            if (isset($entry['id']) && $entry['id'] === $entryId) {
                return $entry;
            }
        }

        return null;
    }
}
