<?php

namespace Modules\Platform\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Scaffold\ScaffoldDefinition;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Modules\Platform\Definitions\ProviderDefinition;
use Modules\Platform\Http\Resources\ProviderResource;
use Modules\Platform\Libs\BunnyApi;
use Modules\Platform\Models\Provider;

class ProviderService implements ScaffoldServiceInterface
{
    use Scaffoldable {
        update as protected scaffoldUpdate;
    }

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new ProviderDefinition;
    }

    public function update(Model $model, array $data): Model
    {
        if (! $model instanceof Provider) {
            return $model;
        }

        if (! isset($data['vendor']) && $model->vendor) {
            $data['vendor'] = $model->vendor;
        }

        if (isset($data['credentials']) && is_array($data['credentials']) && $model->credentials) {
            $data['_existing_credentials'] = $model->credentials;
        }

        return $this->scaffoldUpdate($model, $data);
    }

    public function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('vendor')) {
            $query->where('vendor', $request->string('vendor')->toString());
        }
    }

    public function getTypeOptions(): array
    {
        return Provider::getTypeOptions();
    }

    public function getVendorOptions(?string $type = null): array
    {
        return Provider::getVendorOptions($type);
    }

    public function getStatusOptions(): array
    {
        return Provider::getStatusOptions();
    }

    /**
     * Sync account information from the provider's API.
     */
    public function syncAccountInfo(Provider $provider): bool
    {
        return match ($provider->vendor) {
            'bunny' => $this->syncBunnyAccount($provider),
            'cloudflare' => $this->syncCloudflareAccount($provider),
            default => false,
        };
    }

    protected function getResourceClass(): ?string
    {
        return ProviderResource::class;
    }

    protected function prepareCreateData(array $data): array
    {
        return [
            'name' => $data['name'] ?? null,
            'type' => $data['type'] ?? Provider::TYPE_DNS,
            'vendor' => $data['vendor'] ?? 'manual',
            'email' => $data['email'] ?? null,
            'credentials' => $this->prepareCredentials($data['vendor'] ?? 'manual', $data['credentials'] ?? [], []),
            'status' => $data['status'] ?? 'active',
        ];
    }

    protected function prepareUpdateData(array $data): array
    {
        $preparedData = [];

        if (array_key_exists('name', $data)) {
            $preparedData['name'] = $data['name'];
        }

        if (array_key_exists('type', $data)) {
            $preparedData['type'] = $data['type'];
        }

        if (array_key_exists('vendor', $data)) {
            $preparedData['vendor'] = $data['vendor'];
        }

        if (array_key_exists('email', $data)) {
            $preparedData['email'] = $data['email'];
        }

        if (array_key_exists('status', $data)) {
            $preparedData['status'] = $data['status'];
        }

        if (isset($data['credentials']) && is_array($data['credentials'])) {
            $vendor = $data['vendor'] ?? 'manual';
            $existingCredentials = $data['_existing_credentials'] ?? [];
            $credentials = $this->prepareCredentials($vendor, $data['credentials'], $existingCredentials);

            if ($credentials !== null && $credentials !== []) {
                $preparedData['credentials'] = $credentials;
            }
        }

        return $preparedData;
    }

    /**
     * Prepare credentials based on vendor type.
     * Merges with existing credentials to preserve password fields that weren't changed.
     */
    protected function prepareCredentials(string $vendor, array $credentials, array $existing = []): ?array
    {
        if ($credentials === [] && $existing === []) {
            return null;
        }

        $prepared = match ($vendor) {
            'bunny' => [
                'api_key' => empty($credentials['api_key']) ? $existing['api_key'] ?? null : $credentials['api_key'],
                'account_id' => $credentials['account_id'] ?? $existing['account_id'] ?? null,
            ],
            'cloudflare' => [
                'api_token' => empty($credentials['api_token']) ? $existing['api_token'] ?? null : $credentials['api_token'],
                'zone_id' => $credentials['zone_id'] ?? $existing['zone_id'] ?? null,
            ],
            'hetzner', 'digitalocean', 'linode', 'vultr' => [
                'api_token' => empty($credentials['api_token']) ? $existing['api_token'] ?? null : $credentials['api_token'],
            ],
            'namecheap' => [
                'api_user' => $credentials['api_user'] ?? $existing['api_user'] ?? null,
                'api_key' => empty($credentials['api_key']) ? $existing['api_key'] ?? null : $credentials['api_key'],
                'username' => $credentials['username'] ?? $existing['username'] ?? null,
                'client_ip' => $credentials['client_ip'] ?? $existing['client_ip'] ?? null,
            ],
            'godaddy' => [
                'api_key' => empty($credentials['api_key']) ? $existing['api_key'] ?? null : $credentials['api_key'],
                'api_secret' => empty($credentials['api_secret']) ? $existing['api_secret'] ?? null : $credentials['api_secret'],
            ],
            default => array_merge($existing, array_filter($credentials)),
        };

        return array_filter($prepared, fn ($value): bool => $value !== null);
    }

    protected function syncBunnyAccount(Provider $provider): bool
    {
        $result = BunnyApi::syncAccountInfo($provider);

        return ($result['status'] ?? '') === 'success';
    }

    protected function syncCloudflareAccount(Provider $provider): bool
    {
        return false;
    }
}
