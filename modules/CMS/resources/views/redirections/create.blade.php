<x-app-layout title="Create Redirection">

    <x-page-header
        title="Create Redirection"
        description="Add a new URL redirection rule"
        layout="form"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Manage'],
            ['label' => 'Redirections', 'href' => route('cms.redirections.index')],
            ['label' => 'Create', 'active' => true],
        ]"
        :actions="[
            [
                'label' => 'Back to Redirections',
                'icon' => 'ri-arrow-left-line',
                'variant' => 'btn-outline-secondary',
                'href' => route('cms.redirections.index'),
            ],
        ]"
    />

    <x-client-validation-alert />

    <form data-dirty-form
        class="needs-validation"
        id="seo-redirection-form"
        action="{{ route('cms.redirections.store') }}"
        method="POST"
        novalidate
        data-is-edit="false"
        data-has-errors="{{ $errors->any() ? 'true' : 'false' }}"
        data-alert-container="alert-container"
    >
        @csrf

        @include('cms::redirections.form')
    </form>

    @include('cms::redirections.partials.form-scripts', ['isEdit' => false])

</x-app-layout>
