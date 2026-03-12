<?php

namespace App\Observers;

use App\Models\Settings;
use App\Services\SettingsCacheService;
use App\Support\CacheInvalidation;

/**
 * Observer to automatically invalidate settings cache on model changes.
 *
 * This ensures the cache is always in sync with the database without
 * requiring manual Cache::forget() calls throughout the codebase.
 */
class SettingsObserver
{
    public function __construct(
        private readonly SettingsCacheService $cacheService
    ) {}

    /**
     * Handle the Settings "created" event.
     */
    public function created(Settings $settings): void
    {
        $this->cacheService->invalidate('Setting created: '.$settings->key);
        CacheInvalidation::touch('Setting created: '.$settings->key);
    }

    /**
     * Handle the Settings "updated" event.
     */
    public function updated(Settings $settings): void
    {
        $this->cacheService->invalidate('Setting updated: '.$settings->key);
        CacheInvalidation::touch('Setting updated: '.$settings->key);
    }

    /**
     * Handle the Settings "deleted" event.
     */
    public function deleted(Settings $settings): void
    {
        $this->cacheService->invalidate('Setting deleted: '.$settings->key);
        CacheInvalidation::touch('Setting deleted: '.$settings->key);
    }

    /**
     * Handle the Settings "restored" event.
     */
    public function restored(Settings $settings): void
    {
        $this->cacheService->invalidate('Setting restored: '.$settings->key);
        CacheInvalidation::touch('Setting restored: '.$settings->key);
    }

    /**
     * Handle the Settings "force deleted" event.
     */
    public function forceDeleted(Settings $settings): void
    {
        $this->cacheService->invalidate('Setting force deleted: '.$settings->key);
        CacheInvalidation::touch('Setting force deleted: '.$settings->key);
    }
}
