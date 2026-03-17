<?php

namespace Modules\Platform\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Validation\Rule;
use Modules\Platform\Definitions\AgencyDefinition;
use Modules\Platform\Models\Agency;
use Modules\Platform\Models\Website;

class AgencyRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        $types = array_keys(config('platform.agency_types', []));
        $plans = array_keys(config('astero.agency_plans', []));
        $statuses = array_keys(config('platform.agency_statuses', []));
        $statuses = array_values(array_filter($statuses, fn (string $s): bool => $s !== 'trash'));

        return [
            'name' => ['required', 'string', 'max:255'],
            'branding_website' => ['nullable', 'url', 'max:500'],
            'email' => ['required', 'email', 'max:255'],
            'type' => ['required', 'string', Rule::in($types)],
            'plan' => ['required', 'string', Rule::in($plans)],
            'website_id_prefix' => ['required', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'website_id_zero_padding' => [
                'required',
                'integer',
                'min:'.Agency::MIN_WEBSITE_ID_ZERO_PADDING,
                'max:'.Agency::MAX_WEBSITE_ID_ZERO_PADDING,
            ],
            'owner_id' => ['required', 'integer', $this->existsRule('users', 'id')],
            'agency_website_id' => ['nullable', 'integer', $this->existsRule('platform_websites', 'id')],
            'webhook_url' => ['nullable', 'url', 'max:500'],

            // Contact fields (stored in addresses table)
            'phone_code' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:20'],

            // Address fields (polymorphic address)
            'country' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'max:10'],
            'state' => ['nullable', 'string', 'max:255'],
            'state_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'city_code' => ['nullable', 'string', 'max:10'],
            'address1' => ['nullable', 'string', 'max:1000'],
            'zip' => ['nullable', 'string', 'max:25'],

            // Branding/media
            'branding_name' => ['nullable', 'string', 'max:255'],
            'branding_logo' => ['nullable', 'url', 'max:500'],
            'branding_icon' => ['nullable', 'url', 'max:500'],

            'status' => ['required', 'string', Rule::in($statuses)],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $agencyWebsiteId = $this->input('agency_website_id');

            if ($agencyWebsiteId === null || $agencyWebsiteId === '') {
                return;
            }

            if (! $this->isUpdate()) {
                $validator->errors()->add('agency_website_id', 'Agency website can only be linked after the agency is created.');

                return;
            }

            $agency = $this->getModel();

            if ($agency === null) {
                $validator->errors()->add('agency_website_id', 'Unable to validate agency website for this agency.');

                return;
            }

            $isValidAgencyWebsite = Website::query()
                ->whereKey((int) $agencyWebsiteId)
                ->where('agency_id', $agency->id)
                ->isAgencyWebsite()
                ->exists();

            if (! $isValidAgencyWebsite) {
                $validator->errors()->add('agency_website_id', 'The selected website must belong to this agency and be marked as an agency website.');
            }
        });
    }

    protected function definition(): ScaffoldDefinition
    {
        return new AgencyDefinition;
    }
}
