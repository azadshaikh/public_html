<?php

declare(strict_types=1);

namespace App\Support;

use App\Jobs\RecacheApplication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CacheInvalidation
{
    /**
     * Whether a model change affects public-facing output.
     *
     * If the model has a `status` attribute, we consider it public-facing when:
     * - current status is in $publicStatuses, OR
     * - previous status (if provided) was in $publicStatuses (covers unpublish/unlist)
     *
     * Models without `status` are assumed public-affecting.
     */
    public static function affectsPublic(
        Model $model,
        ?array $previousValues = null,
        array $publicStatuses = ['published', 'scheduled']
    ): bool {
        try {
            $attributes = $model->getAttributes();

            if (! array_key_exists('status', $attributes)) {
                return true;
            }

            $current = strtolower(trim((string) $model->getAttribute('status')));
            $previous = strtolower(trim((string) ($previousValues['status'] ?? '')));
            if ($previous === '') {
                $previous = strtolower(trim((string) ($model->getOriginal('status') ?? '')));
            }

            $allowed = array_map(static fn ($s) => strtolower(trim((string) $s)), $publicStatuses);

            return in_array($current, $allowed, true) || in_array($previous, $allowed, true);
        } catch (Throwable) {
            // Best-effort: if status inspection fails, assume public-affecting.
            return true;
        }
    }

    /**
     * Touch caches only if the given model affects public-facing output.
     *
     * Rule: if the model has a `status` attribute, we only invalidate when:
     * - current status is in $publicStatuses, OR
     * - previous status (if provided) was in $publicStatuses (covers unpublish/unlist)
     *
     * Models without a `status` attribute always invalidate (e.g. Settings).
     */
    public static function touchForModel(
        Model $model,
        string $reason,
        ?array $previousValues = null,
        array $publicStatuses = ['published', 'scheduled'],
        bool $invalidateFrontendCache = true,
        bool $clearUnpolyCache = true,
        bool $dispatchRecache = true
    ): bool {
        if (! self::affectsPublic($model, $previousValues, $publicStatuses)) {
            if ($clearUnpolyCache) {
                self::flagUnpolyClearCache();
            }

            return false;
        }

        self::touch($reason, $invalidateFrontendCache, $clearUnpolyCache, $dispatchRecache);

        return true;
    }

    /**
     * Mark the application caches as dirty after a content/settings mutation.
     *
     * - Flags current Unpoly request to clear its client cache
     * - Dispatches astero:recache asynchronously (debounced)
     */
    public static function touch(
        string $reason,
        bool $invalidateFrontendCache = true,
        bool $clearUnpolyCache = true,
        bool $dispatchRecache = true
    ): void {
        if ($clearUnpolyCache) {
            self::flagUnpolyClearCache();
        }

        if ($invalidateFrontendCache && $dispatchRecache) {
            self::dispatchRecacheDebounced($reason);
        }
    }

    /**
     * Clear the application cache store.
     */
    public static function clearCacheStore(): void
    {
        try {
            Artisan::call('cache:clear');
        } catch (Throwable) {
            try {
                Artisan::call('cache:clear');
            } catch (Throwable) {
                // Best-effort: cache clearing should never break the request.
            }
        }
    }

    private static function flagUnpolyClearCache(): void
    {
        try {
            if (app()->runningInConsole()) {
                return;
            }

            $request = request();
            $request->attributes->set('astero.unpoly_clear_cache', true);
        } catch (Throwable) {
            // Best-effort
        }
    }

    private static function dispatchRecacheDebounced(string $reason): void
    {
        // Debounce across requests to avoid queue storms.
        // If multiple models change within ~30 seconds, we only queue one recache.
        $debounceKey = 'astero.recache.debounce';
        $seconds = 30;

        try {
            if (! Cache::add($debounceKey, now()->timestamp, $seconds)) {
                return;
            }

            // Avoid dispatching multiple times within the same request too.
            if (! app()->runningInConsole()) {
                $request = request();
                if ($request->attributes->get('astero.recache_dispatched') === true) {
                    return;
                }

                $request->attributes->set('astero.recache_dispatched', true);
            }

            dispatch(new RecacheApplication($reason));
        } catch (Throwable) {
            // Best-effort
        }
    }
}
