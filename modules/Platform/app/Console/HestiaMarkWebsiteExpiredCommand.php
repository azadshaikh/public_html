<?php

namespace Modules\Platform\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Modules\Platform\Jobs\WebsiteExpired;
use Modules\Platform\Models\Website;

/**
 * Marks active websites as 'expired' if their expiration date has passed.
 *
 * This command is designed to be run on a schedule. It identifies websites
 * that are due for expiration, updates their status, and dispatches a
 * `WebsiteExpired` job to handle any further actions like sending notifications.
 */
class HestiaMarkWebsiteExpiredCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:mark-website-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark active websites as expired based on their expiration date.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Checking for active websites to mark as expired...');

        $websitesToExpire = Website::query()->where('status', 'active')
            ->whereNotNull('expired_on')
            ->whereNotIn('type', ['free', 'internal', 'special'])
            ->whereDate('expired_on', '<=', Date::now())
            ->get();
        /** @var Collection<int, Website> $websitesToExpire */
        if ($websitesToExpire->isEmpty()) {
            $this->info('No websites found to mark as expired.');

            return;
        }

        $this->info(sprintf('Found %d websites to mark as expired. Processing...', $websitesToExpire->count()));

        foreach ($websitesToExpire as $website) {
            $this->line(sprintf('Dispatching expiration job for website #%d (%s).', $website->id, $website->domain));
            dispatch(new WebsiteExpired($website));
        }

        $this->info('Done. Dispatched expiration jobs for all due websites.');
    }
}
