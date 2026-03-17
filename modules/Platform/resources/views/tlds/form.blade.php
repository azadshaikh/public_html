<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-body p-4">
                <h5 class="card-title mb-4">TLD Details</h5>

                <div class="row g-3">
                    <div class="col-md-6">
                        <x-form-elements.input name="tld" label="TLD" :value="$tld->tld ?? ''" required placeholder=".com" />
                    </div>
                    <div class="col-md-6">
                        <x-form-elements.input name="whois_server" label="Whois Server" :value="$tld->whois_server ?? ''" placeholder="whois.verisign-grs.com" />
                    </div>
                    <div class="col-md-6">
                        <x-form-elements.input name="price" label="Price" type="number" step="0.01" :value="$tld->price ?? ''" required />
                    </div>
                    <div class="col-md-6">
                        <x-form-elements.input name="sale_price" label="Sale Price" type="number" step="0.01" :value="$tld->sale_price ?? ''" />
                    </div>
                    <div class="col-12">
                        <x-form-elements.input name="affiliate_link" label="Affiliate Link" :value="$tld->affiliate_link ?? ''" />
                    </div>
                    <div class="col-12">
                        <x-form-elements.input name="pattern" label="Pattern" :value="$tld->pattern ?? ''" />
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body p-4">
                <h5 class="card-title mb-4">Settings</h5>

                <div class="d-flex flex-column gap-3">
                    <x-form-elements.switch-input name="status" label="Active" :checked="$tld->status ?? true" />
                    <x-form-elements.switch-input name="is_main" label="Main TLD" :checked="$tld->is_main ?? false" />
                    <x-form-elements.switch-input name="is_suggested" label="Suggested" :checked="$tld->is_suggested ?? false" />

                    <x-form-elements.input name="tld_order" label="Order" type="number" :value="$tld->tld_order ?? 0" />
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('platform.tlds.index', 'all') }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary btn-lg" id="submit-btn">
                <i class="ri-save-line me-2"></i>
                <span class="btn-text">Save TLD</span>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script data-up-execute>
(() => {
    const form = document.querySelector('form.needs-validation') || document.getElementById('tld-form');
    const submitBtn = document.getElementById('submit-btn');

    if (form && submitBtn) {
        if (form.dataset.submitInit === '1') return;
        form.dataset.submitInit = '1';

        const originalBtnText = submitBtn.querySelector('.btn-text')?.textContent || 'Save TLD';
        const originalIconClass = submitBtn.querySelector('i')?.className || 'ri-save-line me-2';

        form.addEventListener('submit', function(e) {
            // Always show loading state first
            submitBtn.disabled = true;
            const btnText = submitBtn.querySelector('.btn-text');
            const btnIcon = submitBtn.querySelector('i');
            if (btnText) btnText.textContent = 'Processing...';
            if (btnIcon) btnIcon.className = 'ri-hourglass-2-line me-2';

            // If validation fails, reset button after a short delay
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
                form.classList.add('was-validated');

                setTimeout(function() {
                    submitBtn.disabled = false;
                    if (btnText) btnText.textContent = originalBtnText;
                    if (btnIcon) btnIcon.className = originalIconClass;
                }, 100);
            }
        });
    }
})();
</script>
@endpush
