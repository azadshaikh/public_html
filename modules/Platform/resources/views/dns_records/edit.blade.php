{{-- Edit DNS Record Drawer/Offcanvas Content --}}
<div class="offcanvas-header border-bottom">
    <h5 class="offcanvas-title" id="dnsRecordDrawerLabel">Edit DNS Record</h5>
    <button class="btn-close" data-bs-dismiss="offcanvas" type="button" aria-label="Close"></button>
</div>

<form data-dirty-form class="d-flex flex-column h-100" id="dns-record-form" action="{{ route('platform.dns.update', $domainDnsRecord->id) }}" method="POST" novalidate>
    <div class="offcanvas-body">
        @csrf
        @method('PUT')
        <input name="id" type="hidden" value="{{ $domainDnsRecord->id }}">
        <input name="domain_id" type="hidden" value="{{ $domain_id }}">

        @include('platform::dns_records.form')
    </div>

    <div class="offcanvas-footer border-top p-3">
        <div class="d-grid gap-2">
            <button class="btn btn-primary" type="submit" id="dns-submit-btn">
                <i class="ri-save-line me-2"></i>
                <span class="btn-text">Update Record</span>
            </button>
            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="offcanvas">
                Cancel
            </button>
        </div>
    </div>
</form>

<script data-up-execute>
(() => {
    const form = document.getElementById('dns-record-form');
    const submitBtn = document.getElementById('dns-submit-btn');
    if (!form || !submitBtn) return;
    if (form.dataset.submitInit === '1') return;
    form.dataset.submitInit = '1';

    form.addEventListener('submit', function(e) {
        if (form.checkValidity()) {
            submitBtn.disabled = true;
            const btnText = submitBtn.querySelector('.btn-text');
            if (btnText) btnText.textContent = 'Saving...';
        } else {
            e.preventDefault();
            e.stopPropagation();
        }
        form.classList.add('was-validated');
    });
})();
</script>
