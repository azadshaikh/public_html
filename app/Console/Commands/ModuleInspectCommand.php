<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\ModuleInspector;
use Illuminate\Console\Command;
use InvalidArgumentException;
use JsonException;

class ModuleInspectCommand extends Command
{
    protected $signature = 'module:inspect
        {module? : Optional module name or slug to inspect}
        {--json : Output diagnostics as JSON}
        {--fail-on-issues : Return a failure code when any issue is detected}';

    protected $description = 'Inspect module manifests and structural runtime conventions';

    public function __construct(private readonly ModuleInspector $inspector)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $target = $this->argument('module');

        try {
            $payload = is_string($target) && trim($target) !== ''
                ? $this->inspector->inspect($target)
                : $this->inspector->inspectAll();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            try {
                $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            } catch (JsonException $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            return $this->statusCodeForPayload($payload);
        }

        if (is_array($payload) && array_is_list($payload)) {
            $this->renderSummaryTable($payload);
        } else {
            $this->renderDetailedInspection($payload);
        }

        return $this->statusCodeForPayload($payload);
    }

    /**
     * @param  array<int, array<string, mixed>>  $payload
     */
    private function renderSummaryTable(array $payload): void
    {
        if ($payload === []) {
            $this->warn('No modules were discovered.');

            return;
        }

        $this->table(
            ['Module', 'Slug', 'Status', 'Provider', 'Routes', 'Issues'],
            collect($payload)
                ->map(fn (array $inspection): array => [
                    (string) $inspection['name'],
                    (string) $inspection['slug'],
                    ($inspection['enabled'] ?? false) ? 'enabled' : 'disabled',
                    class_basename((string) $inspection['provider']),
                    implode(', ', array_keys((array) ($inspection['routeFiles'] ?? []))),
                    (string) ($inspection['issuesCount'] ?? 0),
                ])
                ->all(),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderDetailedInspection(array $payload): void
    {
        $this->table(['Key', 'Value'], [
            ['name', (string) $payload['name']],
            ['slug', (string) $payload['slug']],
            ['status', ($payload['enabled'] ?? false) ? 'enabled' : 'disabled'],
            ['provider', (string) $payload['provider']],
            ['provider_path', (string) $payload['providerPath']],
            ['page_root', (string) $payload['pageRootPath']],
            ['navigation_path', (string) ($payload['navigationPath'] ?? '')],
            ['abilities_path', (string) ($payload['abilitiesPath'] ?? '')],
            ['database_seeder', (string) $payload['databaseSeederClass']],
            ['database_seeder_path', (string) $payload['databaseSeederPath']],
            ['issues', (string) ($payload['issuesCount'] ?? 0)],
        ]);

        $routeFiles = (array) ($payload['routeFiles'] ?? []);

        $this->newLine();
        $this->info('Route files');
        $this->table(
            ['Key', 'Path'],
            collect($routeFiles)
                ->map(fn (string $path, string $key): array => [$key, $path])
                ->values()
                ->all(),
        );

        $checks = (array) ($payload['checks'] ?? []);
        $routeChecks = (array) ($checks['route_files'] ?? []);
        unset($checks['route_files']);

        $this->newLine();
        $this->info('Checks');
        $this->table(
            ['Check', 'Summary'],
            [
                ...collect($checks)
                    ->map(fn (array $check, string $key): array => [$key, $this->summarizeCheck($check)])
                    ->values()
                    ->all(),
                ...collect($routeChecks)
                    ->map(fn (array $check): array => [$check['label'] ?? 'route', $this->summarizeCheck($check)])
                    ->values()
                    ->all(),
            ],
        );

        $issues = (array) ($payload['issues'] ?? []);

        if ($issues === []) {
            $this->newLine();
            $this->info('No issues detected.');

            return;
        }

        $this->newLine();
        $this->warn('Detected issues');

        foreach ($issues as $issue) {
            $this->line(' - '.$issue);
        }
    }

    /**
     * @param  array<string, mixed>|array<int, array<string, mixed>>  $payload
     */
    private function statusCodeForPayload(array $payload): int
    {
        if (! $this->option('fail-on-issues')) {
            return self::SUCCESS;
        }

        $issuesCount = array_is_list($payload)
            ? collect($payload)->sum(fn (array $inspection): int => (int) ($inspection['issuesCount'] ?? 0))
            : (int) ($payload['issuesCount'] ?? 0);

        return $issuesCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $check
     */
    private function summarizeCheck(array $check): string
    {
        return collect($check)
            ->map(function (mixed $value, string $key): string {
                if (is_bool($value)) {
                    return sprintf('%s=%s', $key, $value ? 'yes' : 'no');
                }

                return sprintf('%s=%s', $key, is_scalar($value) || $value === null ? (string) $value : json_encode($value));
            })
            ->implode(', ');
    }
}
