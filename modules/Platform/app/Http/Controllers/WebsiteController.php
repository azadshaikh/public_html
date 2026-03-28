<?php

namespace Modules\Platform\Http\Controllers;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use App\Scaffold\ScaffoldController;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Platform\Definitions\WebsiteDefinition;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Http\Controllers\Concerns\InteractsWithWebsitePresentation;
use Modules\Platform\Jobs\WebsiteProvision;
use Modules\Platform\Jobs\WebsiteRemoveFromServer;
use Modules\Platform\Jobs\WebsiteUpdatePrimaryHostname;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Secret;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\WebsiteLifecycleService;
use Modules\Platform\Services\WebsiteProvisioningService;
use Modules\Platform\Services\WebsiteService;
use RuntimeException;
use Throwable;

/**
 * Website Controller following BaseCrudController patterns.
 *
 * This controller handles the CRUD operations for websites using the
 * standardized BaseCrudController architecture with DataGrid support.
 * It also handles website lifecycle events (status, trash) and provisioning steps.
 */
class WebsiteController extends ScaffoldController implements HasMiddleware
{
    use InteractsWithWebsitePresentation;

    private const int WEBSITE_LARAVEL_LOG_TAIL_LINES = 400;

    public function __construct(
        private readonly WebsiteService $websiteService,
        private readonly WebsiteLifecycleService $websiteLifecycleService,
        private readonly WebsiteProvisioningService $websiteProvisioningService
    ) {}

    // =============================================================================
    // MIDDLEWARE
    // =============================================================================

    public static function middleware(): array
    {
        return [
            // Standard CRUD middleware from definition
            ...(new WebsiteDefinition)->getMiddleware(),
            // Custom endpoint permissions
            new Middleware('permission:view_websites', only: ['websiteLog']),
            new Middleware('permission:add_websites', only: ['createFromOrder']),
            new Middleware('permission:edit_websites', only: ['updateStatus', 'updateVersion', 'syncWebsite', 'recacheApplication', 'retryProvision', 'executeStep', 'revertStep', 'updateSetupStatus', 'setupQueueWorker', 'scaleQueueWorker', 'reprovision', 'revealSecret', 'confirmDns', 'stopDnsValidation', 'clearWebsiteLog', 'websiteEnv', 'updateWebsiteEnv', 'updatePrimaryHostname']),
            new Middleware('permission:delete_websites', only: ['removeFromServer']),
        ];
    }

    public function revealSecret(Request $request, int|string $website, int|string $secret): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        /** @var Website $websiteModel */
        $websiteModel = Website::withTrashed()->findOrFail((int) $website);

        /** @var Secret $secretModel */
        $secretModel = $websiteModel->secrets()->whereKey((int) $secret)->firstOrFail();

        $this->logActivity($websiteModel, ActivityAction::VIEW, sprintf("Revealed website secret '%s'.", $secretModel->key));

