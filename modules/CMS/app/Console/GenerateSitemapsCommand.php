<?php

namespace Modules\CMS\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Modules\CMS\Services\SitemapService;

class GenerateSitemapsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:sitemap {--skip-optimize : Skip optimization commands for faster execution}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate new sitemap files.';

    /**
     * Execute the console command.
     */
    public function handle(SitemapService $sitemapService): int
    {
        $this->info('Generating sitemaps...');

        if (! $sitemapService->isEnabled()) {
            $this->info('Sitemap is disabled. Clearing sitemap directory.');

            if (File::exists(public_path('sitemaps'))) {
                File::deleteDirectory(public_path('sitemaps'));
                $this->info('Sitemap directory cleared.');
            }

            return Command::SUCCESS;
        }

        // Generate all enabled sitemaps
        $results = $sitemapService->generateAll();

        foreach ($results as $type => $result) {
            $status = $result['success'] ? '✓' : '✗';
            $message = $result['message'] ?? ucfirst((string) $type);
            $count = $result['count'] ?? 0;
            $this->info(sprintf('%s %s (%s URLs)', $status, $message, $count));
        }

        // Clear and optimize cache only if not skipping optimization
        if (! $this->option('skip-optimize')) {
            Artisan::call('optimize:clear');
            Artisan::call('optimize');
        }

        $this->info('Sitemaps Generated Successfully!');

        return Command::SUCCESS;
    }
}
