<?php

declare(strict_types=1);

namespace Modules\Agency\Console;

use Illuminate\Console\Command;
use Modules\Agency\Enums\WebsiteStatus;
use Modules\Agency\Models\AgencyWebsite;
use Modules\Agency\Services\PlatformApiClient;
use Throwable;

class RepairWebsiteCacheSiteIdsCommand extends Command
{
    protected $signature = 'agency:repair-website-cache-site-ids
                            {--dry-run : Preview changes without writing}
                            {--per-page=100 : Page size for Platform API pulls (1-100)}
                            {--include-trash : Also inspect trashed websites from Platform API}';

    protected $description = 'Repair stale agency_websites.site_id values by reconciling with Platform API records.';

    public function handle(PlatformApiClient $platformApiClient): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $perPage = max(1, min(100, (int) $this->option('per-page')));
        $includeTrash = (bool) $this->option('include-trash');

        $remoteWebsites = $this->fetchRemoteWebsites($platformApiClient, $perPage, $includeTrash);

        if ($remoteWebsites === []) {
            $this->warn('No remote websites found. Nothing to repair.');

            return self::SUCCESS;
        }

        $inspected = 0;
        $repaired = 0;
        $matched = 0;
        $missing = 0;
        $ambiguous = 0;
        $errors = 0;

        foreach ($remoteWebsites as $remote) {
            $inspected++;

            $remoteSiteId = (string) ($remote['site_id'] ?? '');
            $remoteDomain = self::normalizeDomain($remote['domain'] ?? null);

            if ($remoteSiteId === '' || $remoteDomain === null) {
                $missing++;

                continue;
            }

            $bySiteId = AgencyWebsite::query()
                ->withTrashed()
                ->where('site_id', $remoteSiteId)
                ->first();

            if ($bySiteId) {
                $matched++;

                continue;
            }

            $candidates = AgencyWebsite::query()
                ->withTrashed()
                ->whereRaw('LOWER(domain) = ?', [$remoteDomain])
                ->get();

            if ($candidates->count() === 0) {
                $missing++;

                continue;
            }

            if ($candidates->count() > 1) {
                $ambiguous++;
                $this->warn(sprintf(
                    'Ambiguous domain match for domain=%s target_site_id=%s (matches=%d)',
                    $remoteDomain,
                    $remoteSiteId,
                    $candidates->count()
                ));

                continue;
            }

            $website = $candidates->first();
            if (! $website) {
                $missing++;

                continue;
            }

            if ((string) $website->site_id === $remoteSiteId) {
                $matched++;

                continue;
            }

            if ($dryRun) {
                $repaired++;
                $this->line(sprintf(
                    '[DRY RUN] website_id=%d domain=%s site_id %s -> %s',
                    $website->id,
                    $remoteDomain,
                    (string) $website->site_id,
                    $remoteSiteId
                ));

                continue;
            }

            try {
                $website->site_id = $remoteSiteId;
                if (isset($remote['status']) && is_string($remote['status']) && $remote['status'] !== '') {
                    $websiteStatus = WebsiteStatus::tryFrom($remote['status']);
                    if ($websiteStatus !== null) {
                        $website->status = $websiteStatus;
                    }
                }

                if (isset($remote['admin_slug']) && is_string($remote['admin_slug']) && $remote['admin_slug'] !== '') {
                    $website->admin_slug = $remote['admin_slug'];
                }

                $website->save();

                $repaired++;
                $this->line(sprintf(
                    'Repaired website_id=%d domain=%s site_id -> %s',
                    $website->id,
                    $remoteDomain,
                    $remoteSiteId
                ));
            } catch (Throwable $exception) {
                $errors++;
                $this->error(sprintf(
                    'Failed website_id=%d domain=%s: %s',
                    $website->id,
                    $remoteDomain,
                    $exception->getMessage()
                ));
            }
        }

        $this->newLine();
        $this->info(sprintf(
            'Repair finished. inspected=%d repaired=%d matched=%d missing=%d ambiguous=%d errors=%d dry_run=%s',
            $inspected,
            $repaired,
            $matched,
            $missing,
            $ambiguous,
            $errors,
            $dryRun ? 'yes' : 'no',
        ));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    public static function normalizeDomain(mixed $domain): ?string
    {
        if (! is_string($domain)) {
            return null;
        }

        $normalized = strtolower(trim($domain));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchRemoteWebsites(PlatformApiClient $platformApiClient, int $perPage, bool $includeTrash): array
    {
        $statuses = [null];
        if ($includeTrash) {
            $statuses[] = 'trash';
        }

        $all = [];

        foreach ($statuses as $status) {
            $page = 1;
            $lastPage = 1;

            do {
                $params = [
                    'page' => $page,
                    'per_page' => $perPage,
                ];

                if ($status !== null) {
                    $params['status'] = $status;
                }

                $response = $platformApiClient->listWebsites($params);
                $items = $response['data'];

                foreach ($items as $item) {
                    $siteId = (string) ($item['site_id'] ?? '');
                    if ($siteId === '') {
                        continue;
                    }

                    $all[$siteId] = $item;
                }

                $meta = $response['meta'];
                $currentPage = (int) ($meta['current_page'] ?? $page);
                $lastPage = max(1, (int) ($meta['last_page'] ?? $currentPage));
                $page = $currentPage + 1;
            } while ($page <= $lastPage);
        }

        return array_values($all);
    }
}
