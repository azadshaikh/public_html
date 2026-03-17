{{-- Edit SSL Certificate Page --}}
<x-app-layout title="Edit SSL Certificate - {{ $domain->domain_name }}">

    {{-- Page Header --}}
    <x-page-header title="Edit SSL Certificate"
        description="Update SSL certificate for {{ $domain->domain_name }}" layout="form"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Platform'],
            ['label' => 'Domains', 'href' => route('platform.domains.index')],
            ['label' => '#' . $domain->id, 'href' => route('platform.domains.show', $domain)],
            ['label' => '#' . $certificate->id, 'href' => Route::has('platform.domains.ssl-certificates.show') ? route('platform.domains.ssl-certificates.show', [$domain, $certificate->id]) : null],
            ['label' => 'Edit', 'active' => true],
        ]" />

    @if ($errors->any())
        @php
            $errorFields = array_keys($errors->toArray());
            $fieldLabels = [
                'name' => 'Certificate Name',
                'certificate_authority' => 'Certificate Authority',
                'private_key' => 'Private Key',
                'certificate' => 'Certificate',
                'ca_bundle' => 'CA Bundle',
            ];

            $friendlyFieldNames = array_map(function ($field) use ($fieldLabels) {
                return $fieldLabels[$field] ?? ucfirst(str_replace('_', ' ', $field));
            }, $errorFields);

            $fieldCount = count($friendlyFieldNames);
            $errorSummary = $fieldCount === 1
                ? "Please check the {$friendlyFieldNames[0]} field."
                : 'Please check the following fields: ' . implode(', ', $friendlyFieldNames) . '.';
        @endphp
        <div class="alert alert-danger alert-dismissible fade rounded-4 show" role="alert">
            <div class="d-flex align-items-start">
                <div class="alert-icon me-3 flex-shrink-0">
                    <i class="ri-error-warning-fill" style="font-size: 1.25rem;"></i>
                </div>
                <div class="flex-grow-1">
                    <h5 class="fw-semibold mb-2">Validation Error!</h5>
                    <p class="mb-0">{{ $errorSummary }}</p>
                </div>
            </div>
            <button class="btn-close" data-bs-dismiss="alert" type="button" aria-label="Close"></button>
        </div>
    @endif

    {{-- Certificate Form --}}
    <form data-dirty-form class="needs-validation" id="ssl-certificate-form" method="POST" action="{{ $formConfig['action'] }}" novalidate>
        @csrf
        @method('PUT')

        @include('platform::ssl-certificates._form', [
            'domain' => $domain,
            'certificate' => $certificate,
            'certificateDetails' => $certificateDetails,
            'certificateAuthorityOptions' => $certificateAuthorityOptions,
            'formConfig' => $formConfig,
        ])
    </form>

</x-app-layout>
