<x-app-layout title="Edit Post: {{ $post->title }}">
    {{-- Page Header --}}
    <x-page-header title="Edit Post"
        description="Update post information"
        layout="form"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
                ['label' => 'CMS'],
            ['label' => 'Posts', 'href' => route('cms.posts.index')],
            ['label' => '#' . $post->id],
            ['label' => 'Edit', 'active' => true],
        ]"
        :actions="[
            [
                'label' => 'Back to Posts',
                'href' => route('cms.posts.index'),
                'icon' => 'ri-arrow-left-line',
                'variant' => 'btn-outline-secondary',
            ],
            [
                'label' => 'Edit in Builder',
                'href' => route('cms.builder.edit', $post),
                'icon' => 'ri-layout-3-line',
                'variant' => 'btn-outline-primary',
                'target' => '_blank',
                'rel' => 'noopener noreferrer',
                'up-follow' => 'false',
            ],
            [
                'label' => 'View',
                'href' => url($post->permalink_url),
                'icon' => 'ri-eye-line',
                'variant' => 'btn-outline-secondary',
                'target' => '_blank',
            ],
        ]" />

    @if ($errors->any())
        @php
            $errorFields = array_keys($errors->toArray());
            $fieldLabels = [
                'title' => 'Post Title',
                'slug' => 'Slug',
                'status' => 'Status',
                'categories' => 'Categories',
                'meta_title' => 'Meta Title',
                'meta_description' => 'Meta Description',
                'meta_robots' => 'Meta Robots',
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

    <form data-dirty-form class="needs-validation" action="{{ route('cms.posts.update', $post) }}" method="POST" id="post-form" novalidate>
        @csrf
        @method('PUT')
        @include('cms::posts.form')
    </form>

    @can('delete_posts')
        <form id="trash-post-form" method="POST" action="{{ route('cms.posts.destroy', $post->id) }}" class="d-none">
            @csrf
            @method('DELETE')
        </form>
    @endcan
</x-app-layout>
