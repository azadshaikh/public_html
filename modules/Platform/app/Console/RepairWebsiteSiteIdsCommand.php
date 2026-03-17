<?php

declare(strict_types=1);

namespace Modules\Platform\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Modules\Platform\Models\Website;
use Throwable;

class RepairWebsiteSiteIdsCommand extends Command
{
    protected $signature = 'platform:repair-website-site-ids
                            {--dry-run : Preview changes without writing}
                            {--id=* : Limit repair to specific website record IDs}';

    protected $description = 'Repair mutated generated website site_ids (uid) and preserve server usernames in metadata.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $ids = array_values(array_filter(array_map(intval(...), (array) $this->option('id')), fn (int $id): bool => $id > 0));

        $query = Website::query()
            ->withTrashed()
            ->select(['id', 'uid', 'metadata'])
            ->orderBy('id');

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        $scanned = 0;
        $repaired = 0;
        $skipped = 0;
        $errors = 0;

        $query->chunkById(200, function ($websites) use ($dryRun, &$scanned, &$repaired, &$skipped, &$errors): void {
            /** @var Collection<int, Website> $websites */
            foreach ($websites as $website) {
                $scanned++;

                $uidSourceRaw = $website->getMetadata('uid_source');
                $uidSource = is_string($uidSourceRaw) ? $uidSourceRaw : null;

                $serverUsernameRaw = $website->getMetadata('provisioning.server_username');
                $serverUsername = is_string($serverUsernameRaw) ? $serverUsernameRaw : null;
                $uidPrefixRaw = $website->getMetadata('uid_prefix');
                $uidPrefix = is_string($uidPrefixRaw) ? $uidPrefixRaw : null;

                $uidZeroPaddingRaw = $website->getMetadata('uid_zero_padding');
                $uidZeroPadding = is_numeric($uidZeroPaddingRaw) ? (int) $uidZeroPaddingRaw : null;

                $plan = self::buildRepairPlan(
                    $website->id,
                    $website->uid,
                    $uidSource,
                    $serverUsername,
                    $uidPrefix,
                    $uidZeroPadding,
                );

                if (! $plan) {
                    $skipped++;

                    continue;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        '[DRY RUN] website_id=%d uid %s -> %s; server_username=%s',
                        $website->id,
                        $plan['previous_uid'],
                        $plan['expected_uid'],
                        $plan['server_username'],
                    ));
                    $repaired++;

                    continue;
                }

                try {
                    $website->uid = $plan['expected_uid'];
                    $website->setMetadata('provisioning.server_username', $plan['server_username']);
                    $website->setMetadata('repair.previous_uid', $plan['previous_uid']);
                    $website->setMetadata('repair.uid_fixed_at', now()->toIso8601String());
                    $website->save();

                    $repaired++;
                    $this->line(sprintf(
                        'Repaired website_id=%d uid %s -> %s',
                        $website->id,
                        $plan['previous_uid'],
                        $plan['expected_uid'],
                    ));
                } catch (Throwable $exception) {
                    $errors++;
                    $this->error(sprintf(
                        'Failed website_id=%d uid=%s: %s',
                        $website->id,
                        (string) $website->uid,
                        $exception->getMessage(),
                    ));
                }
            }
        });

        $this->newLine();
        $this->info(sprintf(
            'Repair finished. scanned=%d repaired=%d skipped=%d errors=%d dry_run=%s',
            $scanned,
            $repaired,
            $skipped,
            $errors,
            $dryRun ? 'yes' : 'no',
        ));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{previous_uid: string, expected_uid: string, server_username: string}|null
     */
    public static function buildRepairPlan(
        int $recordId,
        ?string $currentUid,
        ?string $uidSource,
        ?string $existingServerUsername,
        ?string $uidPrefix = null,
        ?int $uidZeroPadding = null,
    ): ?array {
        if (! is_string($currentUid) || trim($currentUid) === '') {
            return null;
        }

        if ($uidSource !== 'generated') {
            return null;
        }

        $expectedUid = Website::generateSiteIdFromRecordId($recordId, $uidPrefix, $uidZeroPadding);
        if ($currentUid === $expectedUid) {
            return null;
        }

        $serverUsername = is_string($existingServerUsername) && trim($existingServerUsername) !== ''
            ? $existingServerUsername
            : $currentUid;

        return [
            'previous_uid' => $currentUid,
            'expected_uid' => $expectedUid,
            'server_username' => $serverUsername,
        ];
    }
}
