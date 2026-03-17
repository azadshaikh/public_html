<?php

namespace Modules\Platform\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Platform\Definitions\SecretDefinition;
use Modules\Platform\Http\Resources\SecretResource;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Domain;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Secret;
use Modules\Platform\Models\Server;
use Modules\Platform\Models\Website;

class SecretService implements ScaffoldServiceInterface
{
    use Scaffoldable;

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new SecretDefinition;
    }

    public function getDataGridConfig(): array
    {
        $config = $this->scaffold()->toDataGridConfig();

        foreach (($config['filters'] ?? []) as $i => $filter) {
            if (($filter['key'] ?? null) === 'type') {
                $config['filters'][$i]['options'] = collect(config('platform.secret_types', []))
                    ->mapWithKeys(fn ($item, $key): array => [$key => $item['label'] ?? $key])
                    ->toArray();
            }

            if (($filter['key'] ?? null) === 'secretable_type') {
                $config['filters'][$i]['options'] = collect([
                    Domain::class => 'Domain',
                    Website::class => 'Website',
                    Agency::class => 'Agency',
                    Server::class => 'Server',
                    Provider::class => 'Provider',
                ])->all();
            }
        }

        return $config;
    }

    public function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('secretable_type')) {
            $query->where('secretable_type', $request->string('secretable_type')->toString());
        }

        if ($request->filled('secretable_id')) {
            $query->where('secretable_id', $request->integer('secretable_id'));
        }

        if ($request->filled('key')) {
            $search = $this->escapeLike($request->string('key')->toString());
            $query->where('key', 'ilike', sprintf('%%%s%%', $search));
        }
    }

    public function getStatistics(): array
    {
        return [
            'total' => Secret::query()->count(),
            'active' => Secret::query()->where('is_active', true)->count(),
            'inactive' => Secret::query()->where('is_active', false)->count(),
            'trash' => Secret::onlyTrashed()->count(),
        ];
    }

    public function getTypeOptions(): array
    {
        return collect(config('platform.secret_types', []))
            ->map(fn ($item, $key): array => [
                'value' => $key,
                'label' => $item['label'] ?? $key,
            ])
            ->values()
            ->all();
    }

    public function getSecretableTypeOptions(): array
    {
        return [
            ['value' => Domain::class, 'label' => 'Domain'],
            ['value' => Website::class, 'label' => 'Website'],
            ['value' => Agency::class, 'label' => 'Agency'],
            ['value' => Server::class, 'label' => 'Server'],
            ['value' => Provider::class, 'label' => 'Provider'],
        ];
    }

    protected function getResourceClass(): ?string
    {
        return SecretResource::class;
    }

    protected function prepareCreateData(array $data): array
    {
        return [
            'secretable_type' => $data['secretable_type'] ?? null,
            'secretable_id' => $data['secretable_id'] ?? null,
            'key' => $data['key'] ?? null,
            'username' => $data['username'] ?? null,
            'type' => $data['type'] ?? 'password',
            'value' => isset($data['value']) && $data['value'] !== '' ? encrypt($data['value']) : null,
            'metadata' => $data['metadata'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'expires_at' => $data['expires_at'] ?? null,
        ];
    }

    protected function prepareUpdateData(array $data): array
    {
        $prepared = [
            'key' => $data['key'] ?? null,
            'username' => array_key_exists('username', $data) ? ($data['username'] ?? null) : null,
            'type' => $data['type'] ?? 'password',
            'metadata' => $data['metadata'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'expires_at' => $data['expires_at'] ?? null,
        ];

        // Only update value if explicitly provided
        if (array_key_exists('value', $data) && $data['value'] !== null && $data['value'] !== '') {
            $prepared['value'] = encrypt($data['value']);
        } else {
            // @phpstan-ignore-next-line unset.offset
            unset($prepared['value']);
        }

        // Username is optional; if omitted, keep current
        if (! array_key_exists('username', $data)) {
            unset($prepared['username']);
        }

        return $prepared;
    }

    protected function customizeListQuery(Builder $query, Request $request): void
    {
        $status = $request->input('status');

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $value);
    }
}
