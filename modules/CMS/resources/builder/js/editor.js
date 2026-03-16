/**
 * Astero Builder - Main Entry Point
 *
 * This file imports all builder modules in the correct order
 * and initializes the global Astero namespace.
 *
 * Build: Bundled via Vite for production use.
 */

// =============================================
// UTILITIES (must be first - provides globals)
// =============================================
// =============================================
// UTILITIES (must be first - provides globals)
// =============================================
import './core/builder-utils.js';

// =============================================
// CORE BUILDER
// =============================================
import './core/builder-core.js'; // Core initialization
import './core/builder-html.js'; // HTML get/set/save
import './core/builder-inject.js'; // CSS/JS injection
import './core/builder-links.js'; // Link protection
import './core/builder-panels.js'; // Panel loading (components/sections/blocks)
import './core/builder-selection.js'; // Highlight & selection system
import './core/builder-box.js'; // Selection box & actions
import './core/builder-editors.js'; // WYSIWYG editors
import './core/builder-components.js';
import './core/builder-gui.js';
import './core/builder-navigation.js';
import './core/builder-managers.js';
import './core/builder-undo.js';
import './core/builder-inputs.js';
import './core/builder-sections.js';

// =============================================
// REGISTRY & PROVIDERS
// =============================================
import './core/builder-registry.js';
import './core/providers/provider-builtin.js';
import './core/providers/provider-database.js';
import './core/providers/provider-theme.js';

// =============================================
// PLUGINS
// =============================================
import './plugins/plugin-media.js';
import './plugins/plugin-coloris.js';
import './plugins/plugin-google-fonts.js';
import './plugins/plugin-aos.js';

// =============================================
// COMPONENTS
// =============================================
import './components/components-common.js';
import './components/components-html.js';
import './components/components-elements.js';
import './components/components-bootstrap5.js';
import './components/components-widgets.js';

// =============================================
// SECTIONS
// =============================================
// import './sections/section.js'; // Moved to core/builder-sections.js
// import './sections/landing-sections.js'; // Lazy loaded by BuiltinProvider

// =============================================
// EMBEDS
// =============================================
import './components/oembed.js';
import './components/components-embeds.js';

// =============================================
// BLOCKS & SECTIONS
// =============================================
// import './blocks/bootstrap5/index.js'; // Lazy loaded by BuiltinProvider
import './blocks/astero-design-blocks.js';

// =============================================
// EDITOR PLUGINS
// =============================================
import './plugins/plugin-inline-editor.js';

console.log('[Builder] All modules loaded');
