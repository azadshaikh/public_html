<?php

namespace Modules\CMS\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\CMS\Events\RedirectionHit;
use Modules\CMS\Models\Redirection;

class IncrementRedirectionHit implements ShouldQueue
{
    /**
     * The name of the queue the job should be sent to.
     */
    public string $queue = 'default';

    /**
     * The number of times the queued listener may be attempted.
     */
    public int $tries = 3;

    /**
     * Handle the event.
     */
    public function handle(RedirectionHit $event): void
    {
        // Refresh the model to get latest data (in case of concurrent updates)
        $redirection = Redirection::query()->find($event->redirection->id);

        if ($redirection) {
            $redirection->increment('hits');
            $redirection->update(['last_hit_at' => now()]);
        }
    }

    /**
     * Determine whether the listener should be queued.
     */
    public function shouldQueue(RedirectionHit $event): bool
    {
        // Always queue to avoid blocking the redirect response
        return true;
    }
}
