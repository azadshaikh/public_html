<?php

namespace Modules\Platform\Services;

use App\Enums\ActivityAction;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Jobs\SendAgencyWebhook;
use Modules\Platform\Jobs\WebsiteDelete;
use Modules\Platform\Jobs\WebsiteExpired;
use Modules\Platform\Jobs\WebsiteSuspend;
use Modules\Platform\Jobs\WebsiteTrash;
use Modules\Platform\Jobs\WebsiteUnExpired;
use Modules\Platform\Jobs\WebsiteUnsuspend;
use Modules\Platform\Jobs\WebsiteUntrash;
use Modules\Platform\Jobs\WebsiteUpdate;
use Modules\Platform\Models\Website;

/**
 * Website Lifecycle Service - Status & Lifecycle Management
 * ============================================================================
 *
 * RESPONSIBILITIES:
 * ├── Status Changes: updateStatus() with job dispatch
 * ├── Destroy: trash (soft delete) and permanent delete
 * ├── Restore: untrash websites
 * └── Version Updates: Astero version upgrade dispatch
 *
 * DISPATCHES JOBS:
 * ├── WebsiteExpired, WebsiteUnExpired
 * ├── WebsiteSuspend, WebsiteUnsuspend
 * ├── WebsiteTrash, WebsiteUntrash
 * ├── WebsiteDelete, WebsiteUpdate
 *
 * NOT RESPONSIBLE FOR:
 * ├── CRUD Operations → WebsiteService
 * └── DataGrid/Filters → WebsiteService
 */
class WebsiteLifecycleService
{
    use ActivityTrait;

    /**
     * Updates an existing website.
     *
     * @param  Website  $website  The website to update.
     * @param  array  $data  The validated data from the request.
     * @return bool True on success, false on failure.
     */
    public function update(Website $website, array $data): bool
    {
        return DB::transaction(function () use ($website, $data): bool {
            $old_website_status = $website->status;

            $update_input = [
                'type' => $data['type'] ?? $website->type,
                'name' => $data['name'],
                'niches' => $data['niches'] ?? $website->niches,
                'server_id' => $data['server_id'],
                'agency_id' => $data['agency_id'],
                'status' => $data['status'] ?? $website->status,
                'updated_by' => auth()->id(),
            ];

            // Handle expired_on from form data
            if (array_key_exists('expired_on', $data) && ! empty($data['expired_on'])) {
                try {
                    $update_input['expired_on'] = Date::parse($data['expired_on']);
                } catch (Exception) {
                    $update_input['expired_on'] = null;
                }
            } elseif (array_key_exists('expired_on', $data)) {
                $update_input['expired_on'] = null;
            }

            // If status is being set to expired and no expired_on date is set, set it to now
            if (($data['status'] ?? null) === WebsiteStatus::Expired->value && empty($update_input['expired_on'])) {
                $update_input['expired_on'] = Date::now();
            }

            if (! $website->update($update_input)) {
                return false; // Should exception probably, but keeping existing pattern
            }

            // Manual handling of is_www
            if (isset($data['is_www'])) {
                $website->is_www = $data['is_www'];
                $website->save();
            }

            // Niches are handled via fillable - no manual handling needed

            // Handle DNS/CDN provider assignment via Providerable trait
            if (! empty($data['dns_provider_id'])) {
                $website->assignProvider($data['dns_provider_id'], true);
            }

            if (! empty($data['cdn_provider_id'])) {
                $website->assignProvider($data['cdn_provider_id'], true);
            }

            if ($website->status === WebsiteStatus::Expired) {
                dispatch(new WebsiteExpired($website));
            } elseif ($website->status === WebsiteStatus::Suspended) {
                dispatch(new WebsiteSuspend($website));
            } elseif ($website->status === WebsiteStatus::Active && in_array($old_website_status, [WebsiteStatus::Expired->value, WebsiteStatus::Suspended->value])) {
                if ($old_website_status === WebsiteStatus::Expired->value) {
                    dispatch(new WebsiteUnExpired($website));
                } else {
                    dispatch(new WebsiteUnsuspend($website));
                }
            }

            // Notify agency if status changed via edit form
            if ($website->status !== $old_website_status) {
                SendAgencyWebhook::dispatchForWebsite($website, 'website.status_changed', [
                    'previous_status' => $old_website_status instanceof WebsiteStatus ? $old_website_status->value : (string) $old_website_status,
                ]);
            }

            $this->logActivity($website, ActivityAction::UPDATE, 'Website updated successfully');

            return true;
        });
    }

