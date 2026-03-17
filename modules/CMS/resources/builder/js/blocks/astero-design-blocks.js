/*
 * Astero Design Blocks - Consolidated dynamic loader for Blocks, Sections, and Components
 * Loads all design elements from AJAX requests with proper caching and initialization
 */

// Flags to track loading state
let blocksLoaded = false;
let sectionsLoaded = false;
let componentsLoaded = false;

// Promise tracking for preventing duplicate requests
let blocksLoadPromise = null;
let sectionsLoadPromise = null;
let componentsLoadPromise = null;

// Initialize Astero objects if not already defined
if (typeof Astero !== 'undefined') {
    // Initialize ComponentsGroup, SectionsGroup, and BlocksGroup if not already defined
    if (!Astero.ComponentsGroup) Astero.ComponentsGroup = {};
    if (!Astero.SectionsGroup) Astero.SectionsGroup = {};
    if (!Astero.BlocksGroup) Astero.BlocksGroup = {};

    // Initialize Blocks object if not defined
    if (!Astero.Blocks) {
        Astero.Blocks = {
            _blocks: {},

            get: function (type) {
                return this._blocks[type];
            },

            add: function (type, data) {
                data.type = type;
                this._blocks[type] = data;
            },
        };
    }

    // Initialize Sections object if not defined
    if (!Astero.Sections) {
        Astero.Sections = {
            _sections: {},

            get: function (type) {
                return this._sections[type];
            },

            add: function (type, data) {
                data.type = type;
                this._sections[type] = data;
            },
        };
    }
}

/**
 * Load all design elements (blocks, sections, components) from single AJAX call
 * Uses 'blocks', 'sections', and 'components' arrays from response data
 */
function loadAllDesignElements() {
    if (blocksLoadPromise) {
        return blocksLoadPromise;
    }

    blocksLoadPromise = new Promise((resolve, reject) => {
        fetch(window.blocksurl)
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    let loadedCount = 0;

                    // Load blocks if present
                    if (data.blocks) {
                        Object.keys(data.blocks).forEach((category) => {
                            const blocks = data.blocks[category];
                            const blockIds = [];

                            blocks.forEach((block) => {
                                Astero.Blocks.add(block.id, {
                                    id: block.id,
                                    name: block.name,
                                    image: block.image,
                                    html: block.html,
                                    css: block.css || '',
                                    js: block.js || '',
                                });
                                blockIds.push(block.id);
                            });

                            // Add to blocks group (merge with existing if any)
                            if (Astero.BlocksGroup[category]) {
                                Astero.BlocksGroup[category] = [
                                    ...Astero.BlocksGroup[category],
                                    ...blockIds,
                                ];
                            } else {
                                Astero.BlocksGroup[category] = blockIds;
                            }
                        });
                        blocksLoaded = true;
                        loadedCount++;
                    }

                    // Load sections if present
                    if (data.sections) {
                        Object.keys(data.sections).forEach((category) => {
                            const sections = data.sections[category];
                            const sectionIds = [];

                            sections.forEach((section) => {
                                Astero.Sections.add(section.id, {
                                    id: section.id,
                                    name: section.name,
                                    image: section.image,
                                    html: section.html,
                                    css: section.css || '',
                                    js: section.js || '',
                                });
                                sectionIds.push(section.id);
                            });

                            // Add to sections group (merge with existing if any)
                            if (Astero.SectionsGroup[category]) {
                                Astero.SectionsGroup[category] = [
                                    ...Astero.SectionsGroup[category],
                                    ...sectionIds,
                                ];
                            } else {
                                Astero.SectionsGroup[category] = sectionIds;
                            }
                        });
                        sectionsLoaded = true;
                        loadedCount++;
                    }

                    // Load components if present
                    if (data.components) {
                        Object.keys(data.components).forEach((category) => {
                            const components = data.components[category];
                            const componentIds = [];

                            components.forEach((component) => {
                                // Add component to Astero.Components if it exists
                                if (
                                    Astero.Components &&
                                    Astero.Components.add
                                ) {
                                    Astero.Components.add(component.id, {
                                        id: component.id,
                                        name: component.name,
                                        image: component.image,
                                        html: component.html,
                                        css: component.css || '',
                                        js: component.js || '',
                                        properties: component.properties || [],
                                    });
                                }
                                componentIds.push(component.id);
                            });

                            // Add to components group (merge with existing if any)
                            if (Astero.ComponentsGroup[category]) {
                                Astero.ComponentsGroup[category] = [
                                    ...Astero.ComponentsGroup[category],
                                    ...componentIds,
                                ];
                            } else {
                                Astero.ComponentsGroup[category] = componentIds;
                            }
                        });
                        componentsLoaded = true;
                        loadedCount++;
                    }

                    resolve();
                } else {
                    console.error(
                        'Failed to load design elements:',
                        data.message || 'Request failed',
                    );
                    reject(new Error(data.message || 'Request failed'));
                }
            })
            .catch((error) => {
                console.error('Error loading design elements:', error);
                reject(error);
            });
    });

    return blocksLoadPromise;
}

