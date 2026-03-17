<?php

namespace Modules\Platform\Tests\Unit;

use Tests\TestCase;

class HestiaScriptsArgumentOrderTest extends TestCase
{
    public function test_scripts_set_user_before_sourcing_hestia_main(): void
    {
        $scripts = [
            'hestia/bin/a-create-environment-file',
            'hestia/bin/a-create-web-domain',
            'hestia/bin/a-install-ssl-certificate',
            'hestia/bin/a-revert-installation-step',
            'hestia/bin/a-update-astero',
            'hestia/bin/a-revert-astero-updates',
            'hestia/bin/a-rollback-astero',
            'hestia/bin/a-recache-application',
        ];

        foreach ($scripts as $relativePath) {
            $path = base_path($relativePath);
            $contents = file_get_contents($path);

            $this->assertNotFalse($contents, 'Failed to read '.$relativePath);

            $userPos = strpos($contents, 'user="${1:-}"');
            $sourcePos = strpos($contents, 'source "$HESTIA/func/main.sh"');

            $this->assertNotFalse($userPos, 'Expected user assignment to exist in '.$relativePath);
            $this->assertNotFalse($sourcePos, 'Expected main.sh sourcing to exist in '.$relativePath);
            $this->assertLessThan($sourcePos, $userPos, 'Expected user assignment to appear before sourcing main.sh in '.$relativePath);
        }
    }

    public function test_website_info_prefers_release_version(): void
    {
        $path = base_path('hestia/bin/a-get-website-info');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read hestia/bin/a-get-website-info');
        $this->assertStringContainsString('release_version="${current_release#v}"', $contents);
        $this->assertStringContainsString('astero_version="$release_version"', $contents);
        $this->assertStringContainsString('Usage: a-get-website-info [HESTIA_USER] DOMAIN [FORMAT]', $contents);
        $this->assertStringContainsString('if [ -n "$preferred_user" ] && [ -d "/home/$preferred_user/web/$domain" ]', $contents);
        $this->assertStringContainsString('running_count=$(echo "$status_output" | awk \'$2 == "RUNNING" {count++} END {print count+0}\')', $contents);
        $this->assertStringNotContainsString('grep -c "RUNNING" || echo "0"', $contents);
        $this->assertStringContainsString('if ! id "$user" >/dev/null 2>&1; then', $contents);
        $this->assertStringContainsString('echo "not_configured|0|0"', $contents);
        $this->assertStringContainsString('grep -Eq "FATAL.*ENOENT"', $contents);
        $this->assertStringContainsString('status_output=$(supervisorctl status "${program_name}" 2>/dev/null)', $contents);
        $this->assertStringContainsString('admin_slug=$(get_env_value "$app_root" "$web_dir" "ADMIN_SLUG")', $contents);
        $this->assertStringContainsString('"admin_slug": "${admin_slug:-}"', $contents);
        $this->assertStringNotContainsString('"redis": {', $contents);
    }

    public function test_setup_queue_worker_script_cleans_orphan_supervisor_configs(): void
    {
        $path = base_path('hestia/bin/a-setup-queue-worker');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read hestia/bin/a-setup-queue-worker');
        $this->assertStringContainsString('cleanup_orphan_supervisor_configs()', $contents);
        $this->assertStringContainsString('Astero Queue Worker Configuration', $contents);
        $this->assertStringContainsString('if ! id "$conf_user" >/dev/null 2>&1; then', $contents);
        $this->assertStringContainsString('cleanup_orphan_supervisor_configs', $contents);
    }

    public function test_install_astero_bootstraps_supervisor_before_queue_setup(): void
    {
        $path = base_path('hestia/bin/a-install-astero');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read hestia/bin/a-install-astero');
        $this->assertStringContainsString('if ! command -v supervisorctl >/dev/null 2>&1; then', $contents);
        $this->assertStringContainsString('apt-get install -y -qq supervisor', $contents);
        $this->assertStringContainsString('systemctl enable supervisor', $contents);
        $this->assertStringContainsString('systemctl start supervisor', $contents);
        $this->assertStringContainsString('if [ -x "$BIN/a-setup-queue-worker" ]; then', $contents);
    }

