<x-app-layout title="Pages">
    <x-page-header
        title="Pages"
        description="Manage content pages"
        layout="datagrid"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'CMS'],
            ['label' => 'Pages'],
        ]"
        :actions="[
            ['label' => 'Create', 'href' => route('cms.pages.create'), 'icon' => 'ri-add-line', 'variant' => 'btn-primary'],
        ]"
    />

    <x-datagrid
        aria-label="Pages table"
        :url="route('cms.pages.data')"
        :bulk-action-url="route('cms.pages.bulk-action')"
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
        // Register custom DataGrid templates for Pages
        window.DataGridTemplates = window.DataGridTemplates || {};
        const escapeHtml = (v) => window.DataGrid.escape(v);
        const safeUrl = (v, f) => window.DataGrid.safeUrl(v, f);

        // Template for featured image only (separate column for better card view)
        window.DataGridTemplates['page_featured_image'] = function(value, row, column) {
            if (row.featured_image_url) {
                return `<img src="${safeUrl(row.featured_image_url, '#')}" alt="" class="border rounded" style="width: 90px; height: 60px; object-fit: cover;">`;
            }
            return `<div class="bg-secondary-subtle border rounded d-flex align-items-center justify-content-center" style="width: 90px; height: 60px;"><i class="ri-image-line text-muted"></i></div>`;
        };

        // Template for page title with author and permalink (without image)
        window.DataGridTemplates['page_title_meta'] = function(value, row, column) {
            const titleText = escapeHtml(row.title || '');
            const authorName = row.author_name ? escapeHtml(row.author_name) : null;
            const editUrl = safeUrl(row.edit_url, '#');
            const permalinkUrl = safeUrl(row.permalink_url, '');

            // Build author display
            let authorHtml = authorName ? `by ${authorName}` : '';

            // Build permalink link if URL exists
            let permalinkHtml = '';
            if (permalinkUrl !== '') {
                const permalinkDisplay = escapeHtml(permalinkUrl.replace(/^https?:\/\//, ''));
                if (authorHtml) {
                    permalinkHtml = `<span class="text-muted mx-1">|</span>`;
                }
                permalinkHtml += `
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
                    <a href="${editUrl}" up-follow up-target="[up-main]" class="fw-medium text-decoration-none d-block" style="word-wrap: break-word; overflow-wrap: break-word;">${titleText}</a>
                    <small class="text-muted d-inline-flex align-items-center flex-wrap">${authorHtml}${permalinkHtml}</small>
                </div>
            `;
        };

        // Template for parent page display
        window.DataGridTemplates['page_parent'] = function(value, row, column) {
            const parentName = row.parent_name || row.parent_display;
            if (!parentName || parentName === '—') {
                return '<span class="text-muted">—</span>';
            }
            return `<span class="badge bg-secondary-subtle text-secondary">${escapeHtml(parentName)}</span>`;
        };

        // Template for page date (published or last modified)
        window.DataGridTemplates['page_date'] = function(value, row, column) {
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
