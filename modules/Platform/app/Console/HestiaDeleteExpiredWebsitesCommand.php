<?php

namespace Modules\Platform\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Modules\Platform\Jobs\WebsiteDelete;
use Modules\Platform\Models\Website;

/**
 * Finds and deletes websites that have been expired for a specified grace period.
 *
 * This command is intended to be run on a schedule (e.g., daily). It queries for all
 * websites marked as 'expired' and checks if their expiration date is past the
 * 15-day grace period. For each qualifying website, it dispatches a `WebsiteDelete`
 * job to handle the actual deletion process asynchronously.
 */
class HestiaDeleteExpiredWebsitesCommand extends Command
{
    /**
     * The grace period in days before an expired website is deleted.
     */
    private const int EXPIRATION_GRACE_PERIOD_DAYS = 15;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:hestia:delete-expired-websites';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete websites that have been expired for more than 15 days.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Checking for expired websites to delete...');

        $expiredWebsites = Website::query()->where('status', 'expired')->get();
        /** @var Collection<int, Website> $expiredWebsites */
        if ($expiredWebsites->isEmpty()) {
            $this->info('No expired websites found.');

            return;
        }

        $this->info(sprintf('Found %d expired websites. Processing...', $expiredWebsites->count()));

        $deletedCount = 0;
        foreach ($expiredWebsites as $website) {
            if (Date::parse($website->expired_on)->addDays(self::EXPIRATION_GRACE_PERIOD_DAYS)->isPast()) {
                dispatch(new WebsiteDelete($website->id));
                $this->line(sprintf('Dispatched deletion job for website #%d (%s).', $website->id, $website->domain));
                $deletedCount++;
            }
        }

        $this->info(sprintf('Done. Dispatched deletion jobs for %d websites.', $deletedCount));
    }
}
