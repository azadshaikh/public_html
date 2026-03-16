<x-app-layout title="Categories">
    <x-page-header
        title="Categories"
        description="Manage content categories"
        layout="datagrid"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'CMS'],
            ['label' => 'Categories'],
        ]"
        :actions="[
            ['label' => 'Create', 'href' => route('cms.categories.create'), 'icon' => 'ri-add-line', 'variant' => 'btn-primary'],
        ]"
    />

    <x-datagrid
        aria-label="Categories table"
        :url="route('cms.categories.data')"
        :bulk-action-url="route('cms.categories.bulk-action')"
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
        // Register custom DataGrid templates for Categories
        window.DataGridTemplates = window.DataGridTemplates || {};
        const escapeHtml = (v) => window.DataGrid.escape(v);
        const safeUrl = (v, f) => window.DataGrid.safeUrl(v, f);

        // Template for category title with parent hierarchy and permalink
        window.DataGridTemplates['category_title_meta'] = function(value, row, column) {
            const titleText = escapeHtml(row.title || '');
            const editUrl = safeUrl(row.edit_url, '#');
            const parentName = row.parent_name || null;
            const permalinkUrl = safeUrl(row.permalink_url, '');

            // Build parent badge if exists
            let parentHtml = '';
            if (parentName) {
                parentHtml = `<span class="badge bg-secondary-subtle text-secondary me-1">${escapeHtml(parentName)}</span>`;
            }

            // Build permalink link if URL exists
            let permalinkHtml = '';
            if (permalinkUrl !== '') {
                const permalinkDisplay = escapeHtml(permalinkUrl.replace(/^https?:\/\//, ''));
                permalinkHtml = `
                    <a href="${permalinkUrl}" target="_blank" rel="noopener noreferrer"
                       class="text-muted text-decoration-none d-inline-flex align-items-center gap-1 permalink-link"
                       title="View on site">
                        <i class="ri-external-link-line" style="font-size: 10px;"></i>
                        <span class="text-truncate" style="max-width: 200px;">${permalinkDisplay}</span>
                    </a>
                `;
            }

            return `
                <div>
                    <a href="${editUrl}" up-follow up-target="[up-main]" class="fw-medium text-decoration-none d-block">${titleText}</a>
                    <small class="text-muted d-flex align-items-center flex-wrap gap-1">${parentHtml}${permalinkHtml}</small>
                </div>
            `;
        };

        // Template for term date (published or last modified) - reuse if not already defined
        if (!window.DataGridTemplates['term_date']) {
            window.DataGridTemplates['term_date'] = function(value, row, column) {
                let dateValue = row.display_date || row.updated_at_formatted || row.created_at || '';
                let dateLabel = row.status === 'published' ? 'Published' : 'Last Modified';

                return `
                    <div>
                        <div class="text-muted small">${dateLabel}</div>
                        <div>${escapeHtml(dateValue)}</div>
                    </div>
                `;
            };
        }
    </script>
    @endpush
</x-app-layout>
