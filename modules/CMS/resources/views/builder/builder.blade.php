<!DOCTYPE html>
<html data-bs-theme="light" lang="en">
    @php
        $adminSlug = trim((string) config('app.admin_slug'), '/');
        $builderPath = $adminSlug === '' ? '/cms/builder' : '/'.$adminSlug.'/cms/builder';
    @endphp
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Edit: {{ $page->title }}</title>

        {{-- Astero UI Framework --}}
        <link href="{{ asset('vendor/asteroui/styles/asteroui.min.css') }}" rel="stylesheet" />

        {{-- Builder & App CSS (Vite) --}}
        @vite('modules/CMS/resources/builder/css/builder.css')
        @vite('resources/css/app.css')
        @vite('resources/css/media-library.css')
        @vite('resources/css/datagrid.css')

        {{-- Icons --}}
        <link href="https://cdn.jsdelivr.net/npm/remixicon@4.8.0/fonts/remixicon.css" rel="stylesheet" />

        {{-- Coloris Color Picker - imported via npm in plugin-coloris.js --}}

        @stack('styles')
    </head>
    <body>

        <div id="astero-builder">
            @include('cms::builder.partials.top-panel')
            @include('cms::builder.partials.left-panel')
            @include('cms::builder.partials.canvas')
            @include('cms::builder.partials.right-panel')
            @include('cms::builder.partials.bottom-panel')
        </div>

        @include('cms::builder.partials.templates')
        @include('cms::builder.partials.navigator')
        @include('cms::builder.partials.modals')

        {{-- Core JS Libraries --}}
        <script src="{{ asset('vendor/asteroui/scripts/asteroui.min.js') }}"></script>

        {{-- Media Picker Modal --}}
        <x-media-picker.media-modal />

        {{-- Alpine.js (required for media modal) --}}
        <script defer src="https://unpkg.com/alpinejs@3.14.9/dist/cdn.min.js"></script>

        {{-- Media Library & DataGrid JS --}}
        @vite(['resources/js/media-library.js', 'resources/js/datagrid/index.js'])

        {{-- Builder Configuration --}}
        <script>
            // Builder configuration
            window.ASTERO_IFRAME_HELPERS_CSS_URL = @json(Vite::asset('modules/CMS/resources/builder/css/iframe.css'));
            window.ASTERO_BASE_URL = '{{ asset("assets/builder") }}/';
            window.Astero = window.Astero || {};
            window.Astero.config = {
                theme: '{{ $page->website->theme ?? "starter" }}',
                adminSlug: '{{ $adminSlug }}',
                builderUrl: '{{ url($builderPath) }}'
            };
            window.mediaPath = '/media';
            window.blocksurl = '{{ route('cms.builder.ajax.design.blocks') }}';

            // Toast helper for notifications
            window.displayToast = window.displayToast || function(bgClass, title, message) {
                console.warn('[Toast]', title, message);
            };
        </script>

        {{-- Coloris is now imported via npm in plugin-coloris.js --}}
        {{-- Choices.js --}}
        <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>

        {{-- Astero Builder (Vite bundle) --}}
        @vite('modules/CMS/resources/builder/js/editor.js')

        {{-- Builder Initialization --}}
        <script type="module">
            const saveUrl = '{{ route('cms.builder.save', $page) }}';
            const loadUrl = '{{ url($page->permalink_url) }}?editor_preview=1';
            const csrfToken = '{{ csrf_token() }}';

            // Save page to Laravel backend
            function savePageToLaravel() {
                const saveBtn = document.querySelector('#save-btn');
                const loadingSpan = saveBtn?.querySelector('.loading');
                const buttonIcon = saveBtn?.querySelector('i.ri-save-line');

                // Show loading state
                loadingSpan?.classList.remove('d-none');
                buttonIcon?.classList.add('d-none');
                if (saveBtn) saveBtn.disabled = true;

                // Get content from the builder
                const content = Astero.EnabledContentEditor?.getEnabledContent?.() ?? '';
                const css = Astero.StyleManager?.getCss?.() ?? '';
                const js = Astero.ScriptManager?.getJs?.() ?? '';

                // Debug: Log what we're saving
                console.log('[Builder Save] Content length:', content.length);
                console.log('[Builder Save] CSS length:', css.length);
                console.log('[Builder Save] JS length:', js.length);

                if (!content || content.length === 0) {
                    console.warn('[Builder Save] Warning: Content is empty!');
                    displayToast('bg-warning', 'Warning', 'Page content appears to be empty.');
                }

                fetch(saveUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ content, css, js, format: 'pagebuilder' })
                })
                .then(response => {
                    if (!response.ok) {
                        return response.text().then(text => {
                            console.error('[Builder Save] HTTP Error:', response.status, text);
                            throw new Error(`HTTP ${response.status}: ${text.substring(0, 200)}`);
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        displayToast('bg-success', 'Save', 'Page saved successfully!');
                        Astero.Undo?.reset?.();
                        document.querySelectorAll('#top-panel .save-btn').forEach(e => e.setAttribute('disabled', 'true'));
                    } else {
                        displayToast('bg-danger', 'Error', 'Error saving page: ' + (data.message || 'Unknown error'));
                        if (saveBtn) saveBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error saving page:', error);
                    displayToast('bg-danger', 'Error', 'Error saving page: ' + error.message);
                    if (saveBtn) saveBtn.disabled = false;
                })
                .finally(() => {
                    loadingSpan?.classList.add('d-none');
                    buttonIcon?.classList.remove('d-none');
                });
            }

            // Override Astero.Gui.saveAjax to use Laravel save
            if (window.Astero?.Gui) {
                Astero.Gui.saveAjax = function(event) {
                    event?.preventDefault?.();
                    savePageToLaravel();
                    return false;
                };
            }

            // Initialize builder
            if (window.Astero?.Builder?.init) {
                Astero.Builder.init(loadUrl);
            } else {
                console.error('[PageBuilder] Astero.Builder.init is not available');
            }

            // Initialize UI components
            Astero.Gui?.init?.();
            Astero.SectionList?.init?.();
            Astero.Breadcrumb?.init?.();

            // Notification helper
            if (Astero.Gui && !Astero.Gui.notify) {
                Astero.Gui.notify = (message, type = 'info') => {
                    const bgClass = type === 'success' ? 'bg-success' : type === 'error' ? 'bg-danger' : 'bg-info';
                    displayToast(bgClass, 'Notification', message);
                };
            }

            // Show right panel by default
            document.getElementById('astero-builder')?.classList.remove('no-right-panel');

            // Initialize tree list after iframe loads
            window.addEventListener('astero.iframe.loaded', function() {
                Astero.TreeList?.init?.();
                Astero.TreeList?.loadComponents?.();
                Astero.CssEditor?.init?.();
            });
        </script>

        @stack('scripts')

    </body>
</html>
