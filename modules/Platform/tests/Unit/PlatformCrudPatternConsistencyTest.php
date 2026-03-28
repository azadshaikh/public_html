<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class PlatformCrudPatternConsistencyTest extends TestCase
{
    /**
     * @return array<string, string>
     */
    private function platformRawImageUrlFormFiles(): array
    {
        $paths = array_merge(
            glob(base_path('modules/Platform/resources/js/components/*/*-form.tsx')) ?: [],
            glob(base_path('modules/Platform/resources/js/components/*/*-form-*.tsx')) ?: [],
        );
        $matches = [];

        foreach ($paths as $path) {
            $contents = file_get_contents($path);

            if ($contents === false) {
                $this->fail(sprintf('Failed to read %s', $path));
            }

            if (preg_match('/\b(?:branding_logo|branding_icon|logo_url|icon_url|favicon_url)\b/', $contents) === 1) {
                $matches[$path] = $contents;
            }
        }

        ksort($matches);

        return $matches;
    }

    /**
     * @return array<string, string>
     */
    private function platformRawImageUrlControllerFiles(): array
    {
        $paths = glob(base_path('modules/Platform/app/Http/Controllers/*Controller.php')) ?: [];
        $matches = [];

        foreach ($paths as $path) {
            $contents = file_get_contents($path);

            if ($contents === false) {
                $this->fail(sprintf('Failed to read %s', $path));
            }

            if (preg_match('/\b(?:branding_logo|branding_icon|logo_url|icon_url|favicon_url)\b/', $contents) === 1) {
                $matches[$path] = $contents;
            }
        }

        return $matches;
    }

    public function test_domain_show_page_exposes_current_domain_operations(): void
    {
        $path = base_path('modules/Platform/resources/js/pages/platform/domains/show.tsx');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/resources/js/pages/platform/domains/show.tsx');
        $this->assertStringContainsString('Generate self-signed', $contents);
        $this->assertStringContainsString('Add certificate', $contents);
        $this->assertStringContainsString('Edit domain', $contents);
        $this->assertStringContainsString('Manage DNS', $contents);
    }

    public function test_domain_request_enforces_registrar_provider_type(): void
    {
        $path = base_path('modules/Platform/app/Http/Requests/DomainRequest.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Http/Requests/DomainRequest.php');
        $this->assertStringContainsString("Rule::exists('platform_providers', 'id')->where('type', Provider::TYPE_DOMAIN_REGISTRAR)", $contents);
    }

    public function test_provider_request_enforces_vendor_type_compatibility(): void
    {
        $path = base_path('modules/Platform/app/Http/Requests/ProviderRequest.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Http/Requests/ProviderRequest.php');
        $this->assertStringContainsString('Rule::in($types)', $contents);
        $this->assertStringContainsString('Rule::in($vendors)', $contents);
        $this->assertStringContainsString('The selected vendor is not compatible with the selected provider type.', $contents);
    }

    public function test_secret_request_enforces_allowed_model_types_and_required_value_on_create(): void
    {
        $path = base_path('modules/Platform/app/Http/Requests/SecretRequest.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Http/Requests/SecretRequest.php');
        $this->assertStringContainsString("'secretable_type' => ['required', 'string', Rule::in(\$allowedSecretableTypes)]", $contents);
        $this->assertStringContainsString("'value' => \$this->isUpdate() ? ['nullable', 'string'] : ['required', 'string']", $contents);
        $this->assertStringContainsString('The selected model ID does not exist for the chosen model type.', $contents);
        $this->assertStringContainsString('Provider::class => Provider::class', $contents);
    }

    public function test_secret_form_uses_controller_provided_secretable_options(): void
    {
        $path = base_path('modules/Platform/resources/js/components/secrets/secret-form.tsx');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/resources/js/components/secrets/secret-form.tsx');
        $this->assertStringContainsString('secretableTypeOptions.map(', $contents);
        $this->assertStringContainsString("'secretable_type'", $contents);
        $this->assertStringNotContainsString('$modelTypes = [', $contents);
    }

    public function test_secret_model_includes_audit_fillable_and_relations(): void
    {
        $path = base_path('modules/Platform/app/Models/Secret.php');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read modules/Platform/app/Models/Secret.php');
        $this->assertStringContainsString("'updated_by'", $contents);
        $this->assertStringContainsString("'deleted_by'", $contents);
        $this->assertStringContainsString('public function updatedBy(): BelongsTo', $contents);
        $this->assertStringContainsString('public function deletedBy(): BelongsTo', $contents);
    }

    public function test_search_queries_escape_like_wildcards_in_remaining_crud_services(): void
    {
        $tldServicePath = base_path('modules/Platform/app/Services/TldService.php');
        $tldServiceContents = file_get_contents($tldServicePath);
        $this->assertNotFalse($tldServiceContents, 'Failed to read modules/Platform/app/Services/TldService.php');
        $this->assertStringContainsString('$search = $this->escapeLike($request->string(\'tld\')->toString());', $tldServiceContents);
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_'], ['\\\\\\\\', '\\%', '\\_'], \$value)", $tldServiceContents);

        $secretServicePath = base_path('modules/Platform/app/Services/SecretService.php');
        $secretServiceContents = file_get_contents($secretServicePath);
        $this->assertNotFalse($secretServiceContents, 'Failed to read modules/Platform/app/Services/SecretService.php');
        $this->assertStringContainsString('$search = $this->escapeLike($request->string(\'key\')->toString());', $secretServiceContents);

        $sslServicePath = base_path('modules/Platform/app/Services/DomainSslCertificateService.php');
        $sslServiceContents = file_get_contents($sslServicePath);
        $this->assertNotFalse($sslServiceContents, 'Failed to read modules/Platform/app/Services/DomainSslCertificateService.php');
        $this->assertStringContainsString('$search = $this->escapeLike((string) $request->input(\'search\'));', $sslServiceContents);

        $domainServicePath = base_path('modules/Platform/app/Services/DomainService.php');
        $domainServiceContents = file_get_contents($domainServicePath);
        $this->assertNotFalse($domainServiceContents, 'Failed to read modules/Platform/app/Services/DomainService.php');
        $this->assertStringContainsString('$registrarPattern = \'%\'.$this->escapeLike((string) $registrar).\'%\';', $domainServiceContents);
    }

    public function test_domain_query_builders_use_correct_table_names_and_safe_sorting_search(): void
    {
        $domainQueryBuilderPath = base_path('modules/Platform/app/Models/QueryBuilders/DomainQueryBuilder.php');
        $domainQueryBuilderContents = file_get_contents($domainQueryBuilderPath);
        $this->assertNotFalse($domainQueryBuilderContents, 'Failed to read modules/Platform/app/Models/QueryBuilders/DomainQueryBuilder.php');
        $this->assertStringContainsString("where('platform_domains.name', 'ilike', \$pattern)", $domainQueryBuilderContents);
        $this->assertStringContainsString("'platform_domains.created_at'", $domainQueryBuilderContents);
        $this->assertStringContainsString("str_replace(['\\\\', '%', '_'], ['\\\\\\\\', '\\%', '\\_'], \$value)", $domainQueryBuilderContents);
        $this->assertStringNotContainsString("'domains.name'", $domainQueryBuilderContents);

        $dnsQueryBuilderPath = base_path('modules/Platform/app/Models/QueryBuilders/DomainDnsRecordQueryBuilder.php');
        $dnsQueryBuilderContents = file_get_contents($dnsQueryBuilderPath);
        $this->assertNotFalse($dnsQueryBuilderContents, 'Failed to read modules/Platform/app/Models/QueryBuilders/DomainDnsRecordQueryBuilder.php');
        $this->assertStringContainsString("where('platform_dns_records.name', 'ilike', \$pattern)", $dnsQueryBuilderContents);
        $this->assertStringContainsString('protected array $allowedFields = [', $dnsQueryBuilderContents);
        $this->assertStringContainsString('protected array $allowedDirections = [\'asc\', \'desc\'];', $dnsQueryBuilderContents);
        $this->assertStringContainsString('if (in_array($field, $this->allowedFields, true) && in_array($direction, $this->allowedDirections, true))', $dnsQueryBuilderContents);
        $this->assertStringNotContainsString("'dns_records.name'", $dnsQueryBuilderContents);
    }

    public function test_platform_raw_image_url_forms_use_media_picker_url_input_and_picker_page_props(): void
    {
        $matchedForms = $this->platformRawImageUrlFormFiles();

        $this->assertNotEmpty($matchedForms, 'Expected at least one Platform form with raw image URL fields.');

        foreach ($matchedForms as $path => $contents) {
            $this->assertStringContainsString('MediaPickerUrlInput', $contents, sprintf('Expected %s to use MediaPickerUrlInput for raw image URL fields.', $path));
            $this->assertStringContainsString('PlatformMediaPickerPageProps', $contents, sprintf('Expected %s to accept PlatformMediaPickerPageProps when using raw image URL fields.', $path));

            $resource = basename(dirname($path));

            foreach (['create', 'edit'] as $page) {
                $pagePath = base_path(sprintf('modules/Platform/resources/js/pages/platform/%s/%s.tsx', $resource, $page));

                if (! is_file($pagePath)) {
                    continue;
                }

                $pageContents = file_get_contents($pagePath);
                $this->assertNotFalse($pageContents, sprintf('Failed to read %s', $pagePath));
                $this->assertStringContainsString('PlatformMediaPickerPageProps', $pageContents, sprintf('Expected %s to include PlatformMediaPickerPageProps.', $pagePath));
                $this->assertStringContainsString('pickerMedia={props.pickerMedia}', $pageContents, sprintf('Expected %s to pass pickerMedia through to the form.', $pagePath));
                $this->assertStringContainsString('pickerFilters={props.pickerFilters}', $pageContents, sprintf('Expected %s to pass pickerFilters through to the form.', $pagePath));
                $this->assertStringContainsString('uploadSettings={props.uploadSettings}', $pageContents, sprintf('Expected %s to pass uploadSettings through to the form.', $pagePath));
            }
        }
    }

    public function test_platform_controllers_with_raw_image_url_fields_expose_media_picker_props(): void
    {
        $matchedControllers = $this->platformRawImageUrlControllerFiles();

        $this->assertNotEmpty($matchedControllers, 'Expected at least one Platform controller with raw image URL fields.');

        foreach ($matchedControllers as $path => $contents) {
            $this->assertStringContainsString('HasMediaPicker', $contents, sprintf('Expected %s to use HasMediaPicker for raw image URL fields.', $path));
            $this->assertStringContainsString('getMediaPickerProps', $contents, sprintf('Expected %s to expose getMediaPickerProps() for raw image URL fields.', $path));
        }
    }
}
