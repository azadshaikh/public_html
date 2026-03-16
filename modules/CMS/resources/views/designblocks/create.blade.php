<x-app-layout title="Create Design Block">
    {{-- Page Header --}}
    <x-page-header title="Create Design Block"
        description="Add a new design block component"
        layout="form"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
                ['label' => 'CMS'],
            ['label' => 'Design Blocks', 'href' => route('cms.designblock.index')],
            ['label' => 'Create', 'active' => true],
        ]"
        :actions="[
            [
                'label' => 'Back to Design Blocks',
                'href' => route('cms.designblock.index'),
                'icon' => 'ri-arrow-left-line',
                'variant' => 'btn-outline-secondary',
            ],
        ]" />

    @if ($errors->any())
        @php
            $errorFields = array_keys($errors->toArray());
            $fieldLabels = [
                'title' => 'Title',
                'slug' => 'Slug',
                'description' => 'Description',
                'design_type' => 'Design Type',
                'category_id' => 'Category',
                'design_system' => 'Design System',
                'html' => 'HTML',
                'css' => 'CSS',
                'status' => 'Status',
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
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    @endif

    {{-- Form --}}
    <form data-dirty-form class="needs-validation" action="{{ route('cms.designblock.store') }}" method="POST" id="design-block-form" novalidate>
        @csrf
        @include('cms::designblocks._form')
    </form>

    {{-- JavaScript Assets --}}
    <x-script-loader :wrap="false" :scripts="['modules/CMS/resources/views/designblocks/js/design-block-form-manager.js']" />

    <x-media-picker.media-modal mediaconversion="small" />

    <script data-up-execute>
        setTimeout(() => {
            if (typeof window.initializeDesignBlockForm === 'function') {
                window.initializeDesignBlockForm();
            }
        }, 100);
    </script>
</x-app-layout>
