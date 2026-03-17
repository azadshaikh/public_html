/**
 * Theme Customizer - Live theme settings editor with preview
 */

const AUTH_EXPIRED_ERROR_CODE = 'AUTH_EXPIRED';

function getCustomizerLoginUrl() {
    return window.customizerAuth?.loginUrl || '/login';
}

function isLoginResponsePath(responseUrl) {
    try {
        const loginUrl = new URL(
            getCustomizerLoginUrl(),
            window.location.origin,
        );
        const currentUrl = new URL(responseUrl, window.location.origin);
        return currentUrl.pathname === loginUrl.pathname;
    } catch (error) {
        console.warn(
            'ThemeCustomizer: Could not compare login URL path.',
            error,
        );
        return false;
    }
}

function createAuthExpiredError() {
    const error = new Error('Session expired. Redirecting to login...');
    error.code = AUTH_EXPIRED_ERROR_CODE;
    return error;
}

function isAuthExpiredError(error) {
    return Boolean(
        error &&
        typeof error === 'object' &&
        error.code === AUTH_EXPIRED_ERROR_CODE,
    );
}

function redirectToLogin() {
    if (window.__themeCustomizerRedirectingToLogin) {
        return;
    }

    window.__themeCustomizerRedirectingToLogin = true;
    window.location.href = getCustomizerLoginUrl();
}

function shouldRedirectForAuthExpiry(response) {
    if (!response) {
        return false;
    }

    const unauthorizedStatuses = [401, 419];
    if (unauthorizedStatuses.includes(response.status)) {
        return true;
    }

    const contentType = (
        response.headers.get('content-type') || ''
    ).toLowerCase();
    const redirectedToLogin =
        response.redirected && isLoginResponsePath(response.url);
    const loginHtmlResponse =
        contentType.includes('text/html') && isLoginResponsePath(response.url);

    return redirectedToLogin || loginHtmlResponse;
}

function installAuthExpiryInterceptor() {
    if (
        window.__themeCustomizerAuthInterceptorInstalled ||
        typeof window.fetch !== 'function'
    ) {
        return;
    }

    const originalFetch = window.fetch.bind(window);

    window.fetch = async (...args) => {
        const response = await originalFetch(...args);

        if (shouldRedirectForAuthExpiry(response)) {
            redirectToLogin();
            throw createAuthExpiredError();
        }

        return response;
    };

    window.__themeCustomizerAuthInterceptorInstalled = true;
}

class ThemeCustomizer {
    constructor() {
        this.form = document.getElementById('customizer-form');
        this.iframe = document.getElementById('preview-iframe');
        this.previewContainer = document.getElementById('preview-container');
        this.layoutContainer = document.querySelector(
            '.customizer-flex-container',
        );
        this.debounceTimer = null;
        this.debounceDelay = 800; // Increased for better performance
        this.hasUnsavedChanges = false;

        if (!this.form || !this.iframe) {
            console.error('ThemeCustomizer: Required elements not found');
            return;
        }

        this.init();
    }

    init() {
        this.bindEvents();
        this.setupSidebarToggle();
        this.setupBeforeUnloadWarning();
        this.setupIframePreviewMarker();
        this.setupPreviewResizing();
        console.log('Theme Customizer initialized');
    }

    /**
     * Setup dynamic preview container resizing
     * Calculates optimal dimensions based on available space
     */
    setupPreviewResizing() {
        this.updatePreviewDimensions();

        // Listen to window resize
        window.addEventListener('resize', () => {
            this.updatePreviewDimensions();
        });
    }