    public function test_setup_astero_supports_configurable_api_allowlist(): void
    {
        $path = base_path('hestia/bin/a-setup-astero');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read hestia/bin/a-setup-astero');
        $this->assertStringContainsString('--api-allowed-ip', $contents);
        $this->assertStringContainsString('API_ALLOWED_IP', $contents);
        $this->assertStringContainsString('v-change-sys-config-value API_ALLOWED_IP "$API_ALLOWED_IP"', $contents);
        $this->assertStringContainsString('if [ "$API_ALLOWED_IP" != "allow-all" ]; then', $contents);
        $this->assertStringContainsString("tr ',' '\\n'", $contents);
        $this->assertStringContainsString('normalized_api_ips', $contents);
        $this->assertStringContainsString('127.0.0.1', $contents);
        $this->assertStringContainsString('--multiphp', $contents);
        $this->assertStringContainsString('--php-versions', $contents);
        $this->assertStringContainsString('normalize_php_versions()', $contents);
        $this->assertStringContainsString('v-add-web-php "$php_version"', $contents);
    }

    public function test_setup_astero_enables_pcntl_functions_after_installing_target_php_versions(): void
    {
        $path = base_path('hestia/bin/a-setup-astero');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read hestia/bin/a-setup-astero');
        $installPos = strpos($contents, 'v-add-web-php "$php_version"');
        $enablePos = strpos($contents, 'enable_php_runtime_functions "$FUNCTIONS_TO_ENABLE"');

        $this->assertNotFalse($installPos, 'Expected PHP version install command in a-setup-astero');
        $this->assertNotFalse($enablePos, 'Expected runtime function enable call in a-setup-astero');
        $this->assertGreaterThan($installPos, $enablePos, 'Expected function enablement to run after PHP versions are installed');
        $this->assertStringContainsString('pcntl_signal', $contents);
        $this->assertStringContainsString('systemctl restart php*-fpm', $contents);
    }

    public function test_provision_server_script_consolidates_package_setup_and_delegates_to_setup_script(): void
    {
        $path = base_path('hestia/bin/a-provision-server');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read hestia/bin/a-provision-server');
        $this->assertStringContainsString('apt-get install -y -qq git supervisor screen ripgrep universal-ctags', $contents);
        $this->assertStringContainsString('apt-get install -y -qq jpegoptim optipng pngquant gifsicle libavif-bin', $contents);
        $this->assertStringContainsString('apt-get install -y -qq snapd', $contents);
        $this->assertStringContainsString('snap install svgo', $contents);
        $this->assertStringContainsString('systemctl enable supervisor', $contents);
        $this->assertStringContainsString('systemctl start supervisor', $contents);
        $this->assertStringContainsString('"$SETUP_SCRIPT"', $contents);
        $this->assertStringContainsString('--api-allowed-ip "$API_ALLOWED_IP"', $contents);
    }

    public function test_provision_hestia_script_bootstraps_installer_states_in_screen(): void
    {
        $path = base_path('hestia/bin/a-provision-hestia');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read hestia/bin/a-provision-hestia');
        $this->assertStringContainsString('STATE:INSTALLED', $contents);
        $this->assertStringContainsString('STATE:RUNNING', $contents);
        $this->assertStringContainsString('STATE:STARTED', $contents);
        $this->assertStringContainsString('apt-get remove -y ufw', $contents);
        $this->assertStringContainsString('screen -dmS "$SESSION_NAME"', $contents);
        $this->assertStringContainsString('yes | bash /tmp/hst-install.sh', $contents);
    }

    public function test_run_server_setup_screen_script_manages_start_wait_result_and_logs(): void
    {
        $path = base_path('hestia/bin/a-run-server-setup-screen');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read hestia/bin/a-run-server-setup-screen');
        $this->assertStringContainsString('start|status|wait|result|log-tail', $contents);
        $this->assertStringContainsString('start action requires --command', $contents);
        $this->assertStringContainsString('STATE:STARTED', $contents);
        $this->assertStringContainsString('WAIT_TIMEOUT', $contents);
        $this->assertStringContainsString('WAIT_DONE', $contents);
        $this->assertStringContainsString('tail -n "$TAIL_LINES" "$LOG_FILE"', $contents);
    }

