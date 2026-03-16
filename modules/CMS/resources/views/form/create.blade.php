<x-app-layout title="Create Form">
    {{-- Page Header --}}
    <x-page-header title="Create Form"
        description="Add a new form to your website"
        layout="form"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Manage'],
            ['label' => 'Forms', 'href' => route('cms.form.index')],
            ['label' => 'Create', 'active' => true],
        ]"
        :actions="[
            [
                'label' => 'Back to Forms',
                'href' => route('cms.form.index'),
                'icon' => 'ri-arrow-left-line',
                'variant' => 'btn-outline-secondary',
            ],
        ]" />

    <x-client-validation-alert />

    {{-- Form --}}
    <form data-dirty-form
        class="needs-validation"
        action="{{ route('cms.form.store') }}"
        method="POST"
        enctype="multipart/form-data"
        id="form-form"
        novalidate
        data-is-edit="false"
        data-has-errors="{{ $errors->any() ? 'true' : 'false' }}"
        data-alert-container="alert-container">
        @csrf
        @include('cms::form.form')
    </form>

    {{-- JavaScript Assets --}}
    <x-script-loader :wrap="false" :scripts="['modules/CMS/resources/views/form/js/form-form-manager.js']" />

    <script data-up-execute>
        if (typeof window.initializeFormForm === 'function') {
            window.initializeFormForm();
        }
    </script>
</x-app-layout>
