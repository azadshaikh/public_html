{{-- Shared Domain Form --}}

{{-- Dismissable Alert Container --}}
<div id="domain-form-alert" class="mb-4" style="display: none;"></div>

<div class="row g-4">
    <!-- Main Content Column -->
    <div class="col-lg-8">
        <!-- Domain Lookup Card (Create Mode Only) -->
        @if(!isset($domain) || !$domain->exists)
        <div class="card mb-4" id="domain-lookup-card">
            <div class="card-header">
                <h6>Domain Lookup</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="domain_lookup_input" class="form-label">Enter Domain Name</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="domain_lookup_input"
                                placeholder="example.com" autocomplete="off"
                                value="{{ old('domain_name', '') }}">
                            <button type="button" class="btn btn-primary" id="lookup-domain-btn">
                                <i class="ri-search-eye-line me-1"></i> Lookup Domain
                            </button>
                        </div>
                        <div class="form-text">Enter the domain name to check availability and fetch WHOIS data automatically.</div>
                        <div id="domain-lookup-feedback" class="mt-2" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Domain Information Card -->
        <div class="card mb-4" id="domain-info-card" @if(!isset($domain) || !$domain->exists) style="display: none;" @endif>
            <div class="card-header">
                <h6>Domain Information</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="domain_name" class="form-label">Domain Name</label>
                        <input type="text" class="form-control @error('domain_name') is-invalid @enderror" id="domain_name" name="domain_name" value="{{ old('domain_name', $domain->domain_name ?? '') }}" placeholder="example.com" readonly required>
                        @error('domain_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please provide a domain name.</div>
                        @enderror
                        <div class="form-text">The domain name to register or manage.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="agency_id" class="form-label">Agency</label>
                        <select class="form-select @error('agency_id') is-invalid @enderror" id="agency_id" name="agency_id">
                            <option value="">Select Agency</option>
                            @foreach($agencies ?? [] as $agency)
                            <option value="{{ $agency['value'] }}" {{ old('agency_id', $domain->agency_id ?? '') == $agency['value'] ? 'selected' : '' }}>
                                {{ $agency['label'] }}
                            </option>
                            @endforeach
                        </select>
                        @error('agency_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="type" class="form-label">Type</label>
                        <select class="form-select @error('type') is-invalid @enderror" id="type" name="type">
                            <option value="">Select Type</option>
                            @foreach($types ?? [] as $type)
                            <option value="{{ $type['value'] }}" {{ old('type', $domain->type ?? '') == $type['value'] ? 'selected' : '' }}>
                                {{ $type['label'] }}
                            </option>
                            @endforeach
                        </select>
                        @error('type')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="registrar_id" class="form-label">Domain Registrar</label>
                        <select class="form-select @error('registrar_id') is-invalid @enderror" id="registrar_id" name="registrar_id">
                            <option value="">Select Registrar</option>
                            @foreach($registrars ?? [] as $registrar)
                            <option value="{{ $registrar['value'] }}" {{ old('registrar_id', $domain->primaryDomainRegistrar?->first()?->id ?? '') == $registrar['value'] ? 'selected' : '' }}>
                                {{ $registrar['label'] }}
                            </option>
                            @endforeach
                        </select>
                        @error('registrar_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please select a registrar.</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- WHOIS & Dates Card -->
        <div class="card mb-4" id="whois-dates-card" @if(!isset($domain) || !$domain->exists) style="display: none;" @endif>
            <div class="card-header d-flex justify-content-between align-items-center mb-3">
                <h6>Registration Dates</h6>
                @if(!isset($domain) || !$domain->exists)
                <button type="button" class="btn btn-sm btn-outline-info" id="fetch-whois-btn">
                    <i class="ri-refresh-line me-1"></i> Re-fetch WHOIS
                </button>
                @endif
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <x-form-elements.datepicker
                            class="form-group"
                            id="registered_date"
                            name="registered_date"
                            label="Registered On"
                            labelclass="form-label"
                            inputclass="form-control"
                            mode="date"
                            value="{{ old('registered_date', optional($domain->registered_date)->format('Y-m-d')) }}"
                            placeholder="Select date" />
                    </div>
                    <div class="col-md-4">
                        <x-form-elements.datepicker
                            class="form-group"
                            id="expires_date"
                            name="expires_date"
                            label="Expires On"
                            labelclass="form-label"
                            inputclass="form-control"
                            mode="date"
                            value="{{ old('expires_date', optional($domain->expiry_date)->format('Y-m-d')) }}"
                            placeholder="Select date" />
                    </div>
                    <div class="col-md-4">
                        <x-form-elements.datepicker
                            class="form-group"
                            id="updated_date"
                            name="updated_date"
                            label="Updated On"
                            labelclass="form-label"
                            inputclass="form-control"
                            mode="date"
                            value="{{ old('updated_date', optional($domain->updated_date)->format('Y-m-d')) }}"
                            placeholder="Select date" />
                    </div>
                    <div class="col-12">
                        <label for="registrar_name" class="form-label">Registrar Name (from WHOIS)</label>
                        <input type="text" class="form-control @error('registrar_name') is-invalid @enderror" id="registrar_name" name="registrar_name" value="{{ old('registrar_name', $domain->registrar_name ?? '') }}" placeholder="Auto-populated from WHOIS">
                        @error('registrar_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">This is the registrar name as returned by WHOIS lookup.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Name Servers Card -->
        <div class="card mb-4" id="name-servers-card" @if(!isset($domain) || !$domain->exists) style="display: none;" @endif>
            <div class="card-header">
                <h6>Name Servers</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="domain_name_server_1" class="form-label">Name Server 1</label>
                        <input type="text" class="form-control @error('domain_name_server_1') is-invalid @enderror" id="domain_name_server_1" name="domain_name_server_1" value="{{ old('domain_name_server_1', $domain->name_server_1 ?? '') }}" placeholder="ns1.example.com">
                        @error('domain_name_server_1')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="domain_name_server_2" class="form-label">Name Server 2</label>
                        <input type="text" class="form-control @error('domain_name_server_2') is-invalid @enderror" id="domain_name_server_2" name="domain_name_server_2" value="{{ old('domain_name_server_2', $domain->name_server_2 ?? '') }}" placeholder="ns2.example.com">
                        @error('domain_name_server_2')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="domain_name_server_3" class="form-label">Name Server 3</label>
                        <input type="text" class="form-control @error('domain_name_server_3') is-invalid @enderror" id="domain_name_server_3" name="domain_name_server_3" value="{{ old('domain_name_server_3', $domain->name_server_3 ?? '') }}" placeholder="ns3.example.com">
                        @error('domain_name_server_3')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="domain_name_server_4" class="form-label">Name Server 4</label>
                        <input type="text" class="form-control @error('domain_name_server_4') is-invalid @enderror" id="domain_name_server_4" name="domain_name_server_4" value="{{ old('domain_name_server_4', $domain->name_server_4 ?? '') }}" placeholder="ns4.example.com">
                        @error('domain_name_server_4')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <!-- DNS Zone Card -->
        <div class="card mb-4" id="dns-config-card" @if(!isset($domain) || !$domain->exists) style="display: none;" @endif>
            <div class="card-header">
                <h6>DNS Configuration</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="dns_provider" class="form-label">DNS Provider</label>
                        <input type="text" class="form-control @error('dns_provider') is-invalid @enderror" id="dns_provider" name="dns_provider" value="{{ old('dns_provider', $domain->dns_provider ?? '') }}" placeholder="e.g., Cloudflare, Route53">
                        @error('dns_provider')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="dns_zone_id" class="form-label">DNS Zone ID</label>
                        <input type="text" class="form-control @error('dns_zone_id') is-invalid @enderror" id="dns_zone_id" name="dns_zone_id" value="{{ old('dns_zone_id', $domain->dns_zone_id ?? '') }}" placeholder="Zone ID from provider">
                        @error('dns_zone_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar Column -->
    <div class="col-lg-4" id="sidebar-column" @if(!isset($domain) || !$domain->exists) style="display: none;" @endif>
        <!-- Status Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h6>Domain Status</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                            @foreach($statusOptions ?? [] as $option)
                            <option value="{{ $option['value'] }}" {{ old('status', $domain->status_id ?? $domain->status ?? 1) == $option['value'] ? 'selected' : '' }}>
                                {{ $option['label'] }}
                            </option>
                            @endforeach
                        </select>
                        @error('status')
                        <div class="invalid-feedback">{{ $message }}</div>
                        @else
                        <div class="invalid-feedback">Please select a status.</div>
                        @enderror
                        <div class="form-text">{{ isset($domain) && $domain->exists ? 'Current status for this domain.' : 'Initial status for this domain.' }}</div>
                    </div>
                </div>
            </div>
        </div>

        @if(isset($domain) && $domain->exists)
        <!-- Domain Details Card (Edit Only) -->
        <div class="card mb-4">
            <div class="card-header">
                <h6>Domain Details</h6>
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Created:</dt>
                    <dd class="col-sm-7">{{ $domain->created_at?->format('M d, Y g:i A') ?? 'N/A' }}</dd>

                    <dt class="col-sm-5">Updated:</dt>
                    <dd class="col-sm-7">{{ $domain->updated_at?->format('M d, Y g:i A') ?? 'N/A' }}</dd>

                    @if($domain->created_by)
                    <dt class="col-sm-5">Created By:</dt>
                    <dd class="col-sm-7">{{ $domain->createdBy->name ?? 'Unknown' }}</dd>
                    @endif

                    @if($domain->updated_by)
                    <dt class="col-sm-5">Updated By:</dt>
                    <dd class="col-sm-7">{{ $domain->updatedBy->name ?? 'Unknown' }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        <!-- Related Information Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h6>Quick Links</h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="{{ route('platform.domains.show', $domain) }}#ssl" class="btn btn-outline-secondary btn-sm">
                        <i class="ri-shield-keyhole-line me-1"></i> Manage SSL Certificates
                    </a>
                    <a href="{{ route('platform.dns.index', ['domain_id' => $domain->id]) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="ri-server-line me-1"></i> Manage DNS Records
                    </a>
                </div>
            </div>
        </div>
        @endif

        <!-- Submit Actions Card -->
        <div class="card">
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-lg" type="submit" id="submit-btn">
                        <i class="{{ $formConfig['submitIcon'] ?? 'ri-save-line' }} me-2"></i>
                        <span class="btn-text">{{ $formConfig['submitText'] ?? (isset($domain) ? 'Update Domain' : 'Create Domain') }}</span>
                    </button>
                    <a class="btn btn-outline-secondary" href="{{ $formConfig['cancelUrl'] ?? route('platform.domains.index') }}">
                        <i class="ri-arrow-left-line me-2"></i>Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script data-up-execute>
(() => {
    // Elements
    const lookupDomainBtn = document.getElementById('lookup-domain-btn');
    const domainLookupInput = document.getElementById('domain_lookup_input');
    const domainNameInput = document.getElementById('domain_name');
    const domainLookupCard = document.getElementById('domain-lookup-card');
    const domainLookupFeedback = document.getElementById('domain-lookup-feedback');
    const fetchWhoisBtn = document.getElementById('fetch-whois-btn');
    if (domainNameInput && domainNameInput.dataset.formInit === '1') return;
    if (domainNameInput) domainNameInput.dataset.formInit = '1';

    // Cards to show after lookup
    const formCards = [
        document.getElementById('domain-info-card'),
        document.getElementById('whois-dates-card'),
        document.getElementById('name-servers-card'),
        document.getElementById('dns-config-card'),
        document.getElementById('sidebar-column')
    ];

    /**
     * Show form cards after successful domain lookup
     */
    function showFormCards() {
        formCards.forEach(card => {
            if (card) {
                card.style.display = '';
            }
        });
    }

    /**
     * Populate form fields from WHOIS data
     */
    function populateWhoisData(whoisData) {
        if (!whoisData || !whoisData.success) return;

        // Dates — target the hidden input (by name) so Alpine's datepicker component
        // picks up the change via its hiddenInput 'change' listener and updates state.
        function setDatepickerValue(name, value) {
            if (!value) return;
            const hidden = document.querySelector(`input[type="hidden"][name="${name}"]`);
            if (hidden) {
                hidden.value = value;
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        setDatepickerValue('registered_date', whoisData.registered_on);
        setDatepickerValue('expires_date', whoisData.expires_on);
        setDatepickerValue('updated_date', whoisData.updated_on);

        // Registrar name
        if (whoisData.domain_registrar) {
            const regName = document.getElementById('registrar_name');
            if (regName) regName.value = whoisData.domain_registrar;
        }

        // Name servers
        if (whoisData.name_server_1) {
            const ns1 = document.getElementById('domain_name_server_1');
            if (ns1) ns1.value = whoisData.name_server_1;
        }
        if (whoisData.name_server_2) {
            const ns2 = document.getElementById('domain_name_server_2');
            if (ns2) ns2.value = whoisData.name_server_2;
        }
        if (whoisData.name_server_3) {
            const ns3 = document.getElementById('domain_name_server_3');
            if (ns3) ns3.value = whoisData.name_server_3;
        }
        if (whoisData.name_server_4) {
            const ns4 = document.getElementById('domain_name_server_4');
            if (ns4) ns4.value = whoisData.name_server_4;
        }
    }

    /**
     * Show feedback message
     */
    function showFeedback(message, type = 'error') {
        if (!domainLookupFeedback) return;

        const alertClass = type === 'success' ? 'alert-success' : (type === 'warning' ? 'alert-warning' : 'alert-danger');
        const iconClass = type === 'success' ? 'ri-check-line' : (type === 'warning' ? 'ri-alert-line' : 'ri-error-warning-line');

        domainLookupFeedback.innerHTML = `
            <div class="alert ${alertClass} mb-0 py-2" role="alert">
                <i class="${iconClass} me-1"></i> ${message}
            </div>
        `;
        domainLookupFeedback.style.display = 'block';
    }

    /**
     * Hide feedback message
     */
    function hideFeedback() {
        if (domainLookupFeedback) {
            domainLookupFeedback.style.display = 'none';
        }
    }

    /**
     * Show dismissable alert at the top of the form
     */
    function showDismissableAlert(type, title, message) {
        const alertContainer = document.getElementById('domain-form-alert');
        if (!alertContainer) return;

        const alertClasses = {
            'success': { bg: 'alert-success', icon: 'ri-check-line' },
            'warning': { bg: 'alert-warning', icon: 'ri-alert-line' },
            'danger': { bg: 'alert-danger', icon: 'ri-error-warning-line' },
            'info': { bg: 'alert-info', icon: 'ri-information-line' }
        };

        const config = alertClasses[type] || alertClasses['info'];

        alertContainer.innerHTML = `
            <div class="alert ${config.bg} alert-dismissible fade show rounded-4" role="alert">
                <div class="d-flex align-items-start">
                    <div class="alert-icon me-3 flex-shrink-0">
                        <i class="${config.icon}" style="font-size: 1.25rem;"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h5 class="fw-semibold mb-2">${title}</h5>
                        <p class="mb-0">${message}</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        alertContainer.style.display = 'block';

        // Scroll to the alert
        alertContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /**
     * Handle domain lookup
     */
    if (lookupDomainBtn && domainLookupInput) {
        // Allow Enter key to trigger lookup
        domainLookupInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                lookupDomainBtn.click();
            }
        });

        lookupDomainBtn.addEventListener('click', async function() {
            const domainName = domainLookupInput.value.trim();

            if (!domainName) {
                showFeedback('Please enter a domain name');
                domainLookupInput.focus();
                return;
            }

            // Validate domain format
            const domainRegex = /^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$/;
            const cleanDomain = domainName.replace(/^(https?:\/\/)?(www\.)?/, '').split('/')[0];

            if (!domainRegex.test(cleanDomain)) {
                showFeedback('Please enter a valid domain name (e.g., example.com)');
                return;
            }

            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Looking up...';
            hideFeedback();

            try {
                const response = await fetch("{{ route('platform.domains.lookupDomain') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ domain_name: domainName })
                });

                const result = await response.json();

                if (result.exists) {
                    // Domain already exists in database
                    if (result.status === 'trashed') {
                        showFeedback(
                            `${result.message} <a href="#" class="alert-link" onclick="window.location.href='{{ route('platform.domains.index', 'trash') }}'; return false;">View Trash</a>`,
                            'warning'
                        );
                    } else {
                        showFeedback(
                            `${result.message} <a href="{{ url(config('app.admin_slug')) }}/domain/domains/${result.domain_id}/edit" class="alert-link">Edit existing domain</a>`,
                            'warning'
                        );
                    }
                    return;
                }

                if (result.success) {
                    // Set the domain name in the hidden input
                    if (domainNameInput) {
                        domainNameInput.value = result.domain_name;
                    }

                    // Hide lookup card and show form
                    if (domainLookupCard) {
                        domainLookupCard.style.display = 'none';
                    }
                    showFormCards();

                    // Populate WHOIS data if available
                    if (result.whois) {
                        populateWhoisData(result.whois);

                        if (result.whois.success) {
                            if (typeof ToastSystem !== 'undefined') {
                                ToastSystem.success('Domain verified and WHOIS data fetched successfully');
                            }
                        } else if (result.whois.domain_not_registered) {
                            showDismissableAlert('warning', 'Domain Not Registered', 'This domain appears to be not registered. WHOIS data could not be retrieved. Please fill in all details manually.');
                            if (typeof ToastSystem !== 'undefined') {
                                ToastSystem.warning('Domain appears to be not registered. Please fill in details manually.');
                            }
                        } else if (result.whois.extNotValid) {
                            showDismissableAlert('info', 'TLD Not Supported', 'The TLD for this domain is not supported for WHOIS lookup. Please fill in all details manually.');
                            if (typeof ToastSystem !== 'undefined') {
                                ToastSystem.warning('TLD not supported for WHOIS lookup. Please fill in details manually.');
                            }
                        } else {
                            showDismissableAlert('info', 'WHOIS Unavailable', 'Could not fetch WHOIS data for this domain. Please fill in all details manually.');
                            if (typeof ToastSystem !== 'undefined') {
                                ToastSystem.info('Could not fetch WHOIS data. Please fill in details manually.');
                            }
                        }
                    }
                } else {
                    showFeedback(result.message || 'Failed to lookup domain');
                }
            } catch (error) {
                console.error('Domain lookup error:', error);
                showFeedback('An error occurred while looking up the domain. Please try again.');
            } finally {
                this.disabled = false;
                this.innerHTML = originalText;
            }
        });
    }

    /**
     * Handle re-fetch WHOIS button (for when form is already shown)
     */
    if (fetchWhoisBtn && domainNameInput) {
        fetchWhoisBtn.addEventListener('click', async function() {
            const domainName = domainNameInput.value.trim();

            if (!domainName) {
                if (typeof ToastSystem !== 'undefined') {
                    ToastSystem.error('No domain name to lookup');
                }
                return;
            }

            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Fetching...';

            try {
                const response = await fetch("{{ route('platform.domains.getWhoisData') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ url: domainName })
                });

                const result = await response.json();
                populateWhoisData(result);

                if (result.success) {
                    if (typeof ToastSystem !== 'undefined') {
                        ToastSystem.success('WHOIS data refreshed successfully');
                    }
                } else {
                    if (typeof ToastSystem !== 'undefined') {
                        ToastSystem.warning('Could not fetch WHOIS data');
                    }
                }
            } catch (error) {
                console.error('WHOIS fetch error:', error);
                if (typeof ToastSystem !== 'undefined') {
                    ToastSystem.error('Failed to fetch WHOIS data');
                }
            } finally {
                this.disabled = false;
                this.innerHTML = originalText;
            }
        });
    }

    // If there's already a domain name value (e.g., from validation errors), show the form
    @if(old('domain_name'))
    if (domainLookupCard) {
        domainLookupCard.style.display = 'none';
    }
    showFormCards();
    @endif
})();
</script>
@endpush
