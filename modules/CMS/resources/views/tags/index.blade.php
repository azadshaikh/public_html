<x-app-layout title="Tags">
    <x-page-header
        title="Tags"
        description="Manage content tags"
        layout="datagrid"
        :breadcrumbs="[
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'CMS'],
            ['label' => 'Tags'],
        ]"
        :actions="[
            ['label' => 'Create', 'href' => route('cms.tags.create'), 'icon' => 'ri-add-line', 'variant' => 'btn-primary'],
        ]"
    />

    <x-datagrid
        aria-label="Tags table"
        :url="route('cms.tags.data')"
        :bulk-action-url="route('cms.tags.bulk-action')"
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
        // Register custom DataGrid templates for Tags
        window.DataGridTemplates = window.DataGridTemplates || {};
        const escapeHtml = (v) => window.DataGrid.escape(v);
        const safeUrl = (v, f) => window.DataGrid.safeUrl(v, f);

        // Template for tag title with slug and permalink
        window.DataGridTemplates['tag_title_meta'] = function(value, row, column) {
            const titleText = escapeHtml(row.title || '');
            const editUrl = safeUrl(row.edit_url, '#');
            const permalinkUrl = safeUrl(row.permalink_url, '');

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
                    <small class="text-muted">${permalinkHtml}</small>
                </div>
            `;
        };

        // Template for term date (published or last modified)
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
    </script>
    @endpush
</x-app-layout>
