<x-auth-layout title="{{ __('general.post_password_protected') }}" :enableFooter="true" :showTitle="true">

    <div class="container d-flex justify-content-center align-items-center">
        <div class="card" style="width: 100%; max-width: 400px;">
            <div class="card-body">
                <h3 class="mb-2">{{ __('general.post_password_protected') }}</h3>
                <p class="text-muted mb-3">
                    {{ $post->password_hint ?? __('general.post_password_protected_description') }}
                </p>

                {{-- Post Info Alert --}}
                <div class="alert alert-info mb-4" role="alert">
                    <div class="d-flex align-items-start">
                        <i class="ri-lock-2-line me-2 mt-1"></i>
                        <div>
                            <strong>{{ __('general.accessing_protected_content') }}:</strong>
                            <br>"{{ $post->title }}"
                        </div>
                    </div>
                </div>

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

                <form id="post_access_protection_form" method="POST" action="{{ route('post.access.protection.verify', $post) }}" novalidate>
                    @csrf
                    <div class="mb-3">
                        <x-form-elements.password-input
                            id="password"
                            name="password"
                            label="{{ __('general.enter_post_password') }}"
                            inputclass="form-control"
                            placeholder="{{ __('general.enter_password_to_view_content') }}"
                            :extra-attributes="['required' => 'required']" />
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100" id="submit-btn">
                        <i class="ri-lock-unlock-line me-2"></i>
                        <span class="btn-text">{{ __('general.verify_password') }}</span>
                    </button>
                </form>

                <div class="mt-4">
                    <small class="text-muted text-center d-block">
                        {{ __('general.post_password_protected_notice') }}
                    </small>
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript for Post Access Protection Form --}}
    <script data-up-execute>
    /**
     * Post Access Protection Form JavaScript
     *
     * Handles form functionality for the post access protection form.
     * Includes submit button loading state and form validation.
     */

    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('post_access_protection_form');
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
                submitBtn.innerHTML = '<i class="ri-hourglass-2-line-split me-2"></i>{{ __('general.verifying') }}...';
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