    public function test_sync_releases_uses_secure_tls_by_default_with_optional_insecure_flag(): void
    {
        $path = base_path('hestia/bin/a-sync-releases');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read hestia/bin/a-sync-releases');
        $this->assertStringContainsString('--insecure', $contents);
        $this->assertStringContainsString('if [ "$tls_insecure" = true ]; then', $contents);
        $this->assertStringContainsString('curl_args+=(-k)', $contents);
        $this->assertStringContainsString('wget_args+=(--no-check-certificate)', $contents);
        $this->assertStringContainsString('API request failed (curl exit', $contents);
        $this->assertStringContainsString('API HTTP $http_status', $contents);
        $this->assertStringContainsString('API returned invalid JSON response.', $contents);
        $this->assertStringContainsString('No update found for $release_type/$package_id. Skipping sync.', $contents);
    }

    public function test_release_api_key_reader_prefers_file_and_sanitizes_line_endings(): void
    {
        $path = base_path('hestia/bin/a-common.sh');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read hestia/bin/a-common.sh');
        $filePos = strpos($contents, 'if [ -f "$RELEASE_API_KEY_FILE" ]; then');
        $envPos = strpos($contents, 'if [ -n "${RELEASE_API_KEY:-}" ]; then');
        $this->assertNotFalse($filePos, 'Expected release key file check in a-common.sh');
        $this->assertNotFalse($envPos, 'Expected RELEASE_API_KEY env fallback check in a-common.sh');
        $this->assertLessThan($envPos, $filePos, 'Expected release key file check before env fallback.');
        $this->assertStringContainsString("tr -d '\\r\\n'", $contents);
    }

    public function test_create_web_domain_script_has_legacy_backend_template_arg_guard(): void
    {
        $path = base_path('hestia/bin/a-create-web-domain');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read hestia/bin/a-create-web-domain');
        $this->assertStringContainsString('Backward-compatibility guard', $contents);
        $this->assertStringContainsString('backend_template="$web_template"', $contents);
        $this->assertStringContainsString('web_template="astero-active"', $contents);
    }

    public function test_recache_application_script_runs_astero_recache_non_interactive(): void
    {
        $path = base_path('hestia/bin/a-recache-application');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read hestia/bin/a-recache-application');
        $this->assertStringContainsString('Usage: a-recache-application HESTIA_USER DOMAIN', $contents);
        $this->assertStringContainsString('php artisan astero:recache --no-interaction', $contents);
        $this->assertStringContainsString('v-list-web-domain', $contents);
    }

    public function test_update_astero_script_aborts_when_vite_manifest_is_missing_before_switch(): void
    {
        $path = base_path('hestia/bin/a-update-astero');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read hestia/bin/a-update-astero');
        $this->assertStringContainsString('Validating Vite manifest...', $contents);
        $this->assertStringContainsString('public/build/manifest.json', $contents);
        $this->assertStringContainsString('Build manifest missing; aborting update.', $contents);

        $manifestCheckPos = strpos($contents, 'Validating Vite manifest...');
        $switchPos = strpos($contents, 'Performing atomic switch to v$target_version');

        $this->assertNotFalse($manifestCheckPos, 'Expected manifest validation block in a-update-astero');
        $this->assertNotFalse($switchPos, 'Expected atomic switch block in a-update-astero');
        $this->assertLessThan($switchPos, $manifestCheckPos, 'Expected manifest validation before atomic switch');
    }

    public function test_common_script_preserves_generated_favicon_assets_as_unique_public_files(): void
    {
        $path = base_path('hestia/bin/a-common.sh');
        $contents = file_get_contents($path);

        $this->assertNotFalse($contents, 'Failed to read hestia/bin/a-common.sh');
        $this->assertStringContainsString('favicon-96x96.png', $contents);
        $this->assertStringContainsString('web-app-manifest-192x192.png', $contents);
        $this->assertStringContainsString('web-app-manifest-512x512.png', $contents);
    }
}
