<x-app-layout title="Posts">
    <x-page-header
        title="Posts"
        description="Manage blog posts"
        layout="datagrid"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'CMS'],
            ['label' => 'Posts'],
        ]"
        :actions="[
            ['label' => 'Create', 'href' => route('cms.posts.create'), 'icon' => 'ri-add-line', 'variant' => 'btn-primary'],
        ]"
    />

    <x-datagrid
        aria-label="Posts table"
        :url="route('cms.posts.data')"
        :bulk-action-url="route('cms.posts.bulk-action')"
        :table-config="$config"
        :initial-data="$initialData ?? null"
        :search="request('search')"
    />

    @push('styles')
    <style>
        .permalink-link {
            opacity: 0.7;
            transition: all 0.2s ease;
        }
        .permalink-link:hover {
            opacity: 1;
            color: var(--bs-primary) !important;
        }
        .permalink-link:hover span {
            text-decoration: underline;
        }
    </style>
    @endpush

    @push('scripts')
    <script data-up-execute>
        // Register custom DataGrid templates for Posts
        window.DataGridTemplates = window.DataGridTemplates || {};
        const escapeHtml = (v) => window.DataGrid.escape(v);
        const safeUrl = (v, f) => window.DataGrid.safeUrl(v, f);

        // Template for featured image only (separate column for better card view)
        window.DataGridTemplates['post_featured_image'] = function(value, row, column) {
            if (row.featured_image_url) {
                return `<img src="${safeUrl(row.featured_image_url, '#')}" alt="" class="border rounded" style="width: 90px; height: 60px; object-fit: cover;">`;
            }
            return `<div class="bg-secondary-subtle border rounded d-flex align-items-center justify-content-center" style="width: 90px; height: 60px;"><i class="ri-image-line text-muted"></i></div>`;
        };

        // Template for title with author and permalink (without image)
        window.DataGridTemplates['post_title_meta'] = function(value, row, column) {
            const titleText = escapeHtml(row.title || '');
            const authorName = escapeHtml(row.author_name || 'Unknown');
            const editUrl = safeUrl(row.edit_url, '#');
            const permalinkUrl = safeUrl(row.permalink_url, '');
            const featuredBadgeHtml = row.is_featured
                ? '<span class="badge bg-warning-subtle text-warning">Featured</span>'
                : '';

            // Build permalink link with elegant styling if URL exists and is valid
            let permalinkHtml = '';
            if (permalinkUrl !== '') {
                const permalinkDisplay = escapeHtml(permalinkUrl.replace(/^https?:\/\//, ''));
                permalinkHtml = `
                    <span class="text-muted mx-1">|</span>
                    <a href="${permalinkUrl}" target="_blank" rel="noopener noreferrer"
                       class="text-muted text-decoration-none d-inline-flex align-items-center gap-1 permalink-link"
                       title="View on site">
                        <i class="ri-external-link-line" style="font-size: 10px;"></i>
                        <span class="text-truncate" style="max-width: 150px;">${permalinkDisplay}</span>
                    </a>
                `;
            }

            return `
                <div style="max-width: 400px; overflow: hidden;">
                    <div class="d-flex align-items-start gap-2" style="max-width: 100%;">
                        <a href="${editUrl}" up-follow up-target="[up-main]" class="fw-medium text-decoration-none d-block" style="word-wrap: break-word; overflow-wrap: break-word;">${titleText}</a>
                        ${featuredBadgeHtml ? `<span class="flex-shrink-0">${featuredBadgeHtml}</span>` : ''}
                    </div>
                    <small class="text-muted d-inline-flex align-items-center flex-wrap">by ${authorName}${permalinkHtml}</small>
                </div>
            `;
        };

        // Template for categories display
        window.DataGridTemplates['post_categories'] = function(value, row, column) {
            if (!row.categories || row.categories.length === 0) {
                return '<span class="text-muted">—</span>';
            }

            return row.categories.map(cat => {
                const label = (cat && typeof cat === 'object') ? (cat.title || '') : (cat || '');
                return `<span class="badge bg-primary-subtle text-primary">${escapeHtml(label)}</span>`;
            }).join(' ');
        };

        // Template for conditional date (published or last modified)
        window.DataGridTemplates['post_date'] = function(value, row, column) {
            let dateValue = '';
            let dateLabel = '';

            if (row.status === 'published' && row.published_at) {
                dateValue = row.published_at_formatted || row.published_at;
                dateLabel = 'Published';
            } else {
                dateValue = row.updated_at_formatted || row.updated_at;
                dateLabel = 'Last Modified';
            }

            return `
                <div>
                    <div class="text-muted small">${dateLabel}</div>
                    <div>${escapeHtml(dateValue)}</div>
                </div>
            `;
        };
    </script>
    @endpush
</x-app-layout>
