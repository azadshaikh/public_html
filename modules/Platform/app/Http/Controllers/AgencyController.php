<?php

namespace Modules\Platform\Http\Controllers;

use App\Models\ActivityLog;
use App\Scaffold\ScaffoldController;
use App\Services\GeoDataService;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Modules\Platform\Definitions\AgencyDefinition;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Website;
use Modules\Platform\Services\AgencyService;

class AgencyController extends ScaffoldController implements HasMiddleware
{
    public function __construct(
        private readonly AgencyService $agencyService,
        private readonly GeoDataService $geoDataService
    ) {}

    public static function middleware(): array
    {
        return (new AgencyDefinition)->getMiddleware();
    }

    /**
     * Regenerate the secret key for the given agency.
     */
    public function regenerateSecretKey(int $id): RedirectResponse
    {
        /** @var Agency $agency */
        $agency = $this->findModel($id);
        $agency->generateSecretKey();

        return back()
            ->with('success', 'Secret key regenerated successfully. Update the AGENCY_SECRET_KEY in the agency instance.');
    }

    protected function service(): AgencyService
    {
        return $this->agencyService;
    }

    protected function inertiaPage(): string
    {
        return 'platform/agencies';
    }

    protected function getFormViewData(Model $model): array
    {
        /** @var Agency $agency */
        $agency = $model;

        $countries = $this->geoDataService->getAllCountries();

        $country_codes = [];

        foreach ($countries as $country) {
            $country_codes[] = [
                'label' => ($country['phone_code'] ?? '').' '.$country['iso2'],
                'value' => $country['phone_code'] ?? '',
            ];
        }

        $primaryAddress = $agency->exists
            ? ($agency->getAddressByType('work') ?? $agency->getPrimaryAddress())
            : null;

        $default_country_code = $primaryAddress->country_code
            ?? setting('localization_default_country', 'US');
        $default_phone_code = $primaryAddress->phone_code ?? '';

        return [
            'initialValues' => $this->buildInitialValues($agency, $primaryAddress),
            'typeOptions' => $this->agencyService->getTypeOptionsForForm(),
            'ownerOptions' => $this->agencyService->getOwnerOptionsForForm(),
            'planOptions' => collect(config('astero.agency_plans', []))
                ->map(fn ($item, $key): array => ['label' => $item['label'], 'value' => $key])
                ->values()
                ->all(),
            'statusOptions' => $this->agencyService->getStatusOptionsForForm(),
            'websiteOptions' => $agency->exists ? $this->agencyService->getWebsiteOptionsForAgency((int) $agency->id) : [],
            'country_codes' => $country_codes,
            'default_country_code' => $default_country_code,
            'default_phone_code' => $default_phone_code,
        ];
    }

    protected function transformModelForEdit(Model $model): array
    {
        /** @var Agency $agency */
        $agency = $model;

        return [
            'id' => $agency->getKey(),
            'name' => $agency->name,
            'uid' => $agency->uid,
        ];
    }