        return response()
            ->json([
                'success' => true,
                'value' => $secretModel->decrypted_value,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function show(int|string $id): Response
    {
        $website = Website::withTrashed()
            ->with(['server', 'agency', 'providers', 'domainRecord', 'sslCertificate'])
            ->findOrFail((int) $id);

        abort_unless($website instanceof Website, 404);

        // Get activity logs for this website
        $activities = ActivityLog::query()
            ->forModel(Website::class, $website->id)
            ->with('causer')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $canRevealSecrets = (bool) auth()->user()?->can('edit_websites');
        $provisioningPayload = $this->buildProvisioningStatusPayload($website);

        return Inertia::render($this->inertiaPage().'/show', [
            'website' => $this->transformWebsiteForShow($website),
            'secrets' => ($canRevealSecrets ? $website->secrets()->orderBy('key')->get() : collect())
                ->map(fn ($secret): array => [
                    'id' => $secret->getKey(),
                    'key' => (string) $secret->key,
                    'label' => str($secret->key)->replace('_', ' ')->headline()->toString(),
                    'username' => $secret->username,
                ])
                ->values()
                ->all(),
            'provisioningSteps' => $provisioningPayload['provisioning_steps'],
            'provisioningRun' => $provisioningPayload['provisioning_run'],
            'updates' => collect($website->getUpdateHistoryForView())
                ->map(function ($value, $key): array {
                    if (is_array($value) || is_object($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    }

                    return [
                        'key' => (string) $key,
                        'label' => str((string) $key)->replace('_', ' ')->headline()->toString(),
                        'value' => (string) ($value ?? '—'),
                    ];
                })
                ->values()
                ->all(),
            'activities' => $activities->map(fn ($activity): array => [
                'id' => $activity->getKey(),
                'description' => (string) ($activity->description ?? $activity->message ?? 'Activity recorded'),
                'created_at' => app_date_time_format($activity->created_at, 'datetime'),
                'causer_name' => $activity->causer?->name ?? $activity->causer?->first_name ?? null,
            ])->values()->all(),
            'pullzoneId' => $website->pullzone_id,
            'canRevealSecrets' => $canRevealSecrets,
            'canManageLaravelLog' => (bool) auth()->user()?->can('edit_websites'),
            'canManageWebsiteEnv' => (bool) auth()->user()?->can('edit_websites'),
        ]);
    }

    /**
     * @return array{
     *     status: string,
     *     website_steps_data: Collection,
     *     provisioning_steps: array<int, array<string, mixed>>,
     *     provisioning_run: array{started_at: string|null, completed_at: string|null},
     *     percentage: float|int,
     *     current_status: string|null
     * }
     */
    // =============================================================================
    // CUSTOM DESTROY & RESTORE (delegates to WebsiteLifecycleService)
    // =============================================================================

    public function destroy(int|string $id): RedirectResponse
    {
        /** @var Website $website */
        $website = Website::withTrashed()->findOrFail((int) $id);

        $result = $this->websiteLifecycleService->destroy($website);

        if ($result['status'] === 'success') {
            $message = $result['message'];
            $redirect = $result['redirect'] ?? route($this->scaffold()->getIndexRoute());

            return redirect($redirect)->with('success', $message);
        }

        return back()
            ->with('error', $result['message']);
    }

    public function restore(int|string $id): RedirectResponse
    {
        /** @var Website $website */
        $website = Website::withTrashed()->findOrFail((int) $id);

        $result = $this->websiteLifecycleService->restore($website);

        if ($result['status'] === 'success') {
            return back()
                ->with('success', $result['message']);
        }

        return back()
            ->with('error', $result['message']);
    }

    public function forceDelete(int|string $id): RedirectResponse
    {
        /** @var Website $website */
        $website = Website::withTrashed()->findOrFail((int) $id);

        // Force the deleted_at to trigger permanent delete logic
        if (empty($website->deleted_at)) {
            $website->delete();
        }

        $result = $this->websiteLifecycleService->destroy($website);

        if ($result['status'] === 'success') {
            return to_route($this->scaffold()->getIndexRoute(), ['status' => 'trash'])
                ->with('success', 'Website has been permanently deleted');
        }

        return back()
            ->with('error', $result['message']);
    }

    // =============================================================================
    // ADDITIONAL ACTIONS
    // =============================================================================

    public function updateStatus(Request $request, $id, string $status): JsonResponse|RedirectResponse
    {
        try {
            /** @var Website $website */
            $website = Website::query()->findOrFail((int) $id);

            $result = $this->websiteLifecycleService->updateStatus($website, $status);

            if ($result['status'] === 'success') {
                if ($request->expectsJson()) {
                    return response()->json([
                        'status' => 'success',
                        'message' => $result['message'],
                    ]);
                }

                return to_route($this->scaffold()->getIndexRoute())
                    ->with('success', $result['message']);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message'],
                ], $result['code'] ?? 500);
            }

            return back()
                ->with('error', $result['message']);
        } catch (Exception $exception) {
            Log::error('Error updating website status', [
                'website_id' => $id,
                'status' => $status,
                'error' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error updating website status: '.$exception->getMessage(),
                ]);
            }

            return back()
                ->with('error', 'Error updating website status');
        }
    }

