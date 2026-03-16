<x-app-layout title="Create Tag">
    {{-- Page Header --}}
    <x-page-header title="Create Tag"
        description="Add a new tag for organizing content"
        layout="form"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'CMS'],
            ['label' => 'Tags', 'href' => route('cms.tags.index')],
            ['label' => 'Create', 'active' => true],
        ]"
        :actions="[
            [
                'label' => 'Back to Tags',
                'href' => route('cms.tags.index'),
                'icon' => 'ri-arrow-left-line',
                'variant' => 'btn-outline-secondary',
            ],
        ]" />

    @if ($errors->any())
        @php
            $errorFields = array_keys($errors->toArray());
            $fieldLabels = [
                'title' => 'Tag Title',
                'slug' => 'Slug',
                'status' => 'Status',
                'meta_title' => 'Meta Title',
                'meta_description' => 'Meta Description',
                'meta_robots' => 'Meta Robots',
            ];

            $friendlyFieldNames = array_map(function ($field) use ($fieldLabels) {
                return $fieldLabels[$field] ?? ucfirst(str_replace(['_', '_'], ['', ' '], $field));
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

    <form data-dirty-form class="needs-validation" action="{{ route('cms.tags.store') }}" method="POST" id="tag-form" novalidate>
        @csrf
        @include('cms::tags.form')
    </form>
</x-app-layout>