    protected function transformModelForShow(Model $model): array
    {
        /** @var Agency $agency */
        $agency = $model;
        $agency->loadMissing(['owner', 'websites', 'servers', 'agencyWebsite', 'dnsProviders', 'cdnProviders']);
        $primaryAddress = $agency->getAddressByType('work') ?? $agency->getPrimaryAddress();
        $planConfig = config('astero.agency_plans.'.(string) $agency->plan, []);
        $statusConfig = config('platform.agency_statuses.'.(string) $agency->status, []);
        $websiteCount = $agency->websites()->withTrashed()->count();
        $serverCount = $agency->servers->count();
        $dnsProvidersCount = $agency->dnsProviders->count();
        $cdnProvidersCount = $agency->cdnProviders->count();
        $websiteLimit = isset($planConfig['websites']) ? (int) $planConfig['websites'] : null;
        $planUsagePercent = $websiteLimit && $websiteLimit > 0
            ? (int) round(min(100, ($websiteCount / $websiteLimit) * 100))
            : null;

        return [
            'id' => $agency->getKey(),
            'uid' => $agency->uid,
            'name' => $agency->name,
            'email' => $agency->email,
            'owner_id' => $agency->owner_id,
            'type' => $agency->type,
            'type_label' => $agency->type ? str((string) $agency->type)->headline()->toString() : null,
            'plan' => $agency->plan,
            'plan_label' => $planConfig['label'] ?? ($agency->plan ? str((string) $agency->plan)->headline()->toString() : null),
            'website_limit' => $websiteLimit,
            'plan_usage_percent' => $planUsagePercent,
            'status' => $agency->status,
            'status_label' => $statusConfig['label'] ?? ($agency->status ? str((string) $agency->status)->headline()->toString() : null),
            'has_secret_key' => ! empty($agency->secret_key),
            'is_whitelabel' => $agency->isWhitelabel(),
            'is_trashed' => $agency->trashed(),
            'deleted_at' => app_date_time_format($agency->deleted_at, 'datetime'),
            'owner_name' => $agency->owner?->name ?? $agency->owner?->first_name,
            'owner_email' => $agency->owner?->email,
            'website_id_prefix' => $agency->website_id_prefix,
            'website_id_zero_padding' => $agency->website_id_zero_padding,
            'webhook_url' => $agency->webhook_url,
            'statistics' => [
                'websites' => $websiteCount,
                'servers' => $serverCount,
                'dnsProviders' => $dnsProvidersCount,
                'cdnProviders' => $cdnProvidersCount,
                'providers' => $dnsProvidersCount + $cdnProvidersCount,
            ],
            'agency_website' => $agency->agencyWebsite
                ? [
                    'id' => $agency->agencyWebsite->getKey(),
                    'name' => (string) $agency->agencyWebsite->name,
                    'href' => route('platform.websites.show', $agency->agencyWebsite),
                ]
                : null,
            'branding' => [
                'name' => $agency->getMetadata('branding_name'),
                'website' => $agency->getMetadata('branding_website'),
                'logo' => $agency->getMetadata('branding_logo'),
                'icon' => $agency->getMetadata('branding_icon'),
            ],
            'address' => [
                'address1' => $primaryAddress?->address1,
                'city' => $primaryAddress?->city,
                'state' => $primaryAddress?->state,
                'state_code' => $primaryAddress?->state_code,
                'country' => $primaryAddress?->country,
                'country_code' => $primaryAddress?->country_code,
                'zip' => $primaryAddress?->zip,
                'phone_code' => $primaryAddress?->phone_code,
                'phone' => $primaryAddress?->phone,
            ],
            'created_at' => app_date_time_format($agency->created_at, 'datetime'),
            'updated_at' => app_date_time_format($agency->updated_at, 'datetime'),
        ];
    }

