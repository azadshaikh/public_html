{{-- Provision Mode Form - Install HestiaCP on Fresh VPS --}}
@php
    $server = $server ?? new \Modules\Platform\Models\Server();
    $selectedProviderId = old('provider_id');
    if ($selectedProviderId === null && $server->exists) {
        $selectedProviderId = $server->provider?->id;
    }
@endphp

<div class="row g-4">
    <div class="col-lg-8">
        {{-- SSH Command Section --}}
        <div class="card mb-4 border-warning">
            <div class="card-header">
                <h6 class="mb-3"><i class="ri-terminal-box-line me-1"></i> Step 1: Authorize SSH Access</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-warning small mb-3">
                    <i class="ri-information-line me-1"></i>
                    Your server needs a fresh installation of Ubuntu (22.04/24.04) or Debian (11/12) and must have a root user.
                    Run the following command on your server to authorize SSH access:
                </div>

                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label for="ssh-command" class="form-label mb-0">Run this command on your server as root user</label>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="copy-ssh-command-btn" onclick="copySSHCommand(this)">
                        <i class="ri-file-copy-line me-1"></i> Copy
                    </button>
                </div>
                <textarea id="ssh-command" class="form-control font-monospace bg-light" rows="5" readonly
                    x-text="sshCommand">{{ $sshCommand ?? '' }}</textarea>
            </div>
        </div>

        {{-- Server Details --}}
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="ri-server-line me-1"></i> Step 2: Server Details</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">Server Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror"
                            id="name" name="name"
                            value="{{ old('name', $server->name ?? '') }}"
                            placeholder="e.g., Production Server 1" required>
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="invalid-feedback">Please provide a server name.</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="provision_ip" class="form-label">IP Address <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('ip') is-invalid @enderror"
                            id="provision_ip" name="ip" value="{{ old('ip', $server->ip ?? '') }}"
                            placeholder="e.g., 192.168.1.100" required>
                        @error('ip')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="invalid-feedback">Please provide an IP address.</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="provision_ssh_port" class="form-label">SSH Port</label>
                        <input type="number" class="form-control @error('ssh_port') is-invalid @enderror"
                            id="provision_ssh_port" name="ssh_port"
                            value="{{ old('ssh_port', '22') }}" placeholder="22">
                        @error('ssh_port')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="fqdn" class="form-label">Hostname (FQDN) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('fqdn') is-invalid @enderror"
                            id="fqdn" name="fqdn"
                            value="{{ old('fqdn', $server->fqdn ?? '') }}"
                            placeholder="e.g., server1.example.com" required>
                        <small class="text-muted">The fully qualified domain name for your server.</small>
                        @error('fqdn')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @else
                            <div class="invalid-feedback">Please provide a hostname (FQDN).</div>
                        @enderror
                    </div>
                    <div class="col-12 mb-0">
                        @php
                            $releaseApiKeyValue = old('release_api_key', '');
                            $releaseZipUrlValue = old('release_zip_url', (string) ($server->getMetadata('release_zip_url') ?? ''));
                        @endphp
                        <x-form-elements.input
                            id="release_zip_url"
                            name="release_zip_url"
                            label="Release ZIP URL"
                            type="url"
                            labelclass="form-label"
                            inputclass="form-control"
                            :value="$releaseZipUrlValue"
                            placeholder="https://example.com/releases/latest.zip"
                            infotext="Optional. If provided, provisioning sets up releases directly via SSH from this ZIP URL (without using HestiaCP API for release sync)." />

                        <x-form-elements.password-input
                            id="release_api_key"
                            name="release_api_key"
                            label="Release API Key"
                            labelclass="form-label"
                            inputclass="form-control"
                            :value="$releaseApiKeyValue"
                            placeholder="X-Release-Key used by a-sync-releases"
                            infotext="Optional. Leave blank to use RELEASE_API_KEY from the provisioning server environment." />
                    </div>
                </div>
            </div>
        </div>

        {{-- HestiaCP Installation Options --}}
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="ri-settings-3-line me-1"></i> Step 3: HestiaCP Options</h6>
                <small class="text-muted">Configure what will be installed on your server</small>
            </div>
            <div class="card-body" x-data="hestiaCPOptions()">
                <div class="row g-3">

                    {{-- Row 1: Port, Language, Hostname --}}
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.port.on ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_port" x-model="opts.port.on">
                                    <label class="form-check-label fw-medium" for="opt_port">Port</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Change the port Hestia uses"></i>
                            </div>
                            <template x-if="opts.port.on">
                                <div class="mt-2">
                                    <small class="text-muted d-block mb-1">Change the port Hestia uses</small>
                                    <input type="number" class="form-control form-control-sm" name="install_port" x-model="opts.port.val" placeholder="8443">
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.lang.on ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_lang" x-model="opts.lang.on">
                                    <label class="form-check-label fw-medium" for="opt_lang">Language</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Change the interface language"></i>
                            </div>
                            <template x-if="opts.lang.on">
                                <div class="mt-2">
                                    <small class="text-muted d-block mb-1">Change the interface language</small>
                                    <select class="form-select form-select-sm" name="install_lang" x-model="opts.lang.val">
                                        <option value="en">English</option>
                                        <option value="de">German</option>
                                        <option value="es">Spanish</option>
                                        <option value="fr">French</option>
                                        <option value="ru">Russian</option>
                                    </select>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border border-muted bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check"><input class="form-check-input" type="checkbox" disabled checked>
                                    <label class="form-check-label fw-medium text-muted">Hostname</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Set from FQDN field above"></i>
                            </div>
                            <small class="text-muted mt-2 d-block">Uses FQDN from Step 2</small>
                        </div>
                    </div>

                    {{-- Row 2: Username, Email, Password (Fixed) --}}
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border border-muted bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check"><input class="form-check-input" type="checkbox" disabled checked>
                                    <label class="form-check-label fw-medium text-muted">Username</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Admin username (fixed)"></i>
                            </div>
                            <small class="text-muted mt-2 d-block">adminxastero</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border border-muted bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check"><input class="form-check-input" type="checkbox" disabled checked>
                                    <label class="form-check-label fw-medium text-muted">Email</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Admin email (fixed)"></i>
                            </div>
                            <small class="text-muted mt-2 d-block">hestia@astero.net.in</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border border-muted bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check"><input class="form-check-input" type="checkbox" disabled checked>
                                    <label class="form-check-label fw-medium text-muted">Password</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Auto-generated secure password"></i>
                            </div>
                            <small class="text-muted mt-2 d-block">Auto-generated (secure)</small>
                        </div>
                    </div>

                    {{-- Row 3: Apache, PHP-FPM, MultiPHP --}}
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.apache ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_apache" name="install_apache" value="1" x-model="opts.apache">
                                    <label class="form-check-label fw-medium" for="opt_apache">Apache</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Install Apache (NGINX always included)"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.phpfpm ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_phpfpm" name="install_phpfpm" value="1" x-model="opts.phpfpm">
                                    <label class="form-check-label fw-medium" for="opt_phpfpm">PHP-FPM</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Install PHP-FPM"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.multiphp.on ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_multiphp" x-model="opts.multiphp.on">
                                    <label class="form-check-label fw-medium" for="opt_multiphp">MultiPHP</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Install multiple PHP versions"></i>
                            </div>
                            <template x-if="opts.multiphp.on">
                                <div class="mt-2">
                                    <small class="text-muted d-block mb-1">Eg: 8.3,8.4</small>
                                    <input type="text" class="form-control form-control-sm" name="install_multiphp_versions" x-model="opts.multiphp.val" placeholder="8.3,8.4">
                                    <input type="hidden" name="install_multiphp" value="1">
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Row 4: VSFTPD, ProFTPD, BIND --}}
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.vsftpd ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_vsftpd" name="install_vsftpd" value="1" x-model="opts.vsftpd">
                                    <label class="form-check-label fw-medium" for="opt_vsftpd">VSFTPD</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Install VSFTPD FTP server"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.proftpd ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_proftpd" name="install_proftpd" value="1" x-model="opts.proftpd">
                                    <label class="form-check-label fw-medium" for="opt_proftpd">ProFTPD</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Install ProFTPD FTP server"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.named ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_named" name="install_named" value="1" x-model="opts.named">
                                    <label class="form-check-label fw-medium" for="opt_named">BIND</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Install BIND DNS server"></i>
                            </div>
                        </div>
                    </div>

                    {{-- Row 5: MariaDB, MySQL 8, PostgreSQL --}}
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.mysql ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_mysql" name="install_mysql" value="1" x-model="opts.mysql">
                                    <label class="form-check-label fw-medium" for="opt_mysql">MariaDB</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Install MariaDB database"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.mysql8 ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_mysql8" name="install_mysql8" value="1" x-model="opts.mysql8">
                                    <label class="form-check-label fw-medium" for="opt_mysql8">MySQL 8</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Install MySQL 8 (instead of MariaDB)"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.postgresql ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_postgresql" name="install_postgresql" value="1" x-model="opts.postgresql">
                                    <label class="form-check-label fw-medium" for="opt_postgresql">PostgreSQL</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Install PostgreSQL database"></i>
                            </div>
                        </div>
                    </div>

                    {{-- Row 6: Exim, Dovecot, Sieve --}}
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.exim ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_exim" name="install_exim" value="1" x-model="opts.exim">
                                    <label class="form-check-label fw-medium" for="opt_exim">Exim</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Install Exim mail server"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.dovecot ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_dovecot" name="install_dovecot" value="1" x-model="opts.dovecot">
                                    <label class="form-check-label fw-medium" for="opt_dovecot">Dovecot</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Install Dovecot IMAP/POP3"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.sieve ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_sieve" name="install_sieve" value="1" x-model="opts.sieve">
                                    <label class="form-check-label fw-medium" for="opt_sieve">Sieve</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Install Sieve mail filtering"></i>
                            </div>
                        </div>
                    </div>

                    {{-- Row 7: ClamAV, SpamAssassin, iptables --}}
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.clamav ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_clamav" name="install_clamav" value="1" x-model="opts.clamav">
                                    <label class="form-check-label fw-medium" for="opt_clamav">ClamAV</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Install ClamAV antivirus"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.spamassassin ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_spamassassin" name="install_spamassassin" value="1" x-model="opts.spamassassin">
                                    <label class="form-check-label fw-medium" for="opt_spamassassin">SpamAssassin</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Install SpamAssassin spam filter"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.iptables ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_iptables" name="install_iptables" value="1" x-model="opts.iptables">
                                    <label class="form-check-label fw-medium" for="opt_iptables">iptables</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Install iptables firewall"></i>
                            </div>
                        </div>
                    </div>

                    {{-- Row 8: Fail2Ban, Quota, Web Terminal --}}
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.fail2ban ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_fail2ban" name="install_fail2ban" value="1" x-model="opts.fail2ban">
                                    <label class="form-check-label fw-medium" for="opt_fail2ban">Fail2Ban</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Install Fail2Ban intrusion prevention"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.quota ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_quota" name="install_quota" value="1" x-model="opts.quota">
                                    <label class="form-check-label fw-medium" for="opt_quota">Filesystem quota</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Enable filesystem quotas"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.webterminal ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_webterminal" name="install_webterminal" value="1" x-model="opts.webterminal">
                                    <label class="form-check-label fw-medium" for="opt_webterminal">Web Terminal</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Enable web-based terminal"></i>
                            </div>
                        </div>
                    </div>

                    {{-- Row 9: API, Interactive (disabled), Force --}}
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.api ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_api" name="install_api" value="1" x-model="opts.api">
                                    <label class="form-check-label fw-medium" for="opt_api">Hestia API</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Enable Hestia API access"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border border-muted bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check"><input class="form-check-input" type="checkbox" disabled>
                                    <label class="form-check-label fw-medium text-muted">Interactive install</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Disabled for automated install"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="option-card p-3 rounded border" :class="opts.force ? 'border-primary bg-primary-subtle' : ''">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="opt_force" name="install_force" value="1" x-model="opts.force">
                                    <label class="form-check-label fw-medium" for="opt_force">Force installation</label>
                                </div>
                                <i class="ri-information-line text-muted" data-bs-toggle="tooltip" title="Force install even if checks fail"></i>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Organization</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="type" class="form-label">Server Type <span class="text-danger">*</span></label>
                    <select class="form-select @error('type') is-invalid @enderror" id="type"
                        name="type" required>
                        <option value="">Select Type</option>
                        @foreach ($typeOptions as $option)
                            <option value="{{ $option['value'] }}"
                                {{ old('type', $server->type ?? '') == $option['value'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @else
                        <div class="invalid-feedback">Please select a server type.</div>
                    @enderror
                </div>

                <div class="mb-0">
                    <label for="provider_id" class="form-label">Server Provider <span class="text-danger">*</span></label>
                    <select class="form-select @error('provider_id') is-invalid @enderror" id="provider_id"
                        name="provider_id" required>
                        <option value="">Select Provider</option>
                        @foreach ($providerOptions as $option)
                            <option value="{{ $option['value'] }}"
                                {{ $selectedProviderId == $option['value'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                        @endforeach
                    </select>
                    @error('provider_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @else
                        <div class="invalid-feedback">Please select a server provider.</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0">Connection Status</h6>
            </div>
            <div class="card-body">
                <div id="connection-status" class="text-center py-3">
                    <span class="text-muted">
                        <i class="ri-wifi-off-line me-1"></i> Not connected
                    </span>
                </div>
                <button type="button" class="btn btn-outline-primary w-100" id="verify-connection-btn" onclick="verifyConnection()">
                    <i class="ri-plug-line me-1"></i> Verify Connection
                </button>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-success" type="submit">
                        <i class="ri-install-line me-2"></i>
                        <span class="btn-text">Start Provisioning</span>
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ route('platform.servers.index') }}">
                        <i class="ri-arrow-left-line me-2"></i> Cancel
                    </a>
                </div>
                <div class="small text-muted mt-2 text-center">
                    Provisioning takes approximately 15-30 minutes
                </div>
            </div>
        </div>
    </div>
</div>

<script data-up-execute>
    window.copySSHCommand = async function (button) {
        const command = document.getElementById('ssh-command');
        if (!command) {
            return;
        }

        const originalText = button ? button.innerHTML : '';
        const text = command.value || command.textContent || '';
        if (!command.value && text) {
            command.value = text;
        }
        if (!text.trim()) {
            return;
        }

        const showCopied = () => {
            if (!button) {
                return;
            }
            button.innerHTML = '<i class="ri-check-line me-1"></i> Copied';
            setTimeout(() => button.innerHTML = originalText, 1200);
        };

        const showFailed = () => {
            if (!button) {
                return;
            }
            button.innerHTML = '<i class="ri-error-warning-line me-1"></i> Failed';
            setTimeout(() => button.innerHTML = originalText, 1500);
            if (window.ToastSystem) {
                window.ToastSystem.show({ type: 'error', message: 'Failed to copy' });
            }
        };

        const fallbackCopy = () => {
            command.focus();
            command.select();
            command.setSelectionRange(0, command.value.length);
            let copied = false;
            try {
                copied = document.execCommand('copy');
            } catch (error) {
                copied = false;
            }
            command.setSelectionRange(0, 0);
            return copied;
        };

        if (navigator.clipboard?.writeText) {
            try {
                await navigator.clipboard.writeText(text);
                showCopied();
                return;
            } catch (error) {
                if (fallbackCopy()) {
                    showCopied();
                    return;
                }
                showFailed();
                return;
            }
        }

        if (fallbackCopy()) {
            showCopied();
        } else {
            showFailed();
        }
    };

    window.verifyConnection = async function () {
        const ipInput = document.getElementById('provision_ip');
        const sshPortInput = document.getElementById('provision_ssh_port');
        const statusDiv = document.getElementById('connection-status');
        const btn = document.getElementById('verify-connection-btn');

        const ip = ipInput ? ipInput.value.trim() : '';
        const sshPort = sshPortInput ? sshPortInput.value.trim() || '22' : '22';

        if (!ip) {
            statusDiv.innerHTML = '<span class="text-danger"><i class="ri-error-warning-line me-1"></i> Enter IP address first</span>';
            if (ipInput) ipInput.focus();
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="ri-loader-4-line spin me-1"></i> Verifying...';
        statusDiv.innerHTML = '<span class="text-info"><i class="ri-loader-4-line spin me-1"></i> Connecting...</span>';

        try {
            // Get keys from Alpine component (Alpine 3.x compatible with fallback)
            const wizardEl = document.getElementById('server-wizard');
            let alpine = null;

            if (wizardEl) {
                // Try Alpine 3.x API first
                if (typeof Alpine !== 'undefined' && Alpine.$data) {
                    alpine = Alpine.$data(wizardEl);
                }
                // Fallback to internal data stack
                if (!alpine && wizardEl._x_dataStack) {
                    alpine = wizardEl._x_dataStack[0];
                }
            }

            if (!alpine || !alpine.sshPrivateKey) {
                statusDiv.innerHTML = '<span class="text-danger"><i class="ri-error-warning-line me-1"></i> SSH key not found. Please regenerate.</span>';
                btn.disabled = false;
                btn.innerHTML = '<i class="ri-plug-line me-1"></i> Verify Connection';
                return;
            }

            const response = await fetch('{{ route('platform.servers.verify-connection') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    ip: ip,
                    ssh_port: sshPort,
                    ssh_private_key: alpine.sshPrivateKey
                })
            });

            const data = await response.json();

            if (data.success) {
                statusDiv.innerHTML = '<span class="text-success"><i class="ri-check-double-line me-1"></i> Connected successfully</span>';
                if (data.os_info) {
                    statusDiv.innerHTML += '<br><small class="text-muted">' + data.os_info + '</small>';
                }
            } else {
                statusDiv.innerHTML = '<span class="text-danger"><i class="ri-close-line me-1"></i> ' + (data.message || 'Connection failed') + '</span>';
            }
        } catch (error) {
            statusDiv.innerHTML = '<span class="text-danger"><i class="ri-error-warning-line me-1"></i> ' + error.message + '</span>';
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="ri-plug-line me-1"></i> Verify Connection';
        }
    };

    // Reset status when user starts typing in IP field
    const ipInput = document.getElementById('provision_ip');
    const statusDiv = document.getElementById('connection-status');
    if (ipInput && statusDiv) {
        ipInput.addEventListener('input', function() {
            if (statusDiv.querySelector('.text-danger')) {
                statusDiv.innerHTML = '<span class="text-muted"><i class="ri-wifi-off-line me-1"></i> Not connected</span>';
            }
        });
    }

    // HestiaCP Options Alpine component
    window.hestiaCPOptions = function () {
        return {
            opts: {
                port: { on: true, val: 8443 },
                lang: { on: true, val: 'en' },
                apache: false,
                phpfpm: true,
                multiphp: { on: true, val: '8.4' },
                vsftpd: false,
                proftpd: false,
                named: false,
                mysql: false,
                mysql8: false,
                postgresql: true,
                exim: false,
                dovecot: false,
                sieve: false,
                clamav: false,
                spamassassin: false,
                iptables: true,
                fail2ban: true,
                quota: false,
                resourcelimit: false,
                webterminal: true,
                api: true,
                force: false,
            }
        };
    };
</script>
