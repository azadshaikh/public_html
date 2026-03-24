<?php

declare(strict_types=1);

namespace Modules\Agency\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Modules\Agency\Definitions\WebsiteManageDefinition;
use Modules\Agency\Enums\WebsiteStatus;
use Modules\Agency\Exceptions\PlatformApiException;
use Modules\Agency\Http\Resources\WebsiteManageResource;
use Modules\Agency\Models\AgencyWebsite;

/**
 * Service for Agency admin website management.
 *
 * Business data (type, plan, expiry, customer) is managed locally.
 * Infrastructure actions (suspend, restore, etc.) are delegated to Platform API.
 */
class WebsiteManageService implements ScaffoldServiceInterface
{
    use Scaffoldable {
        applyFilters as traitApplyFilters;
    }

    public function __construct(
        private readonly PlatformApiClient $platformApi,
    ) {}

    // ──────────────────────────────────────────────────────────
    // Scaffold Configuration
    // ──────────────────────────────────────────────────────────

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new WebsiteManageDefinition;
    }

    // ──────────────────────────────────────────────────────────
    // Statistics
    // ──────────────────────────────────────────────────────────

    public function getStatistics(): array
    {
        return [
            'total' => AgencyWebsite::query()->whereNull('deleted_at')->count(),
            'active' => AgencyWebsite::query()->where('status', 'active')->whereNull('deleted_at')->count(),
            'provisioning' => AgencyWebsite::query()->where('status', 'provisioning')->whereNull('deleted_at')->count(),
            'failed' => AgencyWebsite::query()->where('status', 'failed')->whereNull('deleted_at')->count(),
            'suspended' => AgencyWebsite::query()->where('status', 'suspended')->whereNull('deleted_at')->count(),
            'expired' => AgencyWebsite::query()->where('status', 'expired')->whereNull('deleted_at')->count(),
            'trash' => AgencyWebsite::onlyTrashed()->count(),
        ];
    }

    // ──────────────────────────────────────────────────────────
    // CRUD - Local business data management
    // ──────────────────────────────────────────────────────────

    /**
     * Update business data locally and sync to Platform if needed.
     */
    public function update(Model $model, array $data): AgencyWebsite
    {
        /** @var AgencyWebsite $website */
        $website = $model;

        $updateData = [
            'name' => $data['name'] ?? $website->name,
            'type' => $data['type'] ?? $website->type,
            'plan' => $data['plan'] ?? $website->plan,
            'expired_on' => $data['expired_on'] ?? $website->expired_on,
            'updated_by' => auth()->id(),
        ];

        // Handle customer data
        if (isset($data['customer_ref']) || isset($data['customer_name']) || isset($data['customer_email'])) {
            $updateData['customer_ref'] = $data['customer_ref'] ?? $website->customer_ref;
            $updateData['customer_data'] = array_filter([
                'ref' => $data['customer_ref'] ?? $website->customer_ref,
                'name' => $data['customer_name'] ?? ($website->customer_data['name'] ?? null),
                'email' => $data['customer_email'] ?? ($website->customer_data['email'] ?? null),
                'company' => $data['customer_company'] ?? ($website->customer_data['company'] ?? null),
                'phone' => $data['customer_phone'] ?? ($website->customer_data['phone'] ?? null),
            ], fn ($v): bool => $v !== null);
        }

        // Handle plan data
        if (isset($data['plan_ref'])) {
            $updateData['plan_ref'] = $data['plan_ref'];
            $existingPlanData = $website->plan_data ?? [];
            $updateData['plan_data'] = array_merge($existingPlanData, array_filter([
                'ref' => $data['plan_ref'],
                'name' => $data['plan'] ?? ($existingPlanData['name'] ?? null),
            ], fn ($v): bool => $v !== null));
        }

        $website->update($updateData);

        // Sync business data to Platform in background
        $this->syncBusinessDataToPlatform($website);

        return $website->fresh();
    }

    /**
     * Soft-delete via Platform API (bulk-action compatible).
     */
    public function delete(Model $model): void
    {
        /** @var AgencyWebsite $model */
        $this->trashWebsite($model);
    }

    /**
     * Restore from trash via Platform API (bulk-action compatible).
     */
    public function restore(int|string $id): Model
    {
        $website = AgencyWebsite::withTrashed()->findOrFail((int) $id);
        $this->restoreWebsite($website);

        return $website->fresh();
    }

    // ──────────────────────────────────────────────────────────
    // Lifecycle Actions (via Platform API)
    // ──────────────────────────────────────────────────────────

    /**
     * Suspend a website via Platform API.
     */
    public function suspendWebsite(AgencyWebsite $website): array
    {
        try {
            $this->platformApi->changeWebsiteStatus($website->site_id, 'suspended');
            $website->update(['status' => WebsiteStatus::Suspended, 'updated_by' => auth()->id()]);

            return ['status' => 'success', 'message' => 'Website suspended successfully.'];
        } catch (PlatformApiException $platformApiException) {
            Log::error('Failed to suspend website via Platform API', [
                'site_id' => $website->site_id,
                'error' => $platformApiException->getMessage(),
            ]);

            return ['status' => 'error', 'message' => 'Failed to suspend website: '.$platformApiException->getMessage()];
        }
    }

    /**
     * Unsuspend a website via Platform API.
     */
    public function unsuspendWebsite(AgencyWebsite $website): array
    {
        try {
            $this->platformApi->changeWebsiteStatus($website->site_id, 'active');
            $website->update(['status' => WebsiteStatus::Active, 'updated_by' => auth()->id()]);

            return ['status' => 'success', 'message' => 'Website unsuspended successfully.'];
        } catch (PlatformApiException $platformApiException) {
            Log::error('Failed to unsuspend website via Platform API', [
                'site_id' => $website->site_id,
                'error' => $platformApiException->getMessage(),
            ]);

            return ['status' => 'error', 'message' => 'Failed to unsuspend website: '.$platformApiException->getMessage()];
        }
    }

    /**
     * Trash (soft-delete) a website via Platform API.
     */
    public function trashWebsite(AgencyWebsite $website): array
    {
        try {
            $this->platformApi->destroyWebsite($website->site_id);
            $website->update([
                'status' => WebsiteStatus::Trash,
                'deleted_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]);
            $website->delete();

            return ['status' => 'success', 'message' => 'Website moved to trash.'];
        } catch (PlatformApiException $platformApiException) {
            Log::error('Failed to trash website via Platform API', [
                'site_id' => $website->site_id,
                'error' => $platformApiException->getMessage(),
            ]);

            return ['status' => 'error', 'message' => 'Failed to trash website: '.$platformApiException->getMessage()];
        }
    }

    /**
     * Permanently delete a trashed website via Platform API.
     */
    public function forceDeleteWebsite(AgencyWebsite $website): array
    {
        if (! $website->trashed()) {
            return ['status' => 'error', 'message' => 'Website must be in trash before permanent deletion.'];
        }

        try {
            $this->platformApi->forceDeleteWebsite($website->site_id);
            $website->forceDelete();

            return ['status' => 'success', 'message' => 'Website permanently deleted.'];
        } catch (PlatformApiException $platformApiException) {
            Log::error('Failed to permanently delete website via Platform API', [
                'site_id' => $website->site_id,
                'error' => $platformApiException->getMessage(),
            ]);

            return ['status' => 'error', 'message' => 'Failed to permanently delete website: '.$platformApiException->getMessage()];
        }
    }

    /**
     * Restore a trashed website via Platform API.
     */
    public function restoreWebsite(AgencyWebsite $website): array
    {
        try {
            $this->platformApi->restoreWebsite($website->site_id);
            $website->restore();
            $website->update([
                'status' => WebsiteStatus::Active,
                'deleted_by' => null,
                'updated_by' => auth()->id(),
            ]);

            return ['status' => 'success', 'message' => 'Website restored successfully.'];
        } catch (PlatformApiException $platformApiException) {
            Log::error('Failed to restore website via Platform API', [
                'site_id' => $website->site_id,
                'error' => $platformApiException->getMessage(),
            ]);

            return ['status' => 'error', 'message' => 'Failed to restore website: '.$platformApiException->getMessage()];
        }
    }

    /**
     * Sync website info from Platform (pulls latest status, version, etc.)
     */
    public function syncFromPlatform(AgencyWebsite $website): array
    {
        try {
            $response = $this->platformApi->syncWebsite($website->site_id);
            $apiData = $response['data'];

            // Update infrastructure data from Platform
            $website->update(array_filter([
                'status' => $apiData['status'] ?? null,
                'server_name' => $apiData['server_name'] ?? null,
                'astero_version' => $apiData['astero_version'] ?? null,
                'admin_slug' => $apiData['admin_slug'] ?? null,
            ], fn ($v): bool => $v !== null));

            return ['status' => 'success', 'message' => 'Website synced from Platform.'];
        } catch (PlatformApiException $platformApiException) {
            Log::error('Failed to sync website from Platform', [
                'site_id' => $website->site_id,
                'error' => $platformApiException->getMessage(),
            ]);

            return ['status' => 'error', 'message' => 'Sync failed: '.$platformApiException->getMessage()];
        }
    }

    /**
     * Retry failed provisioning via Platform API.
     */
    public function retryProvision(AgencyWebsite $website): array
    {
        try {
            $this->platformApi->retryProvision($website->site_id);
            $website->update(['status' => WebsiteStatus::Provisioning, 'updated_by' => auth()->id()]);

            return ['status' => 'success', 'message' => 'Provisioning retry initiated.'];
        } catch (PlatformApiException $platformApiException) {
            Log::error('Failed to retry provisioning via Platform API', [
                'site_id' => $website->site_id,
                'error' => $platformApiException->getMessage(),
            ]);

            return ['status' => 'error', 'message' => 'Failed to retry: '.$platformApiException->getMessage()];
        }
    }

    // ──────────────────────────────────────────────────────────
    // Form Options
    // ──────────────────────────────────────────────────────────

    public function getTypeOptions(): array
    {
        return [
            ['value' => 'trial', 'label' => 'Trial'],
            ['value' => 'free', 'label' => 'Free'],
            ['value' => 'paid', 'label' => 'Paid'],
            ['value' => 'internal', 'label' => 'Internal'],
            ['value' => 'special', 'label' => 'Special'],
        ];
    }

    public function getStatusOptions(): array
    {
        return array_map(
            fn (WebsiteStatus $s): array => ['value' => $s->value, 'label' => $s->label()],
            [WebsiteStatus::Active, WebsiteStatus::Suspended, WebsiteStatus::Expired]
        );
    }

    protected function getResourceClass(): ?string
    {
        return WebsiteManageResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'owner:id,first_name,last_name,email',
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ];
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $this->traitApplyFilters($query, $request);

        if ($request->filled('customer_ref')) {
            $query->where('customer_ref', $request->input('customer_ref'));
        }
    }

    /**
     * Handle custom bulk actions (suspend / unsuspend).
     *
     * Standard delete and restore are handled natively by the Scaffoldable trait
     * via the delete() and restore() service method overrides above.
     *
     * @param  array<int>  $ids
     * @return array{affected: int, message: string}
     */
    protected function handleCustomBulkAction(string $action, array $ids, Request $request): array
    {
        $websites = AgencyWebsite::withTrashed()->whereIn('id', $ids)->get();

        if ($websites->isEmpty()) {
            return ['affected' => 0, 'message' => 'No websites found.'];
        }

        $succeeded = 0;

        foreach ($websites as $website) {
            $result = match ($action) {
                'suspend' => $this->suspendWebsite($website),
                'unsuspend' => $this->unsuspendWebsite($website),
                default => ['status' => 'error', 'message' => 'Unknown action.'],
            };

            if ($result['status'] === 'success') {
                $succeeded++;
            }
        }

        return ['affected' => $succeeded, 'message' => $succeeded.' websites processed.'];
    }

    // ──────────────────────────────────────────────────────────
    // Private Helpers
    // ──────────────────────────────────────────────────────────

    /**
     * Sync business data changes to Platform (customer, plan).
     */
    private function syncBusinessDataToPlatform(AgencyWebsite $website): void
    {
        try {
            if (! empty($website->customer_data)) {
                $this->platformApi->updateWebsiteCustomer($website->site_id, $website->customer_info);
            }

            if (! empty($website->plan_data)) {
                $this->platformApi->updateWebsitePlan($website->site_id, $website->plan_info);
            }
        } catch (PlatformApiException $platformApiException) {
            Log::warning('Failed to sync business data to Platform', [
                'site_id' => $website->site_id,
                'error' => $platformApiException->getMessage(),
            ]);
            // Non-critical — local data is saved, Platform sync can be retried
        }
    }
}
