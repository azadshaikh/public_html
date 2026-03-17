<?php

namespace Modules\Platform\Http\Controllers;

use App\Models\ActivityLog;
use App\Scaffold\ScaffoldController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Platform\Definitions\ProviderDefinition;
use Modules\Platform\Models\Provider;
use Modules\Platform\Services\ProviderService;

class ProviderController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly ProviderService $providerService
    ) {}

    public static function middleware(): array
    {
        return (new ProviderDefinition)->getMiddleware();
    }

    public function sync(Request $request, int|string $id): JsonResponse|RedirectResponse
    {
        /** @var Provider $provider */
        $provider = $this->findModel((int) $id);

        $ok = $this->providerService->syncAccountInfo($provider);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => $ok ? 'success' : 'error',
                'message' => $ok ? 'Provider sync completed.' : 'Provider sync is not supported for this vendor.',
            ], $ok ? 200 : 400);
        }

        return back()->with($ok ? 'status' : 'error', $ok ? 'Provider sync completed.' : 'Provider sync is not supported for this vendor.');
    }

    public function getVendorsForType(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'vendors' => Provider::getVendorOptions($request->query('type')),
        ]);
    }

    public function getProvidersForType(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'providers' => Provider::getProviderOptions($request->query('type')),
        ]);
    }

    protected function service(): ProviderService
    {
        return $this->providerService;
    }

    protected function inertiaPage(): string
    {
        return 'platform/providers';
    }

    protected function getFormViewData(Model $model): array
    {
        /** @var Provider $provider */
        $provider = $model;
        $type = $provider->type ?: request()->query('type');
        $credentials = is_array($provider->credentials) ? $provider->credentials : [];

        return [
            'initialValues' => [
                'name' => (string) ($provider->name ?? ''),
                'email' => (string) ($provider->email ?? ''),
                'type' => (string) ($type ?? ''),
                'vendor' => (string) ($provider->vendor ?? ''),
                'status' => (string) ($provider->status ?? 'active'),
                'credentials' => [
                    'api_key' => '',
                    'api_token' => '',
                    'api_secret' => '',
                    'api_user' => (string) ($credentials['api_user'] ?? ''),
                    'username' => (string) ($credentials['username'] ?? ''),
                    'account_id' => (string) ($credentials['account_id'] ?? ''),
                    'zone_id' => (string) ($credentials['zone_id'] ?? ''),
                    'client_ip' => (string) ($credentials['client_ip'] ?? ''),
                ],
            ],
            'typeOptions' => Provider::getTypeOptions(),
            'vendorOptions' => Provider::getVendorOptions($type),
            'statusOptions' => Provider::getStatusOptions(),
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var Provider $provider */
        $provider = $model;

        return [
            'id' => $provider->getKey(),
            'name' => $provider->name,
        ];
    }

    protected function transformModelForShow(Model $model): array
    {
        /** @var Provider $provider */
        $provider = $model;
        $provider->loadMissing(['websites', 'domains', 'servers', 'agencies']);

        return [
            'id' => $provider->getKey(),
            'name' => $provider->name,
            'email' => $provider->email,
            'type' => $provider->type,
            'type_label' => $provider->type_label,
            'vendor' => $provider->vendor,
            'vendor_label' => $provider->vendor_label,
            'status' => $provider->status,
            'status_label' => $provider->status_label,
            'websites_count' => $provider->websites->count(),
            'domains_count' => $provider->domains->count(),
            'servers_count' => $provider->servers->count(),
            'agencies_count' => $provider->agencies->count(),
            'credential_keys' => collect(array_keys(is_array($provider->credentials) ? $provider->credentials : []))
                ->filter(fn (string $key): bool => filled($provider->credentials[$key] ?? null))
                ->values()
                ->all(),
            'created_at' => app_date_time_format($provider->created_at, 'datetime'),
            'updated_at' => app_date_time_format($provider->updated_at, 'datetime'),
        ];
    }

    protected function getShowViewData(Model $model): array
    {
        /** @var Provider $provider */
        $provider = $model;
        $provider->loadMissing(['websites', 'domains', 'servers', 'agencies']);

        $activities = ActivityLog::query()
            ->forModel(Provider::class, $provider->id)
            ->with('causer')
            ->latest('created_at')
            ->limit(50)
            ->get();

        return [
            'websites' => $provider->websites->map(fn ($website): array => [
                'id' => $website->getKey(),
                'name' => (string) ($website->name ?? $website->domain),
                'href' => route('platform.websites.show', $website),
                'subtitle' => $website->domain,
                'status' => (string) $website->status,
            ])->values()->all(),
            'domains' => $provider->domains->map(fn ($domain): array => [
                'id' => $domain->getKey(),
                'name' => $domain->name,
                'href' => route('platform.domains.show', $domain),
                'subtitle' => $domain->type_label,
                'status' => $domain->status,
            ])->values()->all(),
            'servers' => $provider->servers->map(fn ($server): array => [
                'id' => $server->getKey(),
                'name' => $server->name,
                'href' => route('platform.servers.show', $server),
                'subtitle' => $server->ip,
                'status' => $server->status,
            ])->values()->all(),
            'agencies' => $provider->agencies->map(fn ($agency): array => [
                'id' => $agency->getKey(),
                'name' => $agency->name,
                'href' => route('platform.agencies.show', $agency),
                'subtitle' => $agency->email,
                'status' => $agency->status,
            ])->values()->all(),
            'activities' => $activities->map(fn ($activity): array => [
                'id' => $activity->getKey(),
                'description' => (string) ($activity->description ?? $activity->message ?? 'Activity recorded'),
                'created_at' => app_date_time_format($activity->created_at, 'datetime'),
                'causer_name' => $activity->causer?->name ?? $activity->causer?->first_name ?? null,
            ])->values()->all(),
        ];
    }
}
