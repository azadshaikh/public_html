<x-app-layout :title="$form->title">
    @php
        // Load relationships needed for this view
        $form->loadMissing(['createdBy']);

        $title = $form->title ?: ('Form #' . $form->id);
        $isTrashed = !empty($form->deleted_at);
        $activeTab = request('section', 'details');

        // Status configuration
        $statusConfig = match($form->status ?? 'draft') {
            'published' => ['icon' => 'ri-checkbox-circle-line', 'color' => 'success', 'bg' => 'success-subtle'],
            'draft' => ['icon' => 'ri-edit-line', 'color' => 'secondary', 'bg' => 'secondary-subtle'],
            'active' => ['icon' => 'ri-checkbox-circle-line', 'color' => 'success', 'bg' => 'success-subtle'],
            'inactive' => ['icon' => 'ri-pause-circle-line', 'color' => 'warning', 'bg' => 'warning-subtle'],
            default => ['icon' => 'ri-question-line', 'color' => 'secondary', 'bg' => 'secondary-subtle'],
        };

        // Breadcrumbs
        $breadcrumbs = [
            ['label' => 'Dashboard', 'href' => route('dashboard')],
            ['label' => 'Manage'],
            ['label' => 'Forms', 'href' => route('cms.form.index')],
            ['label' => $title, 'active' => true],
        ];

        // Actions
        $actions = [];
        $actions[] = [
            'type' => 'link',
            'label' => 'Edit',
            'icon' => 'ri-pencil-line',
            'href' => route('cms.form.edit', $form),
            'variant' => 'btn-primary'
        ];

        if (!$isTrashed) {
            $actions[] = [
                'type' => 'dropdown',
                'label' => 'More',
                'icon' => 'ri-more-fill',
                'variant' => 'btn-outline-secondary',
                'items' => [[
                    'label' => 'Delete',
                    'icon' => 'ri-delete-bin-line',
                    'href' => route('cms.form.destroy', $form),
                    'class' => 'text-danger confirmation-btn',
                    'attributes' => [
                        'data-title' => 'Delete Form',
                        'data-method' => 'DELETE',
                        'data-message' => 'Move this form to trash?',
                        'data-confirmButtonText' => 'Delete',
                        'data-confirmButtonClass' => 'btn-danger',
                    ]
                ]]
            ];
        }

        $actions[] = [
            'type' => 'link',
            'label' => 'Back',
            'icon' => 'ri-arrow-left-line',
            'href' => route('cms.form.index'),
            'variant' => 'btn-outline-secondary'
        ];

        // Tab configuration
        $notesCount = $form->notes->count();
        $tabs = [
            ['name' => 'details', 'label' => 'Details', 'icon' => 'ri-file-list-3-line'],
            ['name' => 'content', 'label' => 'Content', 'icon' => 'ri-code-line'],
            ['name' => 'notes', 'label' => 'Notes', 'icon' => 'ri-sticky-note-line', 'count' => $notesCount],
        ];
    @endphp

    <x-page-header :breadcrumbs="$breadcrumbs" :actions="$actions">
        <x-slot:custom_title>
            <div class="d-flex align-items-center gap-3">
                <span class="h3 mb-0">{{ $title }}</span>
                <span class="badge bg-{{ $statusConfig['bg'] }} text-{{ $statusConfig['color'] }} fs-6">
                    <i class="{{ $statusConfig['icon'] }} me-1"></i>{{ $form->status_label ?? ucfirst($form->status ?? 'Draft') }}
                </span>
            </div>
        </x-slot:custom_title>
        <x-slot:description>View form details and submissions.</x-slot:description>
    </x-page-header>

    {{-- Trashed Warning Banner --}}
    @if($isTrashed)
    <div class="alert alert-warning d-flex align-items-center mb-4">
        <div class="d-flex align-items-center justify-content-center bg-warning bg-opacity-25 me-3" style="width: 48px; height: 48px; border-radius: 50%;">
            <i class="ri-delete-bin-line fs-4 text-warning"></i>
        </div>
        <div class="flex-grow-1">
            <div class="fw-semibold">This form is in trash</div>
            <div class="small text-muted">Trashed on {{ $form->deleted_at ? app_date_time_format($form->deleted_at, 'datetime') : '—' }}</div>
        </div>
        <a class="btn btn-warning confirmation-btn"
            data-title="Restore Form"
            data-method="PATCH"
            data-message="Restore this form?"
            data-confirmButtonText="Restore"
            href="{{ route('cms.form.restore', $form) }}">
            <i class="ri-refresh-line me-1"></i>Restore
        </a>
    </div>
    @endif

    <div class="row">
        {{-- Left Column: Info Cards --}}
        <div class="col-lg-4 mb-4">
            {{-- Primary Info --}}
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Form Information</h5></div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted" style="width: 40%;">Slug</td>
                                <td><code>{{ $form->slug ?: '—' }}</code></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Shortcode</td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control form-control-sm" value="[form id=&quot;{{ $form->shortcode }}&quot;]" readonly id="shortcode-input">
                                        <button class="btn btn-outline-secondary btn-sm" type="button" onclick="copyToClipboard(document.getElementById('shortcode-input'))">
                                            <i class="ri-clipboard-line"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Published</td>
                                <td>{{ $form->published_at_formatted ?? '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Statistics --}}
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Statistics</h5></div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4 border-end">
                            <div class="fs-4 fw-bold text-primary">{{ $form->submissions_count ?? 0 }}</div>
                            <small class="text-muted">Submissions</small>
                        </div>
                        <div class="col-4 border-end">
                            <div class="fs-4 fw-bold text-info">{{ $form->views_count ?? 0 }}</div>
                            <small class="text-muted">Views</small>
                        </div>
                        <div class="col-4">
                            <div class="fs-4 fw-bold text-warning">{{ $notesCount }}</div>
                            <small class="text-muted">Notes</small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Configuration --}}
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Configuration</h5></div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted" style="width: 60%;">Store in Database</td>
                                <td>
                                    @if($form->store_in_database)
                                        <span class="badge bg-success-subtle text-success">Yes</span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning">No</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Spam Protection</td>
                                <td>
                                    @if($form->has_spam_protection ?? false)
                                        <span class="badge bg-success-subtle text-success">Enabled</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">Disabled</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Requires Login</td>
                                <td>
                                    @if($form->requires_login ?? false)
                                        <span class="badge bg-info-subtle text-info">Yes</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">No</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    @if($form->confirmations)
                    <div class="mt-3 pt-3 border-top">
                        <div class="small text-muted mb-2">Confirmation Settings</div>
                        <div class="small">
                            <strong>Type:</strong> {{ ucfirst($form->confirmations['type'] ?? 'Not set') }}<br>
                            @if(isset($form->confirmations['message']))
                                <strong>Message:</strong> {{ $form->confirmations['message'] }}
                            @endif
                            @if(isset($form->confirmations['redirect']))
                                <strong>Redirect:</strong> {{ $form->confirmations['redirect'] }}
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Audit Info --}}
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Record Info</h5></div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-3">
                        <div>
                            <div class="small text-muted">ID</div>
                            <div class="fw-semibold">{{ $form->id }}</div>
                        </div>
                        <div>
                            <div class="small text-muted">Created By</div>
                            <div class="fw-semibold">{{ $form->createdBy?->name ?? 'System' }}</div>
                        </div>
                        <div>
                            <div class="small text-muted">Created</div>
                            <div>{{ $form->created_at ? app_date_time_format($form->created_at, 'datetime') : '—' }}</div>
                        </div>
                        <div>
                            <div class="small text-muted">Updated</div>
                            <div>{{ $form->updated_at ? app_date_time_format($form->updated_at, 'datetime') : '—' }}</div>
                        </div>
                        @if($form->deleted_at)
                        <div>
                            <div class="small text-muted">Deleted</div>
                            <div class="text-danger">{{ app_date_time_format($form->deleted_at, 'datetime') }}</div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Column: Tabbed Content --}}
        <div class="col-lg-8">
            <x-tabs param="section" :active="$activeTab" :tabs="$tabs">
                <x-slot:details>
                    {{-- Basic Information --}}
                    <div class="card mb-4">
                        <div class="card-header"><h6 class="mb-0">Basic Information</h6></div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4 text-muted">Title</dt>
                                <dd class="col-sm-8">{{ $form->title }}</dd>

                                <dt class="col-sm-4 text-muted">Template</dt>
                                <dd class="col-sm-8">{{ $form->template ?: '—' }}</dd>

                                <dt class="col-sm-4 text-muted">Form Type</dt>
                                <dd class="col-sm-8">{{ ucfirst($form->form_type ?? 'standard') }}</dd>

                                @if($form->last_submission_at)
                                <dt class="col-sm-4 text-muted">Last Submission</dt>
                                <dd class="col-sm-8">{{ app_date_time_format($form->last_submission_at, 'datetime') }}</dd>
                                @endif
                            </dl>
                        </div>
                    </div>

                    {{-- Notification Settings --}}
                    @if($form->notification_emails)
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0">Notification Settings</h6></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="small text-muted mb-1">Notification Emails</div>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach($form->notification_emails as $email)
                                        <span class="badge bg-primary-subtle text-primary">{{ $email }}</span>
                                    @endforeach
                                </div>
                            </div>
                            @if($form->send_autoresponder)
                            <div>
                                <span class="badge bg-success-subtle text-success">
                                    <i class="ri-mail-check-line me-1"></i>Autoresponder Enabled
                                </span>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </x-slot:details>

                <x-slot:content>
                    {{-- HTML Code --}}
                    <div class="card mb-4">
                        <div class="card-header"><h6 class="mb-0">HTML Code</h6></div>
                        <div class="card-body p-0">
                            <pre class="bg-light p-3 mb-0" style="max-height: 400px; overflow-y: auto;"><code>{{ $form->html }}</code></pre>
                        </div>
                    </div>

                    {{-- Custom CSS --}}
                    @if($form->css)
                    <div class="card mb-4">
                        <div class="card-header"><h6 class="mb-0">Custom CSS</h6></div>
                        <div class="card-body p-0">
                            <pre class="bg-light p-3 mb-0" style="max-height: 300px; overflow-y: auto;"><code>{{ $form->css }}</code></pre>
                        </div>
                    </div>
                    @endif

                    {{-- Custom JS --}}
                    @if($form->js)
                    <div class="card">
                        <div class="card-header"><h6 class="mb-0">Custom JavaScript</h6></div>
                        <div class="card-body p-0">
                            <pre class="bg-light p-3 mb-0" style="max-height: 300px; overflow-y: auto;"><code>{{ $form->js }}</code></pre>
                        </div>
                    </div>
                    @endif
                </x-slot:content>

                <x-slot:notes>
                    <x-app.notes :model="$form" />
                </x-slot:notes>
            </x-tabs>
        </div>
    </div>

    {{-- JavaScript --}}
    <script data-up-execute>
    function copyToClipboard(input) {
        input.select();
        input.setSelectionRange(0, 99999);
        document.execCommand('copy');

        const button = input.nextElementSibling;
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="ri-check-line"></i>';
        button.classList.add('btn-success');
        button.classList.remove('btn-outline-secondary');

        setTimeout(() => {
            button.innerHTML = originalHTML;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-secondary');
        }, 2000);
    }
    </script>
</x-app-layout>