/**
 * Load blocks dynamically - now uses the unified endpoint
 */
function loadBlocksFromAjax() {
    return loadAllDesignElements();
}

/**
 * Load sections dynamically - now uses the unified endpoint
 */
function loadSectionsFromAjax() {
    return loadAllDesignElements();
}

/**
 * Load components dynamically - now uses the unified endpoint
 */
function loadComponentsFromAjax() {
    return loadAllDesignElements();
}

// Override Astero functions to integrate with AJAX loading
// NOTE: These overrides are DISABLED - the Registry/Provider pattern now handles loading.
// DatabaseProvider fetches from window.blocksurl on-demand.
// Keeping the structure commented for reference in case legacy integration is needed.
/*
if (typeof Astero !== 'undefined' && Astero.Builder) {
    // Override loadBlockGroups function
    if (Astero.Builder.loadBlockGroups) {
        const originalLoadBlockGroups = Astero.Builder.loadBlockGroups;

        Astero.Builder.loadBlockGroups = function () {
            if (blocksLoaded) {
                // Blocks are already loaded, call original function
                return originalLoadBlockGroups.call(this);
            } else {
                // Blocks not loaded yet, load them first
                return loadBlocksFromAjax().then(() => {
                    return originalLoadBlockGroups.call(this);
                });
            }
        };
    }

    // Override loadSectionGroups function
    if (Astero.Builder.loadSectionGroups) {
        const originalLoadSectionGroups = Astero.Builder.loadSectionGroups;

        Astero.Builder.loadSectionGroups = function () {
            if (sectionsLoaded) {
                // Sections are already loaded, call original function
                return originalLoadSectionGroups.call(this);
            } else {
                // Sections not loaded yet, load them first
                return loadSectionsFromAjax().then(() => {
                    return originalLoadSectionGroups.call(this);
                });
            }
        };
    }

    // Override loadComponentGroups function if it exists
    if (Astero.Builder.loadComponentGroups) {
        const originalLoadComponentGroups = Astero.Builder.loadComponentGroups;

        Astero.Builder.loadComponentGroups = function () {
            if (componentsLoaded) {
                // Components are already loaded, call original function
                return originalLoadComponentGroups.call(this);
            } else {
                // Components not loaded yet, load them first
                return loadComponentsFromAjax().then(() => {
                    return originalLoadComponentGroups.call(this);
                });
            }
        };
    }
}
*/

// Initialize design elements when the script is loaded
// NOTE: Auto-preloading is DISABLED to avoid duplicate fetches.
// The Registry/Provider pattern (builder-registry.js + provider-*.js) now handles
// lazy loading of blocks/sections. This file only provides the legacy Astero.Blocks
// and Astero.Sections storage objects for drag-drop compatibility.
//
// To manually preload (e.g., for legacy integrations), call:
//   window.AsteroDesignBlocks.loadAll()

function initializeDesignElements() {
    // Auto-preloading disabled - Registry/Provider handles this now
    // loadAllDesignElements().catch((error) => {
    //     console.error('Failed to preload design elements:', error);
    // });
    console.log(
        '[AsteroDesignBlocks] Legacy storage initialized. Blocks loaded on-demand via Registry.',
    );
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function () {
    initializeDesignElements();
});

// Also initialize immediately if DOM is already loaded
if (document.readyState === 'loading') {
    // DOM is still loading, wait for DOMContentLoaded
} else {
    // DOM is already loaded, initialize now
    initializeDesignElements();
}

// Export functions for external use if needed
window.AsteroDesignBlocks = {
    loadBlocks: loadBlocksFromAjax,
    loadSections: loadSectionsFromAjax,
    loadComponents: loadComponentsFromAjax,
    loadAll: loadAllDesignElements,
    isBlocksLoaded: () => blocksLoaded,
    isSectionsLoaded: () => sectionsLoaded,
    isComponentsLoaded: () => componentsLoaded,
};