    /**
     * Delete or trash a website.
     */
    public function destroy(Website $website): array
    {
        if (! empty($website->deleted_at)) {
            // Permanent delete - website is already trashed
            $website->updated_by = auth()->id();
            $website->save();

            dispatch(new WebsiteDelete($website->id))
                ->onQueue('default')
                ->afterResponse();

            $this->logActivity($website, ActivityAction::DELETE, 'Website permanent delete initiated');

            return [
                'status' => 'success',
                'message' => 'Website deletion started. The website will be removed from the server.',
                'redirect' => route('platform.websites.index', 'all'),
            ];
        }

        // Soft delete - move to trash
        $website->deleted_by = auth()->id();
        $website->status = WebsiteStatus::Trash;
        $website->updated_by = auth()->id();
        $website->save();

        if ($website->delete()) {
            dispatch(new WebsiteTrash($website->id))
                ->onQueue('default')
                ->afterResponse();
        }

        SendAgencyWebhook::dispatchForWebsiteAfterResponse($website, 'website.deleted', [
            'status' => 'trash',
        ]);

        $this->logActivity($website, ActivityAction::DELETE, 'Website trashed successfully');

        return [
            'status' => 'success',
            'message' => 'Website trashed successfully',
            'redirect' => route('platform.websites.show', $website->id),
        ];
    }

    /**
     * Update website status.
     */
    public function updateStatus(Website $website, string|WebsiteStatus $status): array
    {
        $statusEnum = $status instanceof WebsiteStatus ? $status : WebsiteStatus::tryFrom($status);

        if (! $statusEnum) {
            return [
                'status' => 'error',
                'message' => 'Invalid website status.',
                'code' => 422,
            ];
        }

        return DB::transaction(function () use ($website, $statusEnum): array {
            $oldStatus = $website->status;
            $oldStatusEnum = $oldStatus instanceof WebsiteStatus ? $oldStatus : WebsiteStatus::tryFrom((string) $oldStatus);
            $website->deleted_by = null;
            $website->deleted_at = null;
            $website->status = $statusEnum;

            if ($website->save()) {
                // Dispatch appropriate job based on status transition
                if ($statusEnum === WebsiteStatus::Suspended) {
                    // Suspending website
                    dispatch(new WebsiteSuspend($website));
                } elseif ($statusEnum === WebsiteStatus::Expired) {
                    // Expiring website
                    dispatch(new WebsiteExpired($website));
                } elseif ($statusEnum === WebsiteStatus::Active) {
                    // Activating website - dispatch appropriate job based on previous status
                    if ($oldStatusEnum === WebsiteStatus::Suspended) {
                        dispatch(new WebsiteUnsuspend($website));
                    } elseif ($oldStatusEnum === WebsiteStatus::Expired) {
                        dispatch(new WebsiteUnExpired($website));
                    }

                    // For provisioning → active, no server-side action needed
                }

                // Notify agency via webhook
                SendAgencyWebhook::dispatchForWebsite($website, 'website.status_changed', [
                    'previous_status' => $oldStatusEnum?->value,
                ]);

                $this->logActivity($website, ActivityAction::UPDATE, 'Website status updated to '.$statusEnum->value);

                return [
                    'status' => 'success',
                    'message' => 'Website status updated successfully. Server changes are being processed.',
                    'redirect' => route('platform.websites.show', $website->id),
                ];
            }

            return ['status' => 'error', 'message' => 'Error updating website status'];
        });
    }

    /**
     * Restore a trashed website.
     */
    public function restore(Website $website): array
    {
        dispatch(new WebsiteUntrash($website->id))
            ->onQueue('default')
            ->afterResponse();
        $website->update(['status' => WebsiteStatus::Active, 'deleted_by' => null, 'deleted_at' => null, 'updated_by' => auth()->id()]);

        SendAgencyWebhook::dispatchForWebsiteAfterResponse($website, 'website.restored');

        $this->logActivity($website, ActivityAction::RESTORE, 'Website restored successfully');

        return ['status' => 'success', 'message' => 'Website restored successfully', 'redirect' => route('platform.websites.index', 'all')];
    }

    /**
     * Update website version.
     */
    public function updateVersion(Website $website): array
    {
        $server_version = $website->server->astero_version;

        if ($server_version === $website->astero_version) {
            $message = 'Website version already updated.';
        } else {
            dispatch(new WebsiteUpdate($website))->onQueue('default');
            $message = 'Website update process started successfully.';
        }

        $this->logActivity($website, ActivityAction::UPDATE, 'Website version update.');

        return ['status' => 'success', 'message' => $message];
    }
}
