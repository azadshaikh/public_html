<?php

namespace Modules\Platform\Services;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Modules\Platform\Models\Website;

/**
 * Website Provisioning Service - Server Provisioning Operations
 * ============================================================================
 *
 * RESPONSIBILITIES:
 * ├── Step Execution: Running individual or all provisioning steps via Artisan
 * ├── Step Reversion: Reverting individual or all provisioning steps
 * └── Update Reversion: Reverting platform updates (history)
 *
 * CALLED BY:
 * └── WebsiteController - For manual step execution/reversion by admin
 */
class WebsiteProvisioningService
{
    use ActivityTrait;

    /**
     * Execute a specific provisioning step or all steps.
     *
     * @param  Website  $website  The website instance
     * @param  string  $step  The step key or 'all'
     * @return array Result with status and message
     */
    public function executeStep(Website $website, string $step): array
    {
        if ($step === 'all') {
            return $this->executeAllSteps($website);
        }

        return $this->executeSingleStep($website, $step);
    }

    /**
     * Revert a specific provisioning step or all steps.
     *
     * @param  Website  $website  The website instance
     * @param  string  $step  The step key or 'all'
     * @return array Result with status and message
     */
    public function revertStep(Website $website, string $step): array
    {
        $command = 'platform:hestia:revert-installation-step';
        $params = ['website_id' => $website->id, '--step' => $step, '--force' => true];
        $stepTitle = $step === 'all' ? 'All' : $this->getStepTitle($step);

        try {
            $exitCode = Artisan::call($command, $params);

            if ($exitCode !== 0) {
                $commandOutput = trim((string) Artisan::output());

                return [
                    'status' => 'error',
                    'message' => $commandOutput !== ''
                        ? $commandOutput
                        : $stepTitle.' revert failed with exit code: '.$exitCode,
                ];
            }

            $logMessage = $step === 'all'
                ? 'Website step reverted: All'
                : 'Website step reverted: '.$stepTitle;

            $this->logActivity($website, ActivityAction::UPDATE, $logMessage);

            return [
                'status' => 'success',
                'message' => $step === 'all' ? 'All steps reverted successfully.' : $stepTitle.' reverted successfully.',
            ];
        } catch (Exception $exception) {
            return [
                'status' => 'error',
                'message' => $stepTitle.' revert failed: '.$exception->getMessage(),
            ];
        }
    }

    /**
     * Revert a platform update.
     */
    public function revertUpdate(Website $website, int $historyId): array
    {
        $update = $website->getUpdateHistoryEntry($historyId);
        if (! $update) {
            return ['status' => 'error', 'message' => 'Update not found.'];
        }

        $updateData = [];
        $rawMetaValue = $update['meta_value'] ?? null;
        if (is_string($rawMetaValue)) {
            $decoded = json_decode($rawMetaValue, true);
            if (is_array($decoded)) {
                $updateData = $decoded;
            }
        }

        $old_version = $updateData['old_version'] ?? 'unknown';
        $new_version = $updateData['new_version'] ?? 'unknown';

        if ($old_version === $new_version) {
            return ['status' => 'error', 'message' => 'Old version and new version are same.'];
        }

        if (($update['meta_key'] ?? null) === 'update_platform') {
            Artisan::call('platform:hestia:revert-update', [
                'website_id' => $website->id,
                'history_id' => $historyId,
            ]);

            $this->logActivity($website, ActivityAction::UPDATE, 'Attempt to Revert platform update to '.$old_version.' from '.$new_version);

            return ['status' => 'success', 'message' => 'Attempt to Revert platform update to '.$old_version.' from '.$new_version];
        }

        return ['status' => 'error', 'message' => 'Unsupported update type for revert.'];
    }

    /**
     * Execute all steps sequentially.
     */
    private function executeAllSteps(Website $website): array
    {
        $website_steps = config('platform.website.steps', []);
        $executed_steps = [];
        $failed_steps = [];

        $website->resetProvisioningRun();

        foreach ($website_steps as $step_data) {
            if ($this->shouldSkipStep($website, $step_data)) {
                continue;
            }

            try {
                $output = Artisan::call($step_data['command'], ['website_id' => $website->id]);

                if ($output === 0) {
                    $executed_steps[] = $step_data['title'];
                } else {
                    $failed_steps[] = $step_data['title'];
                }
            } catch (Exception $e) {
                $failed_steps[] = $step_data['title'].' (Error: '.$e->getMessage().')';
            }
        }

        if ($failed_steps === []) {
            $website->markProvisioningRunCompleted();
            $this->logActivity($website, ActivityAction::UPDATE, 'Website step executed: All ('.count($executed_steps).' steps completed)');

            return [
                'status' => 'success',
                'message' => 'All steps executed successfully. Completed: '.implode(', ', $executed_steps),
            ];
        }

        $this->logActivity($website, ActivityAction::UPDATE, 'Website step executed: All (Some steps failed)');

        return [
            'status' => 'error',
            'message' => 'Some steps failed. Completed: '.implode(', ', $executed_steps).'. Failed: '.implode(', ', $failed_steps),
        ];
    }

    /**
     * Execute a single step.
     */
    private function executeSingleStep(Website $website, string $step): array
    {
        $website_steps = config('platform.website.steps', []);

        if (! isset($website_steps[$step]) || ! isset($website_steps[$step]['command']) || empty($website_steps[$step]['command'])) {
            return ['status' => 'error', 'message' => 'Step not found or invalid configuration.'];
        }

        $step_data = $website_steps[$step];

        try {
            $output = Artisan::call($step_data['command'], ['website_id' => $website->id]);

            if ($output === 0) {
                $this->logActivity($website, ActivityAction::UPDATE, 'Website step executed: '.$step_data['title']);

                return ['status' => 'success', 'message' => $step_data['title'].' executed successfully.'];
            }

            return ['status' => 'error', 'message' => $step_data['title'].' failed with exit code: '.$output];
        } catch (Exception $exception) {
            return ['status' => 'error', 'message' => $step_data['title'].' failed: '.$exception->getMessage()];
        }
    }

    /**
     * Check if a step should be skipped based on provider matching.
     */
    private function shouldSkipStep(Website $website, array $step_data): bool
    {
        // Skip steps that don't have a command defined
        if (! isset($step_data['command']) || empty($step_data['command'])) {
            return true;
        }

        $command = $step_data['command'];

        // Allow platform-level commands (platform:* without a provider segment like :hestia: or :bunny:)
        $isPlatformCommand = str_starts_with((string) $command, 'platform:')
            && ! str_contains((string) $command, ':hestia:')
            && ! str_contains((string) $command, ':bunny:');

        if ($isPlatformCommand) {
            return false;
        }

        // Allow provider-specific commands for this website's provider
        if (str_starts_with((string) $command, sprintf('platform:%s:', $website->provider))) {
            return false;
        }

        // Allow bunny commands (provider-agnostic)
        // Skip any other commands
        return ! str_starts_with((string) $command, 'platform:bunny:');
    }

    /**
     * Get step title safely.
     */
    private function getStepTitle(string $step): string
    {
        return config(sprintf('platform.website.steps.%s.title', $step), $step);
    }
}
