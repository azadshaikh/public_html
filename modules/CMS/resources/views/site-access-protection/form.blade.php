<x-auth-layout title="{{ __('general.site_access_protection') }}" :enableFooter="true" :showTitle="true">

    <div class="container d-flex justify-content-center align-items-center">
        <div class="card" style="width: 100%; max-width: 400px;">
            <div class="card-body">
                <h3 class="mb-2">{{ __('general.site_access_protection') }}</h3>
                <p class="text-muted mb-3">
                    {{ setting('site_access_protection_message', setting('password_protected_message', __('general.site_access_protection_description'))) }}
                </p>

                <!-- Session Status -->
                @if (session('status'))
                    <div class="alert alert-success mb-4" role="alert">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger mb-4" role="alert">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form id="site_access_protection_form" method="POST" action="{{ route('site.access.protection.verify') }}" novalidate>
                    @csrf
                    <div class="mb-3">
                        <x-form-elements.password-input
                            id="password"
                            name="password"
                            label="{{ __('general.enter_access_password') }}"
                            inputclass="form-control"
                            placeholder="{{ __('general.enter_password_to_continue') }}"
                            :extra-attributes="['required' => 'required']" />
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100" id="submit-btn">
                        <i class="ri-lock-unlock-line me-2"></i>
                        <span class="btn-text">{{ __('general.verify_password') }}</span>
                    </button>
                </form>

                <div class="mt-4">
                    <small class="text-muted text-center d-block">
                        {{ __('general.site_access_protection_notice') }}
                    </small>
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript for Site Access Protection Form --}}
    <script data-up-execute>
    /**
     * Site Access Protection Form JavaScript
     *
     * Handles form functionality for the site access protection form.
     * Includes submit button loading state and form validation.
     */

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('site_access_protection_form');
        const submitBtn = document.getElementById('submit-btn');
        const btnText = submitBtn?.querySelector('.btn-text');

        if (form && submitBtn && btnText) {
            // Store original button state
            const originalText = btnText.textContent;
            const originalHtml = submitBtn.innerHTML;

            form.addEventListener('submit', function(e) {
                // Check if form is valid
                if (!form.checkValidity()) {
                    // Form is invalid, don't show loading state
                    return;
                }

                // Form is valid, show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="ri-hourglass-2-line-split me-2"></i>Verifying...';
            });

            // Reset button state when validation fails
            form.addEventListener('invalid', function() {
                // Reset button state
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            }, true);
        }
    });
    </script>

</x-auth-layout>