    protected function getShowViewData(Model $model): array
    {
        /** @var Agency $agency */
        $agency = $model;
        $agency->loadMissing(['servers', 'websites', 'dnsProviders', 'cdnProviders']);

        $activities = ActivityLog::query()
            ->forModel(Agency::class, $agency->id)
            ->with('causer')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $websites = Website::query()->where('agency_id', $agency->id)
            ->withTrashed()
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return [
            'websites' => $websites->map(fn (Website $website): array => [
                'id' => $website->getKey(),
                'name' => $website->name ?? $website->domain,
                'href' => route('platform.websites.show', $website),
                'subtitle' => $website->domain,
                'status' => $website->status instanceof BackedEnum ? $website->status->value : (string) $website->status,
                'status_label' => $website->status instanceof BackedEnum ? $website->status->label() : str((string) $website->status)->headline()->toString(),
                'is_primary' => (int) $website->getKey() === (int) ($agency->agency_website_id ?? 0),
            ])->values()->all(),
            'servers' => $agency->servers->map(fn ($server): array => [
                'id' => $server->getKey(),
                'name' => $server->name,
                'href' => route('platform.servers.show', $server),
                'subtitle' => $server->ip,
                'type' => $server->type,
                'type_label' => $server->type_label,
                'status' => $server->status,
                'status_label' => $server->status_label,
                'is_primary' => (bool) ($server->pivot?->is_primary ?? false),
            ])->values()->all(),
            'dnsProviders' => $agency->dnsProviders->map(fn ($provider): array => [
                'id' => $provider->getKey(),
                'name' => $provider->name,
                'href' => route('platform.providers.show', $provider),
                'subtitle' => $provider->vendor,
                'vendor' => $provider->vendor,
                'vendor_label' => $provider->vendor_label,
                'type' => $provider->type,
                'type_label' => $provider->type_label,
                'status' => $provider->status,
                'status_label' => $provider->status_label,
                'is_primary' => (bool) ($provider->pivot?->is_primary ?? false),
            ])->values()->all(),
            'cdnProviders' => $agency->cdnProviders->map(fn ($provider): array => [
                'id' => $provider->getKey(),
                'name' => $provider->name,
                'href' => route('platform.providers.show', $provider),
                'subtitle' => $provider->vendor,
                'vendor' => $provider->vendor,
                'vendor_label' => $provider->vendor_label,
                'type' => $provider->type,
                'type_label' => $provider->type_label,
                'status' => $provider->status,
                'status_label' => $provider->status_label,
                'is_primary' => (bool) ($provider->pivot?->is_primary ?? false),
            ])->values()->all(),
            'activities' => $activities->map(fn ($activity): array => [
                'id' => $activity->getKey(),
                'description' => (string) ($activity->description ?? $activity->message ?? 'Activity recorded'),
                'created_at' => app_date_time_format($activity->created_at, 'datetime'),
                'causer_name' => $activity->causer?->name ?? $activity->causer?->first_name ?? null,
            ])->values()->all(),
        ];
    }

    protected function handleRestorationSideEffects(Model $model): void
    {
        if ($model instanceof Agency) {
            $model->update(['status' => 'active']);
        }
    }

    private function buildInitialValues(Agency $agency, mixed $primaryAddress): array
    {
        return [
            'name' => (string) ($agency->name ?? ''),
            'email' => (string) ($agency->email ?? ''),
            'type' => (string) ($agency->type ?? ''),
            'plan' => (string) ($agency->plan ?? 'starter'),
            'owner_id' => $agency->owner_id ? (string) $agency->owner_id : '',
            'website_id_prefix' => (string) ($agency->website_id_prefix ?? Agency::DEFAULT_WEBSITE_ID_PREFIX),
            'website_id_zero_padding' => (string) ($agency->website_id_zero_padding ?? Agency::DEFAULT_WEBSITE_ID_ZERO_PADDING),
            'agency_website_id' => $agency->agency_website_id ? (string) $agency->agency_website_id : '',
            'webhook_url' => (string) ($agency->webhook_url ?? ''),
            'phone_code' => (string) ($primaryAddress?->phone_code ?? ''),
            'phone' => (string) ($primaryAddress?->phone ?? ''),
            'country' => (string) ($primaryAddress?->country ?? ''),
            'country_code' => (string) ($primaryAddress?->country_code ?? ''),
            'state' => (string) ($primaryAddress?->state ?? ''),
            'state_code' => (string) ($primaryAddress?->state_code ?? ''),
            'city_code' => (string) ($primaryAddress?->city_code ?? ''),
            'city' => (string) ($primaryAddress?->city ?? ''),
            'zip' => (string) ($primaryAddress?->zip ?? ''),
            'address1' => (string) ($primaryAddress?->address1 ?? ''),
            'branding_name' => (string) ($agency->getMetadata('branding_name') ?? ''),
            'branding_website' => (string) ($agency->getMetadata('branding_website') ?? ''),
            'branding_logo' => (string) ($agency->getMetadata('branding_logo') ?? ''),
            'branding_icon' => (string) ($agency->getMetadata('branding_icon') ?? ''),
            'status' => (string) ($agency->status ?? 'active'),
        ];
    }
}
