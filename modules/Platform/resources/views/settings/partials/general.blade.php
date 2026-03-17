<div class="card">
    <div class="card-header">
        <div class="d-flex align-items-center">
            <i class="ri-settings-3-fill me-2"></i>
            <h5 class="card-title">{{ __('platform::platform.general_settings') }}</h5>
        </div>
    </div>
    <form class="needs-validation" id="general-form" action="{{ route('platform.settings.update') }}" method="POST"
        novalidate>
        @csrf
        <input name="section" type="hidden" value="general">
        <div class="card-body">
            <x-form-elements.select layout="horizontal" class="mb-3" id="trail_server_id" name="trail_server_id"
                value="{{ session('platform_settings_values.trail_server_id', $setting_data['platform_trail_server_id'] ?? '') }}"
                divclass="form-group" label="Trial Server"
                labelclass="form-label required" placeholder="Select Trial Server" inputclass="form-control"
                :options="json_encode($servers_options, true)"
                infotext="Select the default server for trial websites." />

            <x-form-elements.select layout="horizontal" class="mb-3" id="default_server_group" name="default_server_group"
                value="{{ session('platform_settings_values.default_server_group', $setting_data['platform_default_server_group'] ?? '') }}"
                divclass="form-group" label="Default Server Group" labelclass="form-label required"
                placeholder="Select Default Server Group" inputclass="form-control"
                :options="json_encode($server_groups_options, true)"
                infotext="Default group assigned to new servers." />

            <x-form-elements.input layout="horizontal" class="mb-3" id="default_sub_domain" name="default_sub_domain"
                value="{{ session('platform_settings_values.default_sub_domain', $setting_data['platform_default_sub_domain'] ?? '') }}"
                divclass="form-group" label="Trial Domain" labelclass="form-label required"
                placeholder="Enter Trial Domain" inputclass="form-control"
                infotext="Base domain for trial website subdomains (e.g., trial.example.com)." />

            <x-form-elements.textarea layout="horizontal" class="mb-3" id="default_domain_ssl_key"
                name="default_domain_ssl_key"
                value="{{ session('platform_settings_values.default_domain_ssl_key', $setting_data['platform_default_domain_ssl_key'] ?? '') }}"
                divclass="form-group" label="Domain SSL Key" labelclass="form-label required"
                placeholder="Enter Domain SSL Key" inputclass="form-control"
                infotext="Private key for the wildcard SSL certificate." />

            <x-form-elements.textarea layout="horizontal" class="mb-3" id="default_domain_ssl_crt"
                name="default_domain_ssl_crt"
                value="{{ session('platform_settings_values.default_domain_ssl_crt', $setting_data['platform_default_domain_ssl_crt'] ?? '') }}"
                divclass="form-group" label="Domain SSL Certificate" labelclass="form-label required"
                placeholder="Enter Domain SSL Certificate" inputclass="form-control"
                infotext="Full chain SSL certificate for the trial domain." />

            <x-form-elements.datepicker layout="horizontal" class="mb-3" id="default_ssl_expiry" name="default_ssl_expiry"
                mode="date"
                value="{{ session('platform_settings_values.default_ssl_expiry', $setting_data['platform_default_ssl_expiry'] ?? '') }}"
                divclass="form-group" label="Default SSL Expiry" labelclass="form-label required"
                placeholder="Enter Default SSL Expiry" inputclass="form-control"
                infotext="Expiration date of the SSL certificate." />
        </div>
        <div class="card-footer d-flex justify-content-end bg-transparent py-3">
            <button class="btn btn-primary" type="submit">
                <i class="ri-save-line me-1"></i> <span class="btn-text">{{ __('settings.save_changes') }}</span>
            </button>
        </div>
    </form>
</div>