    /**
     * Update preview container dimensions dynamically
     */
    updatePreviewDimensions() {
        const container = this.previewContainer;
        if (!container) return;

        // Get the current device mode
        const isTabletView = container.classList.contains('tablet-view');
        const isMobileView = container.classList.contains('mobile-view');

        if (!isTabletView && !isMobileView) {
            // Desktop view - no special handling needed, fills available space
            return;
        }

        const wrapper = container.querySelector('.preview-wrapper');
        if (!wrapper) return;

        const DEVICE_SPECS = {
            tablet: { width: 820, height: 1180 },
            mobile: { width: 390, height: 844 },
        };

        const mode = isTabletView ? 'tablet' : 'mobile';
        const specs = DEVICE_SPECS[mode];
        const padding = 32; // 2rem

        const containerRect = container.getBoundingClientRect();
        const availableWidth = Math.max(containerRect.width - padding * 2, 0);
        const availableHeight = Math.max(containerRect.height - padding * 2, 0);

        const widthRatio = specs.width ? availableWidth / specs.width : 1;
        const heightRatio = specs.height ? availableHeight / specs.height : 1;
        const ratios = [];
        if (availableWidth > 0) ratios.push(widthRatio);
        if (availableHeight > 0) ratios.push(heightRatio);
        const scale = ratios.length ? Math.min(...ratios, 1) : 1;

        wrapper.style.width = `${specs.width}px`;
        wrapper.style.height = `${specs.height}px`;
        wrapper.style.maxWidth = `${specs.width}px`;
        wrapper.style.maxHeight = `${specs.height}px`;
        wrapper.style.transformOrigin = 'top center';
        wrapper.style.transform = scale === 1 ? '' : `scale(${scale})`;
        wrapper.style.margin = scale < 1 ? '0 auto' : '0 auto';
    }

    /**
     * Reset preview wrapper inline sizing so desktop mode can stretch naturally
     */
    resetPreviewWrapperStyles() {
        if (!this.previewContainer) return;
        const wrapper = this.previewContainer.querySelector('.preview-wrapper');
        if (!wrapper) return;

        wrapper.style.width = '';
        wrapper.style.maxWidth = '';
        wrapper.style.minHeight = '';
        wrapper.style.margin = '';
        wrapper.style.height = '';
        wrapper.style.maxHeight = '';
        wrapper.style.transform = '';
        wrapper.style.transformOrigin = '';
    }

    /**
     * Setup iframe to hide admin bar in customizer preview
     * Injects CSS to hide admin bar regardless of navigation
     */
    setupIframePreviewMarker() {
        // Inject CSS to hide admin bar once iframe loads
        this.iframe.addEventListener('load', () => {
            try {
                const iframeDoc =
                    this.iframe.contentDocument ||
                    this.iframe.contentWindow.document;
                if (iframeDoc) {
                    // Check if admin bar exists and hide it
                    const hideAdminBarStyle = iframeDoc.getElementById(
                        'customizer-hide-adminbar',
                    );
                    if (!hideAdminBarStyle) {
                        const style = iframeDoc.createElement('style');
                        style.id = 'customizer-hide-adminbar';
                        style.textContent = `
                            #admin_bar,
                            .admin-bar-container,
                            [id*="admin-bar"],
                            [class*="admin-bar"] {
                                display: none !important;
                            }
                            body.admin-bar-active {
                                padding-top: 0 !important;
                                margin-top: 0 !important;
                            }
                        `;
                        iframeDoc.head.appendChild(style);
                    }
                }
            } catch (e) {
                console.warn('Could not inject admin bar hide styles:', e);
            }
        });
    }

