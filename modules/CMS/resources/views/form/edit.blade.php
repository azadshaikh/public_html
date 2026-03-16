<x-app-layout title="Edit Form">
    {{-- Page Header --}}
    <x-page-header title="Edit Form"
        description="Update form details"
        layout="form"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Manage'],
            ['label' => 'Forms', 'href' => route('cms.form.index')],
            ['label' => 'Edit: ' . $form->title, 'active' => true],
        ]"
        :actions="[
            [
                'label' => 'Back to List',
                'href' => route('cms.form.index'),
                'icon' => 'ri-arrow-left-line',
                'class' => 'btn btn-outline-secondary',
            ],
        ]" />

    <x-client-validation-alert />

    {{-- Form --}}
    <form data-dirty-form
        class="needs-validation"
        action="{{ route('cms.form.update', $form->id) }}"
        method="POST"
        enctype="multipart/form-data"
        id="form-form"
        novalidate
        data-is-edit="true"
        data-has-errors="{{ $errors->any() ? 'true' : 'false' }}"
        data-alert-container="alert-container">
        @csrf
        @method('PUT')
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
