<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class AgencyCrudPatternConsistencyTest extends TestCase
{
    public function test_agency_show_restore_action_uses_patch_method_and_supported_statuses(): void
    {
        $path = base_path('modules/Platform/resources/js/pages/platform/agencies/components/agency-show-overview.tsx');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/resources/js/pages/platform/agencies/components/agency-show-overview.tsx');
        $this->assertStringContainsString('Restore Agency', $contents);
        $this->assertStringContainsString("route('platform.agencies.restore', agency.id)", $contents);
        $this->assertStringContainsString("'patch'", $contents);
        $this->assertStringNotContainsString("'suspended' =>", $contents);
    }

    public function test_agency_provider_controller_uses_typed_form_requests_and_membership_guards(): void
    {
        $path = base_path('modules/Platform/app/Http/Controllers/AgencyProviderController.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Http/Controllers/AgencyProviderController.php');
        $this->assertStringContainsString('use Modules\\Platform\\Http\\Requests\\AgencyAttachDnsProvidersRequest;', $contents);
        $this->assertStringContainsString('use Modules\\Platform\\Http\\Requests\\AgencyAttachCdnProvidersRequest;', $contents);
        $this->assertStringContainsString('public function attachDnsProviders(AgencyAttachDnsProvidersRequest $request, $id): JsonResponse', $contents);
        $this->assertStringContainsString('public function attachCdnProviders(AgencyAttachCdnProvidersRequest $request, $id): JsonResponse', $contents);
        $this->assertStringContainsString("dnsProviders()->where('platform_providers.id', \$providerModel->id)->exists()", $contents);
        $this->assertStringContainsString("cdnProviders()->where('platform_providers.id', \$providerModel->id)->exists()", $contents);
        $this->assertStringContainsString('Provider is not attached to this agency', $contents);
    }

    public function test_agency_provider_attach_requests_enforce_provider_type_and_primary_membership(): void
    {
        $dnsRequestPath = base_path('modules/Platform/app/Http/Requests/AgencyAttachDnsProvidersRequest.php');
        $dnsRequestContents = file_get_contents($dnsRequestPath);
        $this->assertNotFalse($dnsRequestContents, 'Failed to read modules/Platform/app/Http/Requests/AgencyAttachDnsProvidersRequest.php');
        $this->assertStringContainsString("Rule::exists('platform_providers', 'id')->where('type', Provider::TYPE_DNS)", $dnsRequestContents);
        $this->assertStringContainsString('The selected primary provider must be included in provider_ids.', $dnsRequestContents);

        $cdnRequestPath = base_path('modules/Platform/app/Http/Requests/AgencyAttachCdnProvidersRequest.php');
        $cdnRequestContents = file_get_contents($cdnRequestPath);
        $this->assertNotFalse($cdnRequestContents, 'Failed to read modules/Platform/app/Http/Requests/AgencyAttachCdnProvidersRequest.php');
        $this->assertStringContainsString("Rule::exists('platform_providers', 'id')->where('type', Provider::TYPE_CDN)", $cdnRequestContents);
        $this->assertStringContainsString('The selected primary provider must be included in provider_ids.', $cdnRequestContents);
    }

    public function test_agency_request_enforces_agency_website_scope_and_agency_website_flag(): void
    {
        $path = base_path('modules/Platform/app/Http/Requests/AgencyRequest.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Http/Requests/AgencyRequest.php');
        $this->assertStringContainsString('Agency website can only be linked after the agency is created.', $contents);
        $this->assertStringContainsString("->where('agency_id', \$agency->id)", $contents);
        $this->assertStringContainsString('->isAgencyWebsite()', $contents);
    }

    public function test_agency_inertia_form_restores_guided_contact_and_platform_defaults_ui(): void
    {
        $formPath = base_path('modules/Platform/resources/js/components/agencies/agency-form.tsx');
        $sectionsPath = base_path('modules/Platform/resources/js/components/agencies/agency-form-sections.tsx');
        $formContents = file_get_contents($formPath);
        $sectionsContents = file_get_contents($sectionsPath);
        $contents = implode("\n", [$formContents, $sectionsContents]);

        $this->assertNotFalse($formContents, 'Failed to read modules/Platform/resources/js/components/agencies/agency-form.tsx');
        $this->assertNotFalse($sectionsContents, 'Failed to read modules/Platform/resources/js/components/agencies/agency-form-sections.tsx');
        $this->assertStringContainsString('CountrySelect', $contents);
        $this->assertStringContainsString('StateSelect', $contents);
        $this->assertStringContainsString('CitySelect', $contents);
        $this->assertStringContainsString('Website ID format preview', $contents);
        $this->assertStringContainsString('Secret key is generated automatically when an agency website is linked or changed.', $contents);
        $this->assertStringContainsString('Save the agency first to link its SaaS', $contents);
        $this->assertStringContainsString('website and enable the shared API secret.', $contents);
    }

    public function test_agency_controller_initial_values_include_structured_address_fields_for_inertia_form(): void
    {
        $path = base_path('modules/Platform/app/Http/Controllers/AgencyController.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Http/Controllers/AgencyController.php');
        $this->assertStringContainsString("'country' => (string) (\$primaryAddress?->country ?? '')", $contents);
        $this->assertStringContainsString("'state' => (string) (\$primaryAddress?->state ?? '')", $contents);
        $this->assertStringContainsString("'city_code' => (string) (\$primaryAddress?->city_code ?? '')", $contents);
        $this->assertStringContainsString("'country_codes' => \$country_codes", $contents);
    }

    public function test_agency_server_controller_prevents_setting_primary_server_when_unattached(): void
    {
        $path = base_path('modules/Platform/app/Http/Controllers/AgencyServerController.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Http/Controllers/AgencyServerController.php');
        $this->assertStringContainsString("servers()->where('platform_servers.id', \$serverModel->id)->exists()", $contents);
        $this->assertStringContainsString('Server is not attached to this agency', $contents);
    }

    public function test_agency_query_builder_uses_safe_search_and_valid_columns(): void
    {
        $path = base_path('modules/Platform/app/Models/QueryBuilders/AgencyQueryBuilder.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Models/QueryBuilders/AgencyQueryBuilder.php');
        $this->assertStringContainsString('$escapedSearch = $this->escapeLike($search);', $contents);
        $this->assertStringContainsString("COALESCE(metadata->>'branding_website', '') ilike ?", $contents);
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_'], ['\\\\\\\\', '\\%', '\\_'], \$value)", $contents);
        $this->assertStringNotContainsString("->orWhere('slug', 'ilike'", $contents);
        $this->assertStringNotContainsString("->orWhere('mobile', 'ilike'", $contents);
        $this->assertStringNotContainsString("->orWhere('city_name', 'ilike'", $contents);
    }

    public function test_agency_model_fillable_contains_audit_fields(): void
    {
        $path = base_path('modules/Platform/app/Models/Agency.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Models/Agency.php');
        $this->assertStringContainsString("'created_by'", $contents);
        $this->assertStringContainsString("'updated_by'", $contents);
        $this->assertStringContainsString("'deleted_by'", $contents);
    }
}
