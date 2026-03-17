<?php

namespace Modules\Platform\Http\Requests;

use App\Scaffold\ScaffoldDefinition;
use App\Scaffold\ScaffoldRequest;
use Illuminate\Validation\Rule;
use Modules\Platform\Definitions\ServerDefinition;
use Modules\Platform\Models\Provider;

class ServerRequest extends ScaffoldRequest
{
    public function rules(): array
    {
        $typeKeys = array_keys(config('platform.server_types', []));
        $statusKeys = array_keys(config('platform.server_statuses', []));
        $statusKeys = array_values(array_filter($statusKeys, fn (string $s): bool => $s !== 'trash'));

        $creationMode = $this->input('creation_mode', 'manual');
        $isProvisionMode = $creationMode === 'provision';

        // Base rules for all modes
        $rules = [
            'creation_mode' => ['nullable', 'string', Rule::in(['manual', 'provision'])],
            'name' => ['required', 'string', 'max:155'],
            'ip' => ['required', 'string', 'max:45', 'ip', $this->uniqueRule('ip')],
            'release_api_key' => ['nullable', 'string', 'max:255'],
            'fqdn' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in($typeKeys)],
            'provider_id' => [
                'required',
                'integer',
                Rule::exists('platform_providers', 'id')->where('type', Provider::TYPE_SERVER),
            ],
            'monitor' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', Rule::in($statusKeys)],
            'location_country_code' => ['nullable', 'string', 'max:2'],
            'location_country' => ['nullable', 'string', 'max:100'],
            'location_city_code' => ['nullable', 'string', 'max:20'],
            'location_city' => ['nullable', 'string', 'max:100'],
            'max_domains' => ['nullable', 'integer', 'min:0'],
            'ssh_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'ssh_user' => ['nullable', 'string', 'max:50'],
            'ssh_public_key' => ['nullable', 'string'],
            'ssh_private_key' => ['nullable', 'string'],
        ];

        // Manual mode: HestiaCP already installed
        if (! $isProvisionMode && ! $this->isUpdate()) {
            $rules['port'] = ['required', 'integer', 'min:1', 'max:65535'];
            $rules['access_key_id'] = ['required', 'string'];
            $rules['access_key_secret'] = ['required', 'string'];
        }

        // Manual mode: update
        if (! $isProvisionMode && $this->isUpdate()) {
            $rules['port'] = ['required', 'integer', 'min:1', 'max:65535'];
            $rules['access_key_id'] = ['required', 'string'];
            $rules['access_key_secret'] = ['nullable', 'string']; // Optional on update
        }

        // Provision mode: fresh VPS
        if ($isProvisionMode) {
            $rules['fqdn'] = ['required', 'string', 'max:255']; // FQDN required for provisioning
            $rules['release_zip_url'] = ['nullable', 'url', 'max:2048'];
            $rules['ssh_port'] = ['nullable', 'integer', 'min:1', 'max:65535'];
            $rules['ssh_public_key'] = ['required', 'string'];
            $rules['ssh_private_key'] = ['required', 'string'];

            // HestiaCP install options - all booleans except port/lang/versions
            $rules['install_port'] = ['nullable', 'integer', 'min:1', 'max:65535'];
            $rules['install_lang'] = ['nullable', 'string', Rule::in(['en', 'de', 'es', 'fr', 'ru', 'pt-br', 'zh-cn'])];
            $rules['install_apache'] = ['nullable', 'boolean'];
            $rules['install_phpfpm'] = ['nullable', 'boolean'];
            $rules['install_multiphp'] = ['nullable', 'boolean'];
            $rules['install_multiphp_versions'] = ['nullable', 'string', 'max:100'];  // "7.4,8.3"
            $rules['install_vsftpd'] = ['nullable', 'boolean'];
            $rules['install_proftpd'] = ['nullable', 'boolean'];
            $rules['install_named'] = ['nullable', 'boolean'];
            $rules['install_mysql'] = ['nullable', 'boolean'];
            $rules['install_mysql8'] = ['nullable', 'boolean'];
            $rules['install_postgresql'] = ['nullable', 'boolean'];
            $rules['install_exim'] = ['nullable', 'boolean'];
            $rules['install_dovecot'] = ['nullable', 'boolean'];
            $rules['install_sieve'] = ['nullable', 'boolean'];
            $rules['install_clamav'] = ['nullable', 'boolean'];
            $rules['install_spamassassin'] = ['nullable', 'boolean'];
            $rules['install_iptables'] = ['nullable', 'boolean'];
            $rules['install_fail2ban'] = ['nullable', 'boolean'];
            $rules['install_quota'] = ['nullable', 'boolean'];
            $rules['install_resourcelimit'] = ['nullable', 'boolean'];
            $rules['install_webterminal'] = ['nullable', 'boolean'];
            $rules['install_api'] = ['nullable', 'boolean'];
            $rules['install_force'] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    protected function definition(): ScaffoldDefinition
    {
        return new ServerDefinition;
    }
}
