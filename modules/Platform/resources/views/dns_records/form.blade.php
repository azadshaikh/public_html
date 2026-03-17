{{-- Shared DNS Record Form Fields --}}
<div class="row g-3">
    <!-- Hostname -->
    <div class="col-12">
        <label for="name" class="form-label">Hostname</label>
        <div class="input-group">
            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $domainDnsRecord->name ?? '') }}" placeholder="e.g., www, mail, @" required>
            <span class="input-group-text">.{{ $domain->domain_name }}</span>
            @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="form-text">Use @ for the root domain.</div>
    </div>

    <!-- Record Type -->
    <div class="col-12">
        <label for="type" class="form-label">Record Type</label>
        <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
            @foreach($record_types ?? [] as $type)
            <option value="{{ $type['value'] }}" {{ old('type', $domainDnsRecord->type ?? 0) == $type['value'] ? 'selected' : '' }}>
                {{ $type['label'] }}
            </option>
            @endforeach
        </select>
        @error('type')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <!-- Value -->
    <div class="col-12">
        <label for="value" class="form-label">Value</label>
        <textarea class="form-control @error('value') is-invalid @enderror" id="value" name="value" rows="2" placeholder="Enter record value" required>{{ old('value', $domainDnsRecord->value ?? '') }}</textarea>
        @error('value')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">For A records, enter an IPv4 address. For CNAME, enter a hostname.</div>
    </div>

    <!-- TTL -->
    <div class="col-md-6">
        <label for="ttl" class="form-label">TTL</label>
        <select class="form-select @error('ttl') is-invalid @enderror" id="ttl" name="ttl" required>
            @foreach($dns_ttls ?? [] as $ttl)
            <option value="{{ $ttl['value'] }}" {{ old('ttl', $domainDnsRecord->ttl ?? 3600) == $ttl['value'] ? 'selected' : '' }}>
                {{ $ttl['label'] }}
            </option>
            @endforeach
        </select>
        @error('ttl')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <!-- Priority (for MX, SRV records) -->
    <div class="col-md-6" id="priority-field" style="display: none;">
        <label for="priority" class="form-label">Priority</label>
        <input type="number" class="form-control @error('priority') is-invalid @enderror" id="priority" name="priority" value="{{ old('priority', $domainDnsRecord->priority ?? '') }}" min="0" max="65535" placeholder="10">
        @error('priority')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <div class="form-text">Lower values have higher priority.</div>
    </div>

    <!-- Weight (for SRV records) -->
    <div class="col-md-6" id="weight-field" style="display: none;">
        <label for="weight" class="form-label">Weight</label>
        <input type="number" class="form-control @error('weight') is-invalid @enderror" id="weight" name="weight" value="{{ old('weight', $domainDnsRecord->weight ?? '') }}" min="0" max="65535" placeholder="100">
        @error('weight')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <!-- Port (for SRV records) -->
    <div class="col-md-6" id="port-field" style="display: none;">
        <label for="port" class="form-label">Port</label>
        <input type="number" class="form-control @error('port') is-invalid @enderror" id="port" name="port" value="{{ old('port', $domainDnsRecord->port ?? '') }}" min="0" max="65535" placeholder="443">
        @error('port')
        <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <!-- Disabled toggle -->
    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="disabled" name="disabled" value="1" {{ old('disabled', $domainDnsRecord->disabled ?? 0) ? 'checked' : '' }}>
            <label class="form-check-label" for="disabled">Disabled</label>
        </div>
        <div class="form-text">When disabled, this record will not be active.</div>
    </div>
</div>

<script data-up-execute>
(() => {
    const typeSelect = document.getElementById('type');
    const priorityField = document.getElementById('priority-field');
    const weightField = document.getElementById('weight-field');
    const portField = document.getElementById('port-field');
    if (!typeSelect) return;
    if (typeSelect.dataset.formInit === '1') return;
    typeSelect.dataset.formInit = '1';

    // DNS Type constants (must match service)
    const TYPE_MX = 4;
    const TYPE_SRV = 8;

    function toggleExtraFields() {
        const selectedType = parseInt(typeSelect.value);

        // Priority: shown for MX and SRV
        if (priorityField) {
            priorityField.style.display = (selectedType === TYPE_MX || selectedType === TYPE_SRV) ? 'block' : 'none';
        }

        // Weight and Port: shown only for SRV
        if (weightField) {
            weightField.style.display = selectedType === TYPE_SRV ? 'block' : 'none';
        }
        if (portField) {
            portField.style.display = selectedType === TYPE_SRV ? 'block' : 'none';
        }
    }

    if (typeSelect) {
        typeSelect.addEventListener('change', toggleExtraFields);
        toggleExtraFields(); // Initial state
    }
})();
</script>
