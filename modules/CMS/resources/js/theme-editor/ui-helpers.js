/**
 * UI Helpers
 *
 * Utility functions for UI operations including toasts, icons, and path manipulation.
 */

/**
 * Get file icon class based on extension
 * @param {string} extension - File extension
 * @returns {string} Icon class name
 */
export function getFileIconClass(extension) {
    const icons = {
        twig: 'ri-leaf-line file-twig',
        tpl: 'ri-html5-line file-tpl',
        html: 'ri-html5-line file-tpl',
        css: 'ri-css3-line file-css',
        scss: 'ri-css3-line file-scss',
        sass: 'ri-css3-line file-scss',
        js: 'ri-javascript-line file-js',
        json: 'ri-braces-line file-json',
        md: 'ri-markdown-line file-md',
        txt: 'ri-file-text-line',
        xml: 'ri-code-line',
        svg: 'ri-image-line',
    };
    return icons[extension] || 'ri-file-line';
}

/**
 * Get path depth (number of slashes)
 * @param {string} path - File/folder path
 * @returns {number}
 */
export function getDepth(path) {
    return (path.match(/\//g) || []).length;
}

/**
 * Get parent directory path
 * @param {string} path - File/folder path
 * @returns {string}
 */
export function getParentPath(path) {
    const parts = path.split('/');
    parts.pop();
    return parts.join('/') || '/';
}

/**
 * Check if file is an image
 * @param {string} path - File path
 * @returns {boolean}
 */
export function isImageFile(path) {
    const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'ico', 'bmp'];
    const extension = path.split('.').pop().toLowerCase();
    return imageExtensions.includes(extension);
}

/**
 * Show toast notification
 * @param {string} message - Toast message
 * @param {string} type - Toast type ('success' or 'error')
 */
export function showToast(message, type = 'success') {
    // Use requestAnimationFrame to ensure DOM is ready
    requestAnimationFrame(() => {
        const container = document.getElementById('toast-container');
        if (!container) {
            console.warn('Toast container not found, message:', message);
            return;
        }

        const toast = document.createElement('div');
        toast.className = `editor-toast ${type}`;
        toast.innerHTML = `
            <i class="ri-${type === 'success' ? 'check' : 'error-warning'}-line"></i>
            <span>${message}</span>
        `;
        container.appendChild(toast);

        // Add show class for animation
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    });
}

/**
 * Create sidebar resize handler
 * @param {Event} event - Mouse event
 * @param {Object} component - Alpine component instance
 */
export function startResize(event, component) {
    component.isResizing = true;
    const startX = event.clientX;
    const startWidth = component.sidebarWidth;

    const onMouseMove = (e) => {
        const delta = e.clientX - startX;
        component.sidebarWidth = Math.max(180, Math.min(400, startWidth + delta));
    };

    const onMouseUp = () => {
        component.isResizing = false;
        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);
        component.saveState();
    };

    document.addEventListener('mousemove', onMouseMove);
    document.addEventListener('mouseup', onMouseUp);
}

/**
 * Create keyboard event handlers
 * @param {Object} component - Alpine component instance
 * @param {Object} tabManager - Tab manager instance
 * @param {Object} fileOps - File operations instance
 * @param {Object} commandPalette - Command palette instance
 * @returns {Function} Keyboard handler function
 */
export function createKeyboardHandlers(component, tabManager, fileOps, commandPalette) {
    return function handleKeydown(event) {
        // Ctrl+S - Save
        if ((event.ctrlKey || event.metaKey) && event.key === 's') {
            event.preventDefault();
            fileOps.saveCurrentFile(component);
        }

        // Ctrl+P - Command Palette
        if ((event.ctrlKey || event.metaKey) && event.key === 'p') {
            event.preventDefault();
            commandPalette.openCommandPalette(component);
        }

        // Ctrl+W - Close Tab
        if ((event.ctrlKey || event.metaKey) && event.key === 'w') {
            event.preventDefault();
            if (component.activeTab) {
                tabManager.closeTab(component.activeTab, component, null);
            }
        }

        // Escape - Close panels
        if (event.key === 'Escape') {
            if (component.showDiff) {
                component.showDiff = false;
            } else if (component.showRevisions) {
                component.showRevisions = false;
                component.selectedRevision = null;
            } else if (component.showCommandPalette) {
                commandPalette.closeCommandPalette(component);
            }
            if (component.contextMenu?.show) {
                component.contextMenu.show = false;
            }
        }
    };
}