    /**
     * Setup sidebar toggle functionality for mobile and desktop
     */
    setupSidebarToggle() {
        const sidebar = document.getElementById('customizer-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const showBtnHeader = document.getElementById(
            'show-sidebar-btn-header',
        );
        const closeBtn = document.getElementById('toggle-sidebar-btn');
        const desktopToggleBtn = document.getElementById(
            'toggle-sidebar-desktop-btn',
        );

        // Mobile sidebar toggle (from header button)
        if (showBtnHeader) {
            showBtnHeader.addEventListener('click', () => {
                if (sidebar) sidebar.classList.add('show');
                if (overlay) overlay.classList.add('show');
            });
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                if (sidebar) sidebar.classList.remove('show');
                if (overlay) overlay.classList.remove('show');
            });
        }

        if (overlay) {
            overlay.addEventListener('click', () => {
                if (sidebar) sidebar.classList.remove('show');
                overlay.classList.remove('show');
            });
        }

        // Desktop sidebar toggle (collapse/expand)
        if (desktopToggleBtn && sidebar) {
            desktopToggleBtn.addEventListener('click', () => {
                const isCollapsed = sidebar.classList.toggle('collapsed');
                desktopToggleBtn.classList.toggle('collapsed', isCollapsed);
                if (this.layoutContainer) {
                    this.layoutContainer.classList.toggle(
                        'sidebar-collapsed',
                        isCollapsed,
                    );
                }

                // Update button title
                desktopToggleBtn.title = isCollapsed
                    ? 'Show Sidebar'
                    : 'Hide Sidebar';

                // Wait for CSS transition to complete, then recalculate dimensions
                setTimeout(() => {
                    this.updatePreviewDimensions();
                    this.refreshPreviewLayout();
                }, 350); // Slightly longer than the 0.3s CSS transition
            });
        }
    }

    /**
     * Refresh preview layout after sidebar toggle
     * This forces the iframe content to recalculate responsive layouts
     */
    refreshPreviewLayout() {
        if (!this.iframe || !this.iframe.contentWindow) {
            return;
        }

        try {
            // Trigger resize event in iframe to force responsive recalculation
            const iframeWindow = this.iframe.contentWindow;

            // Dispatch resize event
            if (iframeWindow.dispatchEvent) {
                const resizeEvent = new Event('resize');
                iframeWindow.dispatchEvent(resizeEvent);
            }
        } catch (e) {
            console.warn('Could not refresh preview layout:', e);
        }
    }

    /**
     * Bind all event listeners
     */
    bindEvents() {
        // Live preview on input change with debouncing
        this.form.addEventListener('input', (e) => {
            if (e.target.classList.contains('customizer-input')) {
                this.hasUnsavedChanges = true;
                this.debouncePreview();
            }
        });

        // Save button
        const saveBtn = document.getElementById('save-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveSettings());
        }

        // Ctrl+S / Cmd+S keyboard shortcut to save
        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                this.saveSettings();
            }
        });

        // Reset button (desktop)
        const resetBtn = document.getElementById('reset-btn');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => this.resetSettings());
        }

        // Reset button (mobile dropdown)
        const resetBtnMobile = document.getElementById('reset-btn-mobile');
        if (resetBtnMobile) {
            resetBtnMobile.addEventListener('click', () =>
                this.resetSettings(),
            );
        }

        // Export button (desktop)
        const exportBtn = document.getElementById('export-btn');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => this.exportSettings());
        }

        // Export button (mobile dropdown)
        const exportBtnMobile = document.getElementById('export-btn-mobile');
        if (exportBtnMobile) {
            exportBtnMobile.addEventListener('click', () =>
                this.exportSettings(),
            );
        }

        // Import button (desktop)
        const importBtn = document.getElementById('import-btn');
        if (importBtn) {
            importBtn.addEventListener('click', () => this.showImportModal());
        }

        // Import button (mobile dropdown)
        const importBtnMobile = document.getElementById('import-btn-mobile');
        if (importBtnMobile) {
            importBtnMobile.addEventListener('click', () =>
                this.showImportModal(),
            );
        }

        // Import confirm
        const importConfirmBtn = document.getElementById('import-confirm-btn');
        if (importConfirmBtn) {
            importConfirmBtn.addEventListener('click', () =>
                this.importSettings(),
            );
        }

        // Device preview buttons
        document.querySelectorAll('.device-preview').forEach((btn) => {
            btn.addEventListener('click', (e) => {
                const device = e.currentTarget.dataset.device;
                this.changePreviewDevice(device);
            });
        });

        // Refresh preview
        const refreshBtn = document.getElementById('refresh-preview');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.refreshPreview());
        }
    }

    /**
     * Debounce preview updates for better performance
     */
    debouncePreview() {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => {
            this.updatePreview();
        }, this.debounceDelay);
    }

    /**
     * Update live preview with current form values
     */
    async updatePreview() {
        try {
            this.previewContainer.classList.add('loading');

            const formData = this.collectFormData();
            const response = await fetch(window.customizerRoutes.previewCss, {
                method: 'POST',
                body: formData,
                headers: {
                    Accept: 'text/css,*/*;q=0.1',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                },
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const css = await response.text();
            this.injectCSS(css);
        } catch (error) {
            if (isAuthExpiredError(error)) {
                return;
            }
            console.error('Preview update failed:', error);
            this.showNotification('Preview update failed', 'error');
        } finally {
            this.previewContainer.classList.remove('loading');
        }
    }

    /**
     * Inject CSS into preview iframe
     */
    injectCSS(css) {
        try {
            const iframeDoc =
                this.iframe.contentDocument ||
                this.iframe.contentWindow.document;

            if (!iframeDoc) {
                console.warn('Cannot access iframe document');
                return;
            }

            // Remove existing custom CSS
            const existingStyle = iframeDoc.getElementById('customizer-css');
            if (existingStyle) {
                existingStyle.remove();
            }

            // Add new CSS
            const style = iframeDoc.createElement('style');
            style.id = 'customizer-css';
            style.textContent = css;
            iframeDoc.head.appendChild(style);
        } catch (error) {
            console.error('Failed to inject CSS:', error);
        }
    }

    /**
     * Save theme settings
     */
    async saveSettings() {
        const saveBtn = document.getElementById('save-btn');
        if (!saveBtn) return;

        const originalHTML = saveBtn.innerHTML;
        saveBtn.innerHTML =
            '<i class="ri-loader-4-line spin me-1"></i> Saving...';
        saveBtn.disabled = true;

        try {
            const formData = this.collectFormData();

            const response = await fetch(window.customizerRoutes.update, {
                method: 'POST',
                body: formData,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                },
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || data.error || 'Save failed');
            }

            if (data.success) {
                this.hasUnsavedChanges = false;
                this.showNotification(
                    'Settings saved successfully!',
                    'success',
                );

                // Refresh preview to show saved changes
                this.refreshPreview();
            } else {
                throw new Error(data.message || 'Failed to save settings');
            }
        } catch (error) {
            if (isAuthExpiredError(error)) {
                return;
            }
            console.error('Save error:', error);
            this.showNotification('Failed to save: ' + error.message, 'error');
        } finally {
            saveBtn.innerHTML = originalHTML;
            saveBtn.disabled = false;
        }
    }

    /**
     * Reset settings to defaults
     */
    async resetSettings() {
        if (
            !confirm(
                'Are you sure you want to reset all settings to defaults? This action cannot be undone.',
            )
        ) {
            return;
        }

        try {
            const response = await fetch(window.customizerRoutes.reset, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                },
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || data.error || 'Reset failed');
            }

            if (data.success) {
                this.showNotification(
                    'Settings reset successfully!',
                    'success',
                );
                setTimeout(() => location.reload(), 1000);
            } else {
                throw new Error(data.message || 'Failed to reset settings');
            }
        } catch (error) {
            if (isAuthExpiredError(error)) {
                return;
            }
            console.error('Reset error:', error);
            this.showNotification('Failed to reset: ' + error.message, 'error');
        }
    }

    /**
     * Export settings to JSON file
     */
    async exportSettings() {
        try {
            const response = await fetch(window.customizerRoutes.export, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                },
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || data.error || 'Export failed');
            }

            if (data.success) {
                this.downloadFile(data.filename, data.data);
                this.showNotification(
                    'Settings exported successfully!',
                    'success',
                );
            } else {
                throw new Error(data.message || 'Failed to export settings');
            }
        } catch (error) {
            if (isAuthExpiredError(error)) {
                return;
            }
            console.error('Export error:', error);
            this.showNotification(
                'Failed to export: ' + error.message,
                'error',
            );
        }
    }

    /**
     * Import settings from JSON file
     */
    async importSettings() {
        const fileInput = document.getElementById('settings_file');

        if (!fileInput || !fileInput.files || !fileInput.files[0]) {
            this.showNotification('Please select a file to import', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('settings_file', fileInput.files[0]);

        try {
            const response = await fetch(window.customizerRoutes.import, {
                method: 'POST',
                body: formData,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.getCsrfToken(),
                },
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || data.error || 'Import failed');
            }

            if (data.success) {
                this.hideImportModal();
                this.showNotification(
                    'Settings imported successfully!',
                    'success',
                );
                setTimeout(() => location.reload(), 1000);
            } else {
                throw new Error(data.message || 'Failed to import settings');
            }
        } catch (error) {
            if (isAuthExpiredError(error)) {
                return;
            }
            console.error('Import error:', error);
            this.showNotification(
                'Failed to import: ' + error.message,
                'error',
            );
        }
    }

    /**
     * Change preview device mode
     */
    changePreviewDevice(device) {
        // Update active button
        document.querySelectorAll('.device-preview').forEach((btn) => {
            btn.classList.remove('active');
        });

        const activeBtn = document.querySelector(`[data-device="${device}"]`);
        if (activeBtn) {
            activeBtn.classList.add('active');
        }

        // Update preview container
        this.previewContainer.className = 'preview-container';
        if (device !== 'desktop') {
            this.previewContainer.classList.add(device + '-view');
        }

        // Reset inline styles so desktop view can expand fully
        this.resetPreviewWrapperStyles();

        // Recalculate dimensions after device change for non-desktop layouts
        setTimeout(() => {
            this.updatePreviewDimensions();
        }, 50);
    }

    /**
     * Refresh preview iframe
     */
    refreshPreview() {
        if (this.iframe) {
            const refreshBtn = document.getElementById('refresh-preview');
            const currentSrc = this.iframe.src;

            // Show loading state on button
            if (refreshBtn) {
                refreshBtn.disabled = true;
                refreshBtn.innerHTML = '<i class="ri-loader-4-line spin"></i>';
            }

            // Show loading overlay on preview container
            this.previewContainer.classList.add('loading');

            // Clear iframe to show blank with loading
            this.iframe.src = 'about:blank';

            // Listen for iframe load to remove loading state
            const onLoad = () => {
                if (refreshBtn) {
                    refreshBtn.disabled = false;
                    refreshBtn.innerHTML = '<i class="ri-refresh-line"></i>';
                }
                this.previewContainer.classList.remove('loading');
                this.iframe.removeEventListener('load', onLoad);
            };

            // Small delay then load the actual URL
            setTimeout(() => {
                this.iframe.addEventListener('load', onLoad);
                this.iframe.src = currentSrc;
            }, 50);
        }
    }

    /**
     * Show import modal
     */
    showImportModal() {
        const modal = new bootstrap.Modal(
            document.getElementById('importModal'),
        );
        modal.show();
    }

    /**
     * Hide import modal
     */
    hideImportModal() {
        const modalElement = document.getElementById('importModal');
        const modal = bootstrap.Modal.getInstance(modalElement);
        if (modal) {
            modal.hide();
        }
    }

    /**
     * Collect form data including proper checkbox handling
     */
    collectFormData() {
        const formData = new FormData();
        const inputs = this.form.querySelectorAll('input, select, textarea');

        inputs.forEach((input) => {
            if (input.type === 'checkbox') {
                // For checkboxes, explicitly set true/false
                formData.set(input.name, input.checked ? 'true' : 'false');
            } else if (
                input.type !== 'hidden' ||
                !this.form.querySelector(
                    `input[type="checkbox"][name="${input.name}"]`,
                )
            ) {
                // For other inputs, add normally (skip hidden fields paired with checkboxes)
                if (input.name) {
                    formData.set(input.name, input.value);
                }
            }
        });

        return formData;
    }

    /**
     * Get CSRF token from meta tag
     */
    getCsrfToken() {
        const token = document.querySelector('meta[name="csrf-token"]');
        return token ? token.content : '';
    }

    /**
     * Download file helper
     */
    downloadFile(filename, content) {
        const blob = new Blob([content], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    /**
     * Show notification message
     */
    showNotification(message, type = 'info') {
        const alertClass = type === 'error' ? 'danger' : type;
        const notification = document.createElement('div');
        notification.className = `alert alert-${alertClass} alert-dismissible fade show position-fixed`;
        notification.style.cssText =
            'top: 80px; right: 20px; z-index: 9999; min-width: 300px; max-width: 500px;';
        notification.innerHTML = `
            <i class="ri-${type === 'success' ? 'check' : type === 'error' ? 'error-warning' : 'information'}-line me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        document.body.appendChild(notification);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
    }

    /**
     * Warn before leaving with unsaved changes
     */
    setupBeforeUnloadWarning() {
        window.addEventListener('beforeunload', (e) => {
            if (this.hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue =
                    'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });
    }
}

/**
 * Image upload functions (global for onclick handlers)
 * Uses the media library picker for image selection
 */
window.selectImage = function (fieldId) {
    // Check if media picker is available
    if (typeof window.openMediaPicker !== 'function') {
        console.error(
            'Media picker not available. Make sure media-modal component is included.',
        );
        return;
    }

    window.openMediaPicker({
        mode: 'single',
        type: 'image',
        title: 'Select Image',
        onSelect: function (media) {
            const selectedMedia = Array.isArray(media) ? media[0] : media;
            if (!selectedMedia) return;

            const hiddenInput = document.getElementById(fieldId);
            const preview = document.getElementById('preview-' + fieldId);
            const removeBtn = document.getElementById('remove-' + fieldId);

            if (hiddenInput && preview) {
                hiddenInput.value = selectedMedia.url;
                preview.innerHTML = `<img src="${selectedMedia.url}" alt="Preview" class="img-fluid rounded" style="max-height: 150px;">`;

                // Show remove button
                if (removeBtn) {
                    removeBtn.style.display = '';
                }

                // Trigger change event for live preview
                hiddenInput.dispatchEvent(
                    new Event('input', { bubbles: true }),
                );
            }
        },
        onCancel: function () {
            // User cancelled selection
        },
    });
};

window.removeImage = function (fieldId) {
    const hiddenInput = document.getElementById(fieldId);
    const preview = document.getElementById('preview-' + fieldId);
    const removeBtn = document.getElementById('remove-' + fieldId);

    if (hiddenInput && preview) {
        hiddenInput.value = '';
        preview.innerHTML = `
            <div class="text-body-secondary py-3">
                <i class="ri-image-add-line" style="font-size: 2.5rem;"></i>
                <p class="mb-0 mt-2 small">Click to select image</p>
            </div>
        `;

        // Hide remove button
        if (removeBtn) {
            removeBtn.style.display = 'none';
        }

        // Trigger change event for live preview
        hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
    }
};

/**
 * Initialize customizer when DOM is ready
 */
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('theme-customizer')) {
        installAuthExpiryInterceptor();

        // Set up routes (these should be set in the blade template)
        if (!window.customizerRoutes) {
            console.error(
                'customizerRoutes not defined! Set them in the blade template.',
            );
        }

        new ThemeCustomizer();
    }
});

// Add spinning animation for loader icons
const style = document.createElement('style');
style.textContent = `
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .spin {
        animation: spin 1s linear infinite;
        display: inline-block;
    }
`;
document.head.appendChild(style);
