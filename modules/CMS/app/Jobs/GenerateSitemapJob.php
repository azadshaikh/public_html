<?php

namespace Modules\CMS\Jobs;

use App\Traits\IsMonitored;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\CMS\Services\SitemapService;

class GenerateSitemapJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use IsMonitored;
    use Queueable;
    use SerializesModels;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        /**
         * The sitemap type to generate ('posts', 'pages', etc.) or 'all'.
         */
        protected string $type = 'all'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SitemapService $sitemapService): void
    {
        $this->queueMonitorLabel('Sitemap: '.$this->type);
        // Check if sitemap is enabled
        if (! $sitemapService->isEnabled()) {
            Log::info('Sitemap generation skipped: sitemap is disabled');

            return;
        }

        // Check if auto-regenerate is enabled
        if (! setting('seo.sitemap.auto_regenerate', true)) {
            Log::info('Sitemap generation skipped: auto-regenerate is disabled');

            return;
        }

        try {
            if ($this->type === 'all') {
                $results = $sitemapService->generateAll();
                Log::info('All sitemaps regenerated', ['results' => $results]);
            } else {
                $result = $sitemapService->generate($this->type);
                Log::info('Sitemap regenerated for type: '.$this->type, ['result' => $result]);

                // Also regenerate index
                $sitemapService->generateIndex();
            }
        } catch (Exception $exception) {
            Log::error('Sitemap generation failed', [
                'type' => $this->type,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * Get the unique ID for debouncing.
     */
    public function uniqueId(): string
    {
        return 'sitemap-'.$this->type;
    }

    /**
     * Determine if the job should be unique.
     */
    public function shouldBeUnique(): bool
    {
        return true;
    }

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public function uniqueFor(): int
    {
        return 30; // 30 second debounce window
    }
}
