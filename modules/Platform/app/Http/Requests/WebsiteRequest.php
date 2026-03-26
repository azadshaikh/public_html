<?php

namespace Modules\Platform\Http\Requests;

use App\Rules\WebsiteDomainUnique;
use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Validation\Rule;
use Modules\Platform\Definitions\WebsiteDefinition;
use Modules\Platform\Enums\WebsiteStatus;
use Modules\Platform\Models\Provider;
use Modules\Platform\Models\Website;
use RuntimeException;

class WebsiteRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        $typeKeys = array_keys(config('platform.website.types', []));
        $planKeys = array_keys(config('astero.website_plans', []));
        $allowedStatuses = [
            WebsiteStatus::Provisioning->value,
            WebsiteStatus::Active->value,
            WebsiteStatus::Failed->value,
            WebsiteStatus::Suspended->value,
            WebsiteStatus::Expired->value,
        ];

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in($typeKeys)],
            'plan' => ['nullable', 'string', Rule::in($planKeys)],
            'domain' => $this->isUpdate()
                ? ['required', 'string', $this->uniqueRule('domain')]
                : ['required', 'string', new WebsiteDomainUnique],
            'server_id' => ['required', 'integer', $this->existsRule('platform_servers', 'id')],
            'owner_id' => ['nullable', 'integer'],  // deprecated: owner_id column removed, kept for backward compat
            'agency_id' => ['nullable', 'integer', $this->existsRule('platform_agencies', 'id')],
            'niches' => ['nullable', 'array'],
            'niches.*' => ['string', 'max:100'],
            'dns_mode' => ['nullable', 'string', Rule::in(['subdomain', 'managed', 'external'])],

            'dns_provider_id' => [
                'required',
                'integer',
                Rule::exists('platform_providers', 'id')->where('type', Provider::TYPE_DNS),
            ],
            'cdn_provider_id' => [
                'required',
                'integer',
                Rule::exists('platform_providers', 'id')->where('type', Provider::TYPE_CDN),
            ],

            'is_www' => ['nullable', 'boolean'],
            'is_agency' => ['nullable', 'boolean'],
            'skip_cdn' => ['nullable', 'boolean'],
            'skip_dns' => ['nullable', 'boolean'],
            'skip_ssl_issue' => ['nullable', 'boolean'],
            'skip_email' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', Rule::in($allowedStatuses)],
            'expired_on' => ['nullable', 'date'],
        ];

        if (! $this->isUpdate()) {
            $rules['website_username'] = ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9_\-]+$/'];
            $rules['owner_password'] = ['nullable', 'string', 'min:8'];
            $rules['customer_name'] = ['nullable', 'string', 'max:255'];
            $rules['customer_email'] = ['nullable', 'email', 'max:255'];
        }

        if ($this->has('only_add_data')) {
            $rules['owner_password'] = ['required'];
            $rules['db_name'] = ['required'];
            $rules['db_user_name'] = ['required'];
            $rules['db_password'] = ['required'];
            $rules['super_user_email'] = ['required', 'email'];
            $rules['super_user_password'] = ['required'];
            $rules['uid'] = ['required', Rule::unique('platform_websites', 'uid')];
            $rules['secret_key'] = ['required', Rule::unique('platform_websites', 'secret_key')];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->isUpdate() && is_string($this->input('website_username')) && trim($this->input('website_username')) !== '') {
                try {
                    $normalized = Website::normalizeCustomUidInput($this->input('website_username'));
                } catch (RuntimeException $e) {
                    $validator->errors()->add('website_username', $e->getMessage());

                    return;
                }

                if (Website::query()->where('uid', $normalized)->exists()) {
                    $validator->errors()->add('website_username', 'This server username is already taken.');

                    return;
                }
            }

            if ($this->input('is_agency') && $this->input('agency_id')) {
                $currentWebsite = $this->getModel();
                $existingAgencyWebsite = Website::query()->where('agency_id', $this->input('agency_id'))
                    ->isAgencyWebsite()
                    ->when($currentWebsite, fn ($query) => $query->where('id', '!=', $currentWebsite->id))
                    ->exists();

                if ($existingAgencyWebsite) {
                    $validator->errors()->add(
                        'is_agency',
                        'This agency already has a designated agency website. Only one website per agency can have the is_agency flag enabled.'
                    );
                }
            }
        });
    }

    protected function definition(): ScaffoldDefinition
    {
        return new WebsiteDefinition;
    }
}