    public function updateVersion(Request $request, $id): JsonResponse|RedirectResponse
    {
        /** @var Website $website */
        $website = Website::query()->findOrFail((int) $id);

        $result = $this->websiteLifecycleService->updateVersion($website);

        if ($result['status'] === 'success') {
            $this->logActivity($website, ActivityAction::UPDATE, 'Website version update initiated');

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                ]);
            }

            return back()
                ->with('success', $result['message']);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
            ]);
        }

        return back()
            ->with('error', $result['message']);
    }

    /**
     * Sync website information from the remote server.
     */
    public function syncWebsite(Request $request, $id): JsonResponse|RedirectResponse
    {
        /** @var Website $website */
        $website = Website::query()->findOrFail((int) $id);

        $result = $this->websiteService->syncWebsiteInfo($website);

        if ($result['success']) {
            $this->logActivity($website, ActivityAction::UPDATE, 'Website synced: '.$result['message']);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => $result['message'],
                    'data' => $result['data'] ?? [],
                ]);
            }

            return back()
                ->with('success', $result['message']);
        }

        // Check if it's just an info response (no changes)
        if (! empty($result['info'])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'info',
                    'message' => $result['message'],
                ]);
            }

            return back()
                ->with('info', $result['message']);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'error',
                'message' => $result['message'],
            ]);
        }

        return back()
            ->with('error', $result['message']);
    }

    /**
     * Run astero:recache on the remote website application.
     */
    public function recacheApplication(Request $request, $id): JsonResponse|RedirectResponse
    {
        try {
            /** @var Website $website */
            $website = Website::query()->findOrFail((int) $id);

            $exitCode = Artisan::call('platform:hestia:recache-application', [
                'website_id' => $website->id,
            ]);

            throw_if($exitCode !== 0, RuntimeException::class, 'Remote recache command returned a non-zero exit code.');

            $this->logActivity($website, ActivityAction::UPDATE, 'Application recache initiated');

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Application recache has been executed successfully.',
                ]);
            }

            return back()
                ->with('success', 'Application recache has been executed successfully.');
        } catch (Exception $exception) {
            Log::error('Error running website application recache', [
                'website_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error running application recache: '.$exception->getMessage(),
                ]);
            }

            return back()
                ->with('error', 'Error running application recache: '.$exception->getMessage());
        }
    }

    public function updatePrimaryHostname(Request $request, $id, string $hostnameType): JsonResponse
    {
        if (! in_array($hostnameType, ['apex', 'www'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid primary hostname selection.',
            ], 422);
        }

        /** @var Website $website */
        $website = Website::withTrashed()->with(['domainRecord', 'providers', 'server'])->findOrFail((int) $id);

        if (! $website->supportsWwwFeature()) {
            return response()->json([
                'status' => 'error',
                'message' => 'WWW primary hostname is only supported for apex domains.',
            ], 422);
        }

        try {
            dispatch(new WebsiteUpdatePrimaryHostname(
                websiteId: (int) $website->id,
                useWww: $hostnameType === 'www',
                requestedByUserId: auth()->id(),
            ))->onQueue('default');
        } catch (Throwable $throwable) {
            Log::error('Failed to dispatch primary hostname update job', [
                'website_id' => $website->id,
                'hostname_type' => $hostnameType,
                'error' => $throwable->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Unable to queue primary hostname update right now. Please try again.',
            ], 500);
        }

        return response()->json([
            'status' => 'info',
            'message' => 'Primary hostname update queued. Refresh in a few moments to see the final state.',
        ], 202);
    }

    // =============================================================================
    // PROVISIONING STEPS
    // =============================================================================

    public function executeStep(Request $request, $id, string $step): JsonResponse
    {
        /** @var Website $website */
        $website = Website::query()->findOrFail((int) $id);
        $result = $this->websiteProvisioningService->executeStep($website, $step);

        return response()->json($result);
    }

    public function revertStep(Request $request, $id, string $step): JsonResponse
    {
        /** @var Website $website */
        $website = Website::query()->findOrFail((int) $id);
        $result = $this->websiteProvisioningService->revertStep($website, $step);

        return response()->json($result);
    }

    /**
     * Retry provisioning for a failed website.
     * Resets failed step statuses to pending and dispatches the provision job.
     */
    public function retryProvision(Request $request, $id): JsonResponse|RedirectResponse
    {
        /** @var Website $website */
        $website = Website::query()->findOrFail((int) $id);

        // Only allow retry for failed websites
        if ($website->status !== WebsiteStatus::Failed) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Website is not in a failed state.',
                ], 400);
            }

            return back()
                ->with('error', 'Website is not in a failed state.');
        }

        // Reset failed provisioning steps to pending so they can be retried
        $metadata = $website->metadata ?? [];
        if (isset($metadata['provisioning_steps'])) {
            // Check if we need to reset steps to ensure re-run
            foreach ($metadata['provisioning_steps'] as $key => $step) {
                if (isset($step['status']) && $step['status'] === 'failed') {
                    $metadata['provisioning_steps'][$key]['status'] = 'pending';
                    $metadata['provisioning_steps'][$key]['message'] = null;
                    $metadata['provisioning_steps'][$key]['started_at'] = null;
                    $metadata['provisioning_steps'][$key]['completed_at'] = null;
                }
            }

            $website->metadata = $metadata;
        }

        // Set status back to provisioning
        $website->status = WebsiteStatus::Provisioning;
        $website->save();
        $website->resetProvisioningRun();

        // Dispatch the provisioning job
        dispatch(new WebsiteProvision($website));

        $this->logActivity($website, ActivityAction::UPDATE, 'Provisioning retry initiated');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Provisioning retry has been initiated. The website will be provisioned in the background.',
            ]);
        }

        return to_route('platform.websites.show', $website->id)
            ->with('success', 'Provisioning retry has been initiated. The website will be provisioned in the background.');
    }

    /**
     * Revert a website update.
     *
     * @todo This endpoint is not yet implemented. Implementation should:
     *       1. Fetch the WebsiteUpdate record using $update_id
     *       2. Restore backup files and database from before the update
     *       3. Update the website status and log the revert action
     *       See original StepController lines 116-148 for reference logic.
     */
    public function revertUpdate(Request $request, $id): JsonResponse
    {
        // Validate that the website exists
        Website::query()->findOrFail((int) $id);

        // TODO: Implement revert update logic
        // - Fetch WebsiteUpdate by $request->route('update_id')
        // - Restore files/database from backup
        // - Update website status
        // - Log the revert action

        return response()->json([
            'status' => 'error',
            'message' => 'Revert update functionality is not yet implemented. Please contact support.',
        ], 501);
    }

    public function updateSetupStatus(Request $request, $id): JsonResponse
    {
        try {
            /** @var Website $website */
            $website = Website::query()->findOrFail((int) $id);
            $updateData = ['updated_by' => auth()->id()];

            if ($request->has('backup_setup')) {
                $updateData['backup_setup'] = $request->backup_setup;
            }

            if ($request->has('setup_complete_flag')) {
                $updateData['setup_complete_flag'] = $request->setup_complete_flag;
            }

            $website->update($updateData);
            $this->logActivity($website, ActivityAction::UPDATE, 'Website setup status updated');

            return response()->json([
                'status' => 'success',
                'message' => 'Website setup status updated successfully',
            ]);
        } catch (Exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error updating website setup status',
            ]);
        }
    }

    /**
     * Setup Supervisor queue workers for a website.
     */
    public function setupQueueWorker(Request $request, $id): JsonResponse|RedirectResponse
    {
        try {
            /** @var Website $website */
            $website = Website::query()->findOrFail((int) $id);

            // Call the Artisan command to setup queue workers
            Artisan::call('platform:hestia:setup-queue-worker', [
                'website_id' => $website->id,
            ]);

            $this->logActivity($website, ActivityAction::UPDATE, 'Queue workers setup initiated');

            // Sync website info to update queue worker status in metadata
            $this->websiteService->syncWebsiteInfo($website);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Queue workers have been set up. Syncing website info...',
                ]);
            }

            return back()
                ->with('success', 'Queue workers have been set up successfully.');
        } catch (Exception $exception) {
            Log::error('Error setting up queue workers', [
                'website_id' => $id,
                'error' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error setting up queue workers: '.$exception->getMessage(),
                ]);
            }

            return back()
                ->with('error', 'Error setting up queue workers: '.$exception->getMessage());
        }
    }

    /**
     * Scale Supervisor queue workers for a website.
     */
    public function scaleQueueWorker(Request $request, $id, $count): JsonResponse|RedirectResponse
    {
        try {
            /** @var Website $website */
            $website = Website::query()->findOrFail((int) $id);
            $workerCount = max(1, min(10, (int) $count)); // Limit between 1-10

            // Call the Artisan command to scale queue workers
            Artisan::call('platform:hestia:manage-queue-worker', [
                'website_id' => $website->id,
                'action' => 'scale',
                '--workers' => $workerCount,
            ]);

            $this->logActivity($website, ActivityAction::UPDATE, 'Queue workers scaled to '.$workerCount);

            // Sync website info to update queue worker status in metadata
            $this->websiteService->syncWebsiteInfo($website);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => sprintf('Queue workers scaled to %d.', $workerCount),
                ]);
            }

            return back()
                ->with('success', sprintf('Queue workers scaled to %d successfully.', $workerCount));
        } catch (Exception $exception) {
            Log::error('Error scaling queue workers', [
                'website_id' => $id,
                'count' => $count,
                'error' => $exception->getMessage(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Error scaling queue workers: '.$exception->getMessage(),
                ]);
            }

            return back()
                ->with('error', 'Error scaling queue workers: '.$exception->getMessage());
        }
    }

    /**
     * Remove website from Hestia server but keep database record.
     */
    public function removeFromServer(Request $request, $id): JsonResponse|RedirectResponse
    {
        /** @var Website $website */
        $website = Website::withTrashed()->findOrFail((int) $id);

        if (empty($website->deleted_at)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only trashed websites can be removed from the server',
                ], 400);
            }

            return back()
                ->with('error', 'Only trashed websites can be removed from the server');
        }

        if ($website->status === WebsiteStatus::Deleted) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This website has already been removed from the server',
                ], 409);
            }

            return back()
                ->with('error', 'This website has already been removed from the server');
        }

        // Dispatch the job to remove from server
        dispatch(new WebsiteRemoveFromServer($website->id));

        $this->logActivity($website, ActivityAction::UPDATE, 'Website removal from server initiated');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Website removal from server has been queued',
            ]);
        }

        return back()
            ->with('success', 'Website removal from server has been queued. The record will be kept for historical tracking.');
    }

    /**
     * Re-provision a deleted website (create fresh Hestia user/files).
     */
    public function reprovision(Request $request, $id): JsonResponse|RedirectResponse
    {
        /** @var Website $website */
        $website = Website::withTrashed()->findOrFail((int) $id);

        // Only allow re-provisioning of deleted websites
        if ($website->status !== WebsiteStatus::Deleted) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Only websites with "Deleted" status can be re-provisioned',
                ], 400);
            }

            return back()
                ->with('error', 'Only websites with "Deleted" status can be re-provisioned');
        }

        if (empty($website->deleted_at)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This website is not in trash and cannot be re-provisioned',
                ], 400);
            }

            return back()
                ->with('error', 'This website is not in trash and cannot be re-provisioned');
        }

        // Reset provisioning steps in metadata
        $website->setMetadata('provisioning_steps', null);
        $website->status = WebsiteStatus::Provisioning;
        $website->restore(); // Restore from soft delete
        $website->deleted_by = null;
        $website->updated_by = auth()->id();
        $website->save();
        $website->resetProvisioningRun();

        // Dispatch the provision job
        dispatch(new WebsiteProvision($website));

        $this->logActivity($website, ActivityAction::UPDATE, 'Website re-provisioning initiated');

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Website re-provisioning has been started',
            ]);
        }

        return to_route($this->scaffold()->getShowRoute(), $website->id)
            ->with('success', 'Website re-provisioning has been started. Check the Provision tab for progress.');
    }

    protected function service(): WebsiteService
    {
        return $this->websiteService;
    }

    protected function inertiaPage(): string
    {
        return 'platform/websites';
    }

    protected function getFormViewData(Model $model): array
    {
        /** @var Website $website */
        $website = $model;

        return [
            'initialValues' => $this->buildWebsiteInitialValues($website),
            'serverOptions' => Server::getServerOptions(),
            'agencyOptions' => Agency::getAgencyOptions(),
            'statusOptions' => $this->websiteService->getStatusOptionsForForm(),
            'typeOptions' => $this->websiteService->getTypeOptionsForForm(),
            'planOptions' => $this->websiteService->getPlanOptionsForForm(),
            'dnsModeOptions' => $this->websiteService->getDnsModeOptionsForForm(),
            'dnsProviderOptions' => Provider::getProviderOptions(Provider::TYPE_DNS),
            'cdnProviderOptions' => Provider::getProviderOptions(Provider::TYPE_CDN),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var Website $website */
        $website = $model;

        return [
            'id' => $website->getKey(),
            'name' => $website->name,
            'uid' => $website->uid,
        ];
    }

    // =============================================================================
    // CUSTOMIZATIONS
    // =============================================================================

    protected function capturePreviousValues(Model $model): array
    {
        if (! $model instanceof Website) {
            return [];
        }

        return [
            'name' => $model->name,
            'domain' => $model->domain,
            'server_id' => $model->server_id,
            'agency_id' => $model->agency_id,
            'status' => $model->status,
            'type' => $model->type,
        ];
    }

    /**
     * Handle side effects after website creation.
     * Processes order updates if created via "Create from Order" flow.
     */
    protected function handleCreationSideEffects(Model $model): void
    {
        if (! $model instanceof Website) {
            return;
        }

        // Handle order update if an order_id is present in the request
        if (request()->has('order_id')) {
            $this->processOrderUpdate($model, request());
        }
    }

    /**
     * Override success message for website creation
     */
    protected function buildCreateSuccessMessage(Model $model): string
    {
        return 'Website created successfully. Provisioning started.';
    }
}
