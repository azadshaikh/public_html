/**
 * Menu Editor - Entry Point
 *
 * A modular menu editor with native drag-and-drop support.
 * No external dependencies (SortableJS removed).
 */

import { MenuEditor } from './MenuEditor.js';

// Initialize when DOM is ready
function initMenuEditor() {
    if (document.querySelector('#menu-builder')) {
        console.log('[MenuEditor] Initializing...');
        window.menuEditor = new MenuEditor({
            menuUrl: window.menuUrl,
            settings: window.menuSettings,
        });
        console.log('[MenuEditor] Initialized successfully');
    }
}

// Handle both cases: DOM already loaded or still loading
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMenuEditor);
} else {
    initMenuEditor();
}

// Warn about unsaved changes
window.addEventListener('beforeunload', function (event) {
    if (window.menuEditor?.hasChanges) {
        const message = 'You have unsaved changes. Are you sure you want to leave?';
        event.preventDefault();
        event.returnValue = message;
        return message;
    }
});

export { MenuEditor };
