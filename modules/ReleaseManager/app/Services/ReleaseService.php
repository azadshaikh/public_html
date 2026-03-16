<?php

declare(strict_types=1);

namespace Modules\ReleaseManager\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\ReleaseManager\Definitions\ReleaseDefinition;
use Modules\ReleaseManager\Http\Resources\ReleaseResource;
use Modules\ReleaseManager\Models\Release;
use Throwable;

class ReleaseService implements ScaffoldServiceInterface
{
    use Scaffoldable {
        update as protected scaffoldUpdate;
    }

    private ?string $releaseType = null;

    private ?int $currentModelId = null;

    public function setReleaseType(string $type): void
    {
        $this->releaseType = $this->normalizeReleaseType($type);
        $this->scaffoldDefinitionCache = null;
    }

    public function getReleaseType(): string
    {
        return $this->resolveReleaseType();
    }

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new ReleaseDefinition($this->resolveReleaseType());
    }

    public function getStatistics(): array
    {
        $type = $this->resolveReleaseType();

        $statusCounts = Release::query()
            ->selectRaw('status, COUNT(*) as count')
            ->where('release_type', $type)
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total' => Release::query()->where('release_type', $type)->count(),
            ...$statusCounts,
            'trash' => Release::onlyTrashed()->where('release_type', $type)->count(),
        ];
    }

    // =========================================================================
    // CRUD OVERRIDES (context + meta.json)
    // =========================================================================

    public function update(Model $model, array $data): Model
    {
        $this->currentModelId = (int) $model->getKey();

        try {
            return $this->scaffoldUpdate($model, $data);
        } finally {
            $this->currentModelId = null;
        }
    }

    // =========================================================================
    // FORM OPTIONS
    // =========================================================================

    public function getStatusOptions(): array
    {
        return config('releasemanager.status_options', [
            ['label' => 'Draft', 'value' => 'draft', 'class' => 'bg-secondary-subtle text-secondary'],
            ['label' => 'Published', 'value' => 'published', 'class' => 'bg-success-subtle text-success'],
            ['label' => 'Deprecate', 'value' => 'deprecate', 'class' => 'bg-warning-subtle text-warning'],
        ]);
    }

    public function getReleaseTypeOptions(): array
    {
        return config('releasemanager.release_types', [
            ['label' => 'Application', 'value' => 'application', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>'],
            ['label' => 'Module', 'value' => 'module', 'icon' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/></svg>'],
        ]);
    }

    public function getVersionTypeOptions(): array
    {
        return config('releasemanager.version_types', [
            ['label' => 'Major', 'value' => 'major'],
            ['label' => 'Minor', 'value' => 'minor'],
            ['label' => 'Patch', 'value' => 'patch'],
        ]);
    }

    protected function alwaysIncludeStatistics(): bool
    {
        return true;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function generateNextVersion(string $type, string $versionType = 'patch', ?string $packageIdentifier = null): string
    {
        $type = $this->normalizeReleaseType($type);

        $latestQuery = Release::query()
            ->where('release_type', $type)
            ->when(
                $type === 'module' && $packageIdentifier !== null && $packageIdentifier !== '',
                fn (Builder $query): Builder => $query->where('package_identifier', $packageIdentifier),
            );

        $driver = $latestQuery->getModel()->getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            $latestQuery
                ->orderByRaw("COALESCE(NULLIF(split_part(version, '.', 1), ''), '0')::int DESC")
                ->orderByRaw("COALESCE(NULLIF(split_part(version, '.', 2), ''), '0')::int DESC")
                ->orderByRaw("COALESCE(NULLIF(split_part(version, '.', 3), ''), '0')::int DESC");
        } else {
            $latestQuery->orderByDesc('version');
        }

        $latest = $latestQuery->first();

        /** @var Release|null $latest */
        $current = $latest ? $latest->version : '0.0.0';
        $parts = explode('.', (string) $current);
        $major = (int) $parts[0];
        $minor = isset($parts[1]) ? (int) $parts[1] : 0;
        $patch = isset($parts[2]) ? (int) $parts[2] : 0;

        return match ($versionType) {
            'major' => ($major + 1).'.0.0',
            'minor' => $major.'.'.($minor + 1).'.0',
            default => $major.'.'.$minor.'.'.($patch + 1),
        };
    }

    protected function getResourceClass(): ?string
    {
        return ReleaseResource::class;
    }

    /**
     * Releases routes are nested under /{type}/..., so the default Scaffoldable
     * row action URL builder (route(name, id)) would generate invalid URLs.
     *
     * Note: This is a safety-net for cases where the Resource isn't used.
     */
    protected function getRowActions(Model $item): array
    {
        $isTrashed = method_exists($item, 'trashed') && $item->trashed();
        $status = $isTrashed ? 'trash' : 'all';
        $type = $this->resolveReleaseType();

        $definedActions = collect($this->scaffold()->actions())
            ->filter(fn ($action): bool => $action->authorized())
            ->filter(fn ($action): bool => $action->isForRow())
            ->filter(fn ($action): bool => $action->shouldShow($status));

        $actions = [];

        foreach ($definedActions as $action) {
            $actionData = $action->toArray();
            $key = $actionData['key'];

            if (empty($actionData['route'])) {
                continue;
            }

            try {
                $suffix = str($actionData['route'])->afterLast('.')->value();
                $routeName = $type === 'module' ? 'releasemanager.module.'.$suffix : 'releasemanager.application.'.$suffix;
                $url = route($routeName, ['release' => $item->getKey()]);
            } catch (Throwable) {
                continue;
            }

            $actions[$key] = [
                'url' => $url,
                'label' => $actionData['label'] ?? $this->getEntityName(),
                'icon' => $actionData['icon'] ?? null,
                'method' => $actionData['method'] ?? 'GET',
                'confirm' => $actionData['confirm'] ?? null,
                'variant' => $actionData['variant'] ?? 'default',
            ];
        }

        return $actions;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'createdBy:id,first_name,last_name,name',
            'updatedBy:id,first_name,last_name,name',
            'deletedBy:id,first_name,last_name,name',
        ];
    }

    protected function customizeListQuery(Builder $query, Request $request): void
    {
        $query->where('release_type', $this->resolveReleaseType());
    }

    /**
     * Release routes are type-scoped (/{type}/...), so empty-state create URL
     * must include the required {type} parameter.
     */
    protected function getEmptyStateConfig(): array
    {
        $type = $this->resolveReleaseType();

        return [
            'icon' => 'Rocket',
            'title' => sprintf('No %s Found', $this->getEntityPlural()),
            'message' => sprintf('There are no %s to display.', $this->getEntityPlural()),
            'action' => [
                'label' => 'Create '.$this->getEntityName(),
                'url' => route($type === 'module' ? 'releasemanager.module.create' : 'releasemanager.application.create'),
            ],
        ];
    }

    protected function prepareCreateData(array $data): array
    {
        return $this->prepareReleaseData($data);
    }

    protected function prepareUpdateData(array $data): array
    {
        return $this->prepareReleaseData($data, excludeId: $this->currentModelId);
    }

    private function prepareReleaseData(array $data, ?int $excludeId = null): array
    {
        $type = $this->resolveReleaseType();
        $versionType = (string) ($data['version_type'] ?? 'patch');

        // Auto-set package_identifier to "main" for application releases
        $packageIdentifier = $type === 'application'
            ? 'main'
            : ($data['package_identifier'] ?? null);

        $releaseLink = $data['release_link'] ?? null;

        // Auto-fetch metadata from sidecar .meta.json when file fields are missing
        $fileName = $data['file_name'] ?? null;
        $checksum = $data['checksum'] ?? null;
        $fileSize = $data['file_size'] ?? null;
        $version = $data['version'] ?? null;

        if ($releaseLink && (empty($fileName) || empty($checksum) || empty($fileSize) || empty($version))) {
            $metadata = $this->fetchReleaseMetadata((string) $releaseLink);
            if ($metadata) {
                $fileName = $fileName ?: $metadata['file_name'] ?? null;
                $checksum = $checksum ?: $metadata['checksum'] ?? null;
                $fileSize = $fileSize ?: $metadata['file_size'] ?? null;
                $version = $version ?: $metadata['version'] ?? null;
            }
        }

        $version = $version ?: $this->generateNextVersion($type, $versionType, $packageIdentifier !== null ? (string) $packageIdentifier : null);

        $this->validateVersionUniqueness((string) $packageIdentifier, (string) $version, $excludeId);

        return [
            'package_identifier' => $packageIdentifier,
            'version' => $version,
            'version_type' => $versionType,
            'release_type' => $type,
            'status' => $data['status'] ?? 'draft',
            'release_at' => $data['release_at'] ?? now(),
            'change_log' => $data['change_log'] ?? null,
            'release_link' => $releaseLink,
            'file_name' => $fileName,
            'checksum' => $checksum,
            'file_size' => $fileSize !== null ? (int) $fileSize : null,
        ];
    }

    private function fetchReleaseMetadata(string $releaseLink): ?array
    {
        try {
            $metaUrl = $releaseLink.'.meta.json';

            $response = @file_get_contents($metaUrl, false, stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'ignore_errors' => true,
                ],
            ]));

            if ($response === false) {
                return null;
            }

            $metadata = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($metadata)) {
                return null;
            }

            return $metadata;
        } catch (Throwable) {
            return null;
        }
    }

    private function validateVersionUniqueness(string $packageIdentifier, string $version, ?int $excludeId = null): void
    {
        if ($packageIdentifier === '' || $version === '') {
            return;
        }

        $query = Release::query()
            ->where('release_type', $this->resolveReleaseType())
            ->where('package_identifier', $packageIdentifier)
            ->where('version', $version);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'version' => sprintf("This version already exists for the selected package. Version '%s' is already registered for package '%s'.", $version, $packageIdentifier),
            ]);
        }
    }

    private function resolveReleaseType(): string
    {
        return $this->releaseType ?? $this->normalizeReleaseType((string) (request()->route('type') ?? request()->input('type', 'application')));
    }

    private function normalizeReleaseType(string $type): string
    {
        $allowed = collect(config('releasemanager.release_types', []))
            ->pluck('value')
            ->filter()
            ->values()
            ->all();

        $allowed = $allowed ?: ['application', 'module'];

        return in_array($type, $allowed, true) ? $type : 'application';
    }
}
