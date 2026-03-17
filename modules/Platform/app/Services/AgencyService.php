<?php

namespace Modules\Platform\Services;

use App\Contracts\ScaffoldServiceInterface;
use App\Models\User;
use App\Scaffold\ScaffoldDefinition;
use App\Services\GeoDataService;
use App\Traits\Scaffoldable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Modules\Platform\Definitions\AgencyDefinition;
use Modules\Platform\Http\Resources\AgencyResource;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Website;

class AgencyService implements ScaffoldServiceInterface
{
    use Scaffoldable {
        create as protected scaffoldCreate;
        update as protected scaffoldUpdate;
    }

    public function getScaffoldDefinition(): ScaffoldDefinition
    {
        return new AgencyDefinition;
    }

    public function getDataGridConfig(): array
    {
        $config = $this->scaffold()->toDataGridConfig();

        foreach (($config['filters'] ?? []) as $i => $filter) {
            if (($filter['key'] ?? null) === 'type') {
                $config['filters'][$i]['options'] = $this->getTypeOptions();
            }

            if (($filter['key'] ?? null) === 'owner_id') {
                $config['filters'][$i]['options'] = $this->getOwnerOptions();
            }
        }

        return $config;
    }

    /**
     * Override create to handle address data using AddressableTrait.
     */
    public function create(array $data): Agency
    {
        /** @var Agency $agency */
        $agency = $this->scaffoldCreate($data);

        // Assign UID based on record ID
        $agency->assignUid();

        // Generate secret_key if agency_website_id is set
        if ($agency->agency_website_id) {
            $agency->generateSecretKey();
            $agency->save();
        }

        // Save address using the AddressableTrait method with 'work' type
        $agency->saveAddress($this->prepareAddressData($data), 'work', true);

        return $agency->fresh();
    }

    /**
     * Override update to handle address data and branding metadata.
     */
    public function update(Model $model, array $data): Agency
    {
        throw_unless($model instanceof Agency, InvalidArgumentException::class, 'Expected Agency model instance.');

        $previousWebsiteId = $model->agency_website_id;

        // Update branding fields using setMetadata to properly merge
        foreach ($this->getBrandingFields() as $field) {
            if (array_key_exists($field, $data)) {
                $model->setMetadata($field, $data[$field]);
            }
        }

        // Update standard fields
        /** @var Agency $updatedAgency */
        $updatedAgency = $this->scaffoldUpdate($model, $data);

        // Generate secret_key if agency_website_id was just set or changed
        if ($updatedAgency->agency_website_id && $updatedAgency->agency_website_id !== $previousWebsiteId) {
            $updatedAgency->generateSecretKey();
            $updatedAgency->save();
        }

        // Save address using the AddressableTrait method with 'work' type
        $updatedAgency->saveAddress($this->prepareAddressData($data), 'work', true);

        return $updatedAgency->fresh();
    }

    /**
     * Get countries list for form using GeoDataService
     */
    public function getCountryOptions(): array
    {
        $geoService = resolve(GeoDataService::class);
        $countries = $geoService->getAllCountries();

        return array_map(
            fn (array $country): array => [
                'value' => $country['iso2'],
                'label' => $country['name'],
                'phone_code' => $country['phone_code'] ?? '',
            ],
            $countries
        );
    }

    /**
     * Get states for a country using GeoDataService
     */
    public function getStateOptions(string $countryCode): array
    {
        if ($countryCode === '' || $countryCode === '0') {
            return [];
        }

        $geoService = resolve(GeoDataService::class);
        $states = $geoService->getStatesByCountryCode($countryCode);

        return array_map(
            fn (array $state): array => [
                'value' => $state['iso2'] ?? $state['iso3166_2'] ?? '',
                'label' => $state['name'],
            ],
            $states
        );
    }

    public function getStatusOptions(): array
    {
        return collect(config('platform.agency_statuses', []))
            ->reject(fn ($item, $key): bool => $key === 'trash')
            ->mapWithKeys(fn ($item, $key): array => [$key => $item['label'] ?? $key])
            ->toArray();
    }

    public function getStatusOptionsForForm(): array
    {
        return collect(config('platform.agency_statuses', []))
            ->reject(fn ($item, $key): bool => $key === 'trash')
            ->map(fn ($item, $key): array => [
                'value' => $key,
                'label' => $item['label'] ?? $key,
            ])
            ->values()
            ->all();
    }

    public function getTypeOptions(): array
    {
        return collect(config('platform.agency_types'))
            ->mapWithKeys(fn ($item): array => [$item['value'] => $item['label']])
            ->toArray();
    }

    public function getTypeOptionsForForm(): array
    {
        return collect(config('platform.agency_types'))
            ->map(fn ($item): array => [
                'value' => $item['value'],
                'label' => $item['label'],
            ])
            ->values()
            ->all();
    }

    public function getOwnerOptions(): array
    {
        // getAddedByOptions returns ['id' => 'name'] format
        return User::getAddedByOptions();
    }

