<?php

declare(strict_types=1);

namespace Modules\ReleaseManager\Http\Resources;

use App\Scaffold\Action;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldResource;
use App\Traits\DateTimeFormattingTrait;
use Modules\ReleaseManager\Definitions\ReleaseDefinition;
use Modules\ReleaseManager\Models\Release;
use Throwable;

/** @mixin Release */
class ReleaseResource extends ScaffoldResource
{
    use DateTimeFormattingTrait;

    private function currentType(): string
    {
        return (string) ($this->release_type ?? request()->route('type') ?? request()->input('type', 'application'));
    }

    private function routeNamespace(string $type): string
    {
        return $type === 'module'
            ? 'releasemanager.module'
            : 'releasemanager.application';
    }

    protected function definition(): ScaffoldDefinition
    {
        $type = $this->currentType();

        return new ReleaseDefinition($type);
    }

    protected function customFields(): array
    {
        $type = $this->currentType();
        $status = (string) ($this->status ?? '');
        $versionType = (string) ($this->version_type ?? '');

        $statusConfig = collect(config('releasemanager.status_options', []))
            ->firstWhere('value', $status);

        $statusLabel = $statusConfig['label'] ?? ($status !== '' && $status !== '0' ? ucfirst(str_replace('_', ' ', $status)) : '—');
        $statusClass = $statusConfig['class'] ?? 'bg-secondary-subtle text-secondary';

        if (! empty($this->deleted_at)) {
            $statusLabel = 'Trashed';
            $statusClass = 'bg-danger-subtle text-danger';
        }

        $versionTypeConfig = collect(config('releasemanager.version_types', []))
            ->firstWhere('value', $versionType);

        $versionTypeLabel = $versionTypeConfig['label'] ?? ($versionType !== '' && $versionType !== '0' ? ucfirst(str_replace('_', ' ', $versionType)) : '—');
        $versionTypeBadge = match ($versionType) {
            'major' => 'destructive',
            'minor' => 'info',
            'patch' => 'secondary',
            default => 'secondary',
        };

        $data = [
            'show_url' => route($this->routeNamespace($type).'.show', ['release' => $this->id]),

            'status_label' => $statusLabel,
            'status_class' => $statusClass,

            'version_type_label' => $versionTypeLabel,
            'version_type_badge' => $versionTypeBadge,

            // Override ISO timestamps from ScaffoldResource with formatted date-only strings
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'release_at' => $this->release_at,
        ];

        return $this->formatDateTimeFields($data, dateFields: ['created_at', 'updated_at', 'release_at']);
    }

    /**
     * Release routes are nested under /{type}/..., so we must build action URLs with both params.
     */
    protected function getActions(): array
    {
        $isTrashed = $this->resource->deleted_at !== null;
        $status = $isTrashed ? 'trash' : 'all';
        $id = $this->resource->getKey();
        $type = (string) ($this->resource->release_type ?? request()->input('type', 'application'));

        $definedActions = collect($this->scaffold()->actions())
            ->filter(fn (Action $action): bool => $action->authorized())
            ->filter(fn (Action $action): bool => $action->isForRow())
            ->filter(fn (Action $action): bool => $action->shouldShow($status));

        $actions = [];

        foreach ($definedActions as $action) {
            $actionData = $action->toArray();
            $key = $actionData['key'];

            if (empty($actionData['route'])) {
                continue;
            }

            try {
                $suffix = str($actionData['route'])->afterLast('.')->value();
                $url = route($this->routeNamespace($type).'.'.$suffix, ['release' => $id]);
            } catch (Throwable) {
                continue;
            }

            $actions[$key] = [
                'url' => $url,
                'label' => $actionData['label'],
                'icon' => $actionData['icon'] ?? null,
                'method' => $actionData['method'] ?? 'GET',
                'confirm' => $actionData['confirm'] ?? null,
                'variant' => $actionData['variant'] ?? 'default',
                'fullReload' => $actionData['fullReload'] ?? false,
                'attributes' => $actionData['attributes'] ?? null,
            ];

            if (($actionData['variant'] ?? null) === 'danger') {
                $actions[$key]['danger'] = true;
            }
        }

        return $actions;
    }
}
