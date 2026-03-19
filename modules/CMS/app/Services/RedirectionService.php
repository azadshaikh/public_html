<?php

declare(strict_types=1);

namespace Modules\CMS\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Modules\CMS\Definitions\RedirectionDefinition;
use Modules\CMS\Http\Resources\RedirectionResource;
use Modules\CMS\Models\Redirection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RedirectionService implements ScaffoldServiceInterface
{
    use Scaffoldable {
        getFiltersConfig as protected scaffoldGetFiltersConfig;
    }

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new RedirectionDefinition;
    }

    public function getStatusOptions(): array
    {
        return [
            ['value' => 'active', 'label' => 'Active'],
            ['value' => 'inactive', 'label' => 'Inactive'],
        ];
    }

    protected function getFiltersConfig(): array
    {
        $filters = $this->scaffoldGetFiltersConfig();

        foreach ($filters as $index => $filter) {
            if (($filter['key'] ?? null) === 'redirect_type') {
                $filters[$index]['options'] = $this->normalizeFilterOptionMap($this->getRedirectTypeOptions());
            }

            if (($filter['key'] ?? null) === 'url_type') {
                $filters[$index]['options'] = $this->normalizeFilterOptionMap($this->getUrlTypeOptions());
            }

            if (($filter['key'] ?? null) === 'match_type') {
                $filters[$index]['options'] = $this->normalizeFilterOptionMap($this->getMatchTypeOptions());
            }
        }

        return $filters;
    }

    public function getRedirectTypeOptions(): array
    {
        return collect(config('seo.redirect_types', []))
            ->map(fn (array $item, int|string $code): array => [
                'label' => $item['label'] ?? (string) $code,
                'value' => (string) $code,
                'description' => $item['description'] ?? null,
            ])
            ->values()
            ->all();
    }

    public function getUrlTypeOptions(): array
    {
        return collect(config('seo.url_types', []))
            ->map(fn (array $item, string $value): array => [
                'label' => $item['label'] ?? ucfirst($value),
                'value' => $value,
                'description' => $item['description'] ?? null,
            ])
            ->values()
            ->all();
    }

    public function getMatchTypeOptions(): array
    {
        return collect(config('seo.match_types', []))
            ->map(fn (array $item, string $value): array => [
                'label' => $item['label'] ?? ucfirst($value),
                'value' => $value,
                'description' => $item['description'] ?? null,
            ])
            ->values()
            ->all();
    }

    public function flushCache(): void
    {
        resolve(RedirectionCacheService::class)->invalidate();
    }

    /**
     * Export redirections to CSV.
     */
    public function exportToCsv(string $status = 'all'): StreamedResponse
    {
        $query = $this->buildListQueryForExport($status);
        /** @var Collection<int, Redirection> $redirections */
        $redirections = $query->get();

        $filename = 'redirections-export-'.date('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($redirections): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'source_url',
                'target_url',
                'redirect_type',
                'url_type',
                'match_type',
                'status',
                'hits',
                'notes',
                'expires_at',
            ]);

            foreach ($redirections as $redirect) {
                fputcsv($handle, [
                    $redirect->source_url,
                    $redirect->target_url,
                    $redirect->redirect_type,
                    $redirect->url_type,
                    $redirect->match_type,
                    $redirect->status,
                    $redirect->hits,
                    $redirect->notes,
                    $redirect->expires_at?->toIso8601String(),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Import redirections from CSV file.
     */
    public function importFromCsv(
        UploadedFile $file,
        bool $skipDuplicates = true,
        bool $updateExisting = false
    ): array {
        $result = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'error_details' => [],
        ];

        $handle = fopen($file->getRealPath(), 'r');
        if (! $handle) {
            $result['errors'] = 1;
            $result['error_details'][] = 'Could not open file for reading.';

            return $result;
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);
            $result['errors'] = 1;
            $result['error_details'][] = 'Could not read CSV header.';

            return $result;
        }

        $header = array_map(strtolower(...), array_map(trim(...), $header));
        $requiredColumns = ['source_url', 'target_url'];
        foreach ($requiredColumns as $col) {
            if (! in_array($col, $header, true)) {
                fclose($handle);
                $result['errors'] = 1;
                $result['error_details'][] = 'Missing required column: '.$col;

                return $result;
            }
        }

        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            try {
                $data = array_combine($header, $row);

                if (empty($data['source_url']) || empty($data['target_url'])) {
                    $result['skipped']++;
                    $result['error_details'][] = sprintf('Row %d: Missing source_url or target_url.', $rowNumber);

                    continue;
                }

                $existing = Redirection::query()->where('source_url', $data['source_url'])
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing) {
                    if ($updateExisting) {
                        $this->update($existing, $this->mapCsvRowToData($data));
                        $result['updated']++;
                    } elseif ($skipDuplicates) {
                        $result['skipped']++;
                    } else {
                        $result['errors']++;
                        $result['error_details'][] = sprintf("Row %d: Duplicate source_url '%s'.", $rowNumber, $data['source_url']);
                    }

                    continue;
                }

                $this->create($this->mapCsvRowToData($data));
                $result['created']++;
            } catch (Exception $e) {
                $result['errors']++;
                $result['error_details'][] = sprintf('Row %d: ', $rowNumber).$e->getMessage();
            }
        }

        fclose($handle);
        $this->flushCache();

        return $result;
    }

    /**
     * Test a redirect rule against a path.
     */
    public function testRedirect(string $sourceUrl, string $matchType, string $testPath): array
    {
        $tempRedirect = new Redirection([
            'source_url' => $sourceUrl,
            'match_type' => $matchType,
        ]);

        $matches = $tempRedirect->matchesPath($testPath);

        return [
            'matches' => $matches !== false,
            'captured_groups' => is_array($matches) ? $matches : [],
            'source_pattern' => $sourceUrl,
            'test_path' => $testPath,
            'match_type' => $matchType,
        ];
    }

    protected function getResourceClass(): ?string
    {
        return RedirectionResource::class;
    }

    protected function prepareCreateData(array $data): array
    {
        $payload = [
            'source_url' => trim((string) $data['source_url']),
            'target_url' => trim((string) $data['target_url']),
            'redirect_type' => (int) $data['redirect_type'],
            'url_type' => $data['url_type'] ?? 'internal',
            'match_type' => $data['match_type'] ?? 'exact',
            'status' => $data['status'] ?? 'active',
            'hits' => $data['hits'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ];

        if (array_key_exists('metadata', $data)) {
            $payload['metadata'] = $this->normalizeMetadata($data['metadata']);
        }

        return $payload;
    }

    protected function prepareUpdateData(array $data): array
    {
        $payload = [
            'source_url' => trim((string) $data['source_url']),
            'target_url' => trim((string) $data['target_url']),
            'redirect_type' => (int) $data['redirect_type'],
            'url_type' => $data['url_type'] ?? 'internal',
            'match_type' => $data['match_type'] ?? 'exact',
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
        ];

        if (array_key_exists('metadata', $data)) {
            $payload['metadata'] = $this->normalizeMetadata($data['metadata']);
        }

        return $payload;
    }

    protected function afterCreate(Model $model, array $data): void
    {
        $this->flushCache();
    }

    protected function afterUpdate(Model $model, array $data): void
    {
        $this->flushCache();
    }

    protected function afterDelete(Model $model): void
    {
        $this->flushCache();
    }

    protected function afterRestore(Model $model): void
    {
        $this->flushCache();
    }

    protected function afterForceDelete(Model $model): void
    {
        $this->flushCache();
    }

    protected function mapCsvRowToData(array $row): array
    {
        return [
            'source_url' => trim((string) $row['source_url']),
            'target_url' => trim((string) $row['target_url']),
            'redirect_type' => (int) ($row['redirect_type'] ?? 301),
            'url_type' => $row['url_type'] ?? 'internal',
            'match_type' => $row['match_type'] ?? 'exact',
            'status' => $row['status'] ?? 'active',
            'notes' => $row['notes'] ?? null,
            'expires_at' => empty($row['expires_at']) ? null : $row['expires_at'],
        ];
    }

    protected function buildListQueryForExport(string $status): Builder
    {
        $query = Redirection::query();

        return match ($status) {
            'active' => $query->where('status', 'active'),
            'inactive' => $query->where('status', 'inactive'),
            'trash' => $query->onlyTrashed(),
            default => $query,
        };
    }

    private function normalizeMetadata(mixed $metadata): array
    {
        if (is_string($metadata)) {
            $decoded = json_decode($metadata, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            return ['value' => $metadata];
        }

        if (is_array($metadata)) {
            return $metadata;
        }

        return [];
    }
}
