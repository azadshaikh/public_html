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

        return [
            'id' => $agency->getKey(),
            'uid' => $agency->uid,
            'name' => $agency->name,
            'email' => $agency->email,
            'type' => $agency->type,
            'plan' => $agency->plan,
            'status' => $agency->status,
            'owner_name' => $agency->owner?->first_name,
            'owner_email' => $agency->owner?->email,
            'website_id_prefix' => $agency->website_id_prefix,
            'website_id_zero_padding' => $agency->website_id_zero_padding,
            'webhook_url' => $agency->webhook_url,
            'branding' => [
                'name' => $agency->getMetadata('branding_name'),
                'website' => $agency->getMetadata('branding_website'),
                'logo' => $agency->getMetadata('branding_logo'),
                'icon' => $agency->getMetadata('branding_icon'),
            ],
            'address' => [
                'address1' => $primaryAddress?->address1,
                'city' => $primaryAddress?->city,
                'state_code' => $primaryAddress?->state_code,
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
            ])->values()->all(),
            'servers' => $agency->servers->map(fn ($server): array => [
                'id' => $server->getKey(),
                'name' => $server->name,
                'href' => route('platform.servers.show', $server),
                'subtitle' => $server->ip,
                'status' => $server->status,
            ])->values()->all(),
            'dnsProviders' => $agency->dnsProviders->map(fn ($provider): array => [
                'id' => $provider->getKey(),
                'name' => $provider->name,
                'subtitle' => $provider->vendor,
                'status' => $provider->status,
            ])->values()->all(),
            'cdnProviders' => $agency->cdnProviders->map(fn ($provider): array => [
                'id' => $provider->getKey(),
                'name' => $provider->name,
                'subtitle' => $provider->vendor,
                'status' => $provider->status,
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
            'country_code' => (string) ($primaryAddress?->country_code ?? ''),
            'state_code' => (string) ($primaryAddress?->state_code ?? ''),
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