    public function getOwnerOptionsForForm(): array
    {
        $options = User::getAddedByOptions();

        // Convert ['id' => 'name'] to [['value' => id, 'label' => name], ...]
        return collect($options)
            ->map(fn ($name, $id): array => ['value' => $id, 'label' => $name])
            ->values()
            ->all();
    }

    /**
     * Get website options for agency dropdown (only websites with is_agency flag belonging to this agency)
     */
    public function getWebsiteOptionsForAgency(int $agencyId): array
    {
        $websites = Website::query()->where('agency_id', $agencyId)
            ->isAgencyWebsite()
            ->orderBy('name')
            ->get(['id', 'name', 'domain', 'metadata']);

        /** @var Collection<int, Website> $websites */
        return $websites->map(fn ($website): array => [
            'value' => $website->id,
            'label' => $website->name.' ('.$website->domain.')',
        ])->all();
    }

    protected function getResourceClass(): ?string
    {
        return AgencyResource::class;
    }

    protected function getEagerLoadRelationships(): array
    {
        return [
            'owner:id,first_name,last_name,email',
            'primaryAddress',
            'createdBy:id,first_name,last_name',
            'updatedBy:id,first_name,last_name',
        ];
    }

    protected function customizeListQuery(Builder $query, Request $request): void
    {
        $query->withCount('websites');
    }

    protected function prepareCreateData(array $data): array
    {
        // Collect branding fields for metadata
        $metadata = [];
        foreach ($this->getBrandingFields() as $field) {
            if (isset($data[$field])) {
                $metadata[$field] = $data[$field];
            }
        }

        return [
            'uid' => null, // Will be set after creation based on record ID
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'type' => $data['type'] ?? null,
            'plan' => $data['plan'] ?? 'starter',
            'website_id_prefix' => $data['website_id_prefix'] ?? Agency::DEFAULT_WEBSITE_ID_PREFIX,
            'website_id_zero_padding' => $data['website_id_zero_padding'] ?? Agency::DEFAULT_WEBSITE_ID_ZERO_PADDING,
            'owner_id' => $data['owner_id'] ?? null,
            'metadata' => $metadata === [] ? null : $metadata,
            'agency_website_id' => $data['agency_website_id'] ?? null,
            'webhook_url' => $data['webhook_url'] ?? null,
            'status' => $data['status'] ?? 'active',
        ];
    }

    protected function prepareUpdateData(array $data): array
    {
        // Note: Branding fields are handled separately in update() method
        // to properly merge with existing metadata using setMetadata()
        return [
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'type' => $data['type'] ?? null,
            'plan' => $data['plan'] ?? null,
            'website_id_prefix' => $data['website_id_prefix'] ?? Agency::DEFAULT_WEBSITE_ID_PREFIX,
            'website_id_zero_padding' => $data['website_id_zero_padding'] ?? Agency::DEFAULT_WEBSITE_ID_ZERO_PADDING,
            'owner_id' => $data['owner_id'] ?? null,
            'agency_website_id' => $data['agency_website_id'] ?? null,
            'webhook_url' => $data['webhook_url'] ?? null,
            'status' => $data['status'] ?? 'active',
        ];
    }

    /**
     * Get branding fields that should be stored in metadata.
     */
    protected function getBrandingFields(): array
    {
        return ['branding_name', 'branding_logo', 'branding_icon', 'branding_website'];
    }

    /**
     * Prepare address data from form input
     */
    protected function prepareAddressData(array $data): array
    {
        $geoService = resolve(GeoDataService::class);

        $addressData = [
            'address1' => $data['address1'] ?? null,
            'address2' => $data['address2'] ?? null,
            'country' => $data['country'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'state' => $data['state'] ?? null,
            'state_code' => $data['state_code'] ?? null,
            'city' => $data['city'] ?? null,
            'city_code' => $data['city_code'] ?? null,
            'zip' => $data['zip'] ?? null,
            'phone' => $data['phone'] ?? null,
            'phone_code' => $data['phone_code'] ?? null,
        ];

        // Populate country name from country code
        if (! empty($addressData['country_code'])) {
            $countryResponse = $geoService->getCountryByCode($addressData['country_code']);
            $countryName = $countryResponse['data']['name'] ?? null;
            if ($countryName) {
                $addressData['country'] = $countryName;
            }
        }

        // Populate state name from state code
        if (! empty($addressData['state_code']) && ! empty($addressData['country_code'])) {
            // State API expects format: COUNTRY-STATE (e.g., IN-GJ)
            $fullStateCode = $addressData['country_code'].'-'.$addressData['state_code'];
            $stateResponse = $geoService->getStateByCode($fullStateCode);
            $stateName = $stateResponse['data']['name'] ?? null;
            if ($stateName) {
                $addressData['state'] = $stateName;
            }
        }

        return $addressData;
    }
}
