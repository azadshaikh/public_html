<x-app-layout title="Edit Redirection">

    <x-page-header
        title="Edit Redirection"
        description="Update the redirection details"
        layout="form"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Manage'],
            ['label' => 'Redirections', 'href' => route('cms.redirections.index')],
            ['label' => '#' . $redirection->id],
            ['label' => 'Edit', 'active' => true],
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
        action="{{ route('cms.redirections.update', $redirection) }}"
        method="POST"
        novalidate
        data-is-edit="true"
        data-has-errors="{{ $errors->any() ? 'true' : 'false' }}"
        data-alert-container="alert-container"
    >
        @csrf
        @method('PUT')

        @include('cms::redirections.form')
    </form>

    @include('cms::redirections.partials.form-scripts', ['isEdit' => true])

</x-app-layout>
