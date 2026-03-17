/**
 * Widget Editor - Entry Point
 *
 * A modular widget editor with native drag-and-drop support.
 * No external dependencies (SortableJS removed).
 * Consistent with Menu Editor architecture.
 */

import { WidgetEditor } from './WidgetEditor.js';

// Initialize when DOM is ready
function initWidgetEditor() {
    if (document.getElementById('widget-editor-container')) {
        console.log('[WidgetEditor] Initializing...');
        window.widgetEditor = new WidgetEditor({
            widgetUrl: window.widgetUrl,
            widgetAreas: window.widgetAreas,
            availableWidgets: window.availableWidgets,
            currentWidgets: window.currentWidgets,
        });
        console.log('[WidgetEditor] Initialized successfully');
    }
}

// Handle both cases: DOM already loaded or still loading
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWidgetEditor);
} else {
    initWidgetEditor();
}

// Warn about unsaved changes
window.addEventListener('beforeunload', function (event) {
    if (window.widgetEditor?.hasUnsavedChanges()) {
        const message =
            'You have unsaved changes. Are you sure you want to leave?';
        event.preventDefault();
        event.returnValue = message;
        return message;
    }
});

export { WidgetEditor };
