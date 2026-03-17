/**
 * Astero Builder - Panel Loading
 *
 * Handles loading component, section, and block panels:
 * - loadControlGroups
 * - loadSectionGroups
 * - loadBlockGroups
 */

// Extend Astero.Builder with panel loading operations
Object.assign(Astero.Builder, {
    /**
     * Load component groups into the controls panel
     */
    loadControlGroups: function () {
        let componentsList = document.querySelectorAll('.components-list');
        let item = {};
        let component = {};
        let count = 0;

        componentsList.forEach(function (list, i) {
            let type = list.dataset.type;
            let isChecked = type === 'addbox' ? 'checked' : '';
            list.replaceChildren();
            count++;

            for (const group in Astero.ComponentsGroup) {
                if (!Astero.ComponentsGroup.hasOwnProperty(group)) continue;

                list.append(
                    generateElements(
                        `<li class="header" data-section="${group}" data-search="">
                            <label class="header" for="${type}_comphead_${group}${count}">
                                ${group}<div class="header-arrow"><i class="ri-subtract-line icon-minus"></i><i class="ri-add-line icon-plus"></i></div>
                            </label>
                            <input class="header_check" type="checkbox" id="${type}_comphead_${group}${count}" ${isChecked}>
                            <ol></ol>
                        </li>`,
                    )[0],
                );

                let componentsSubList = list.querySelector(
                    'li[data-section="' + group + '"] ol',
                );
                const components = Astero.ComponentsGroup[group];

                for (const idx in components) {
                    if (!components.hasOwnProperty(idx)) continue;
                    const componentType = components[idx];
                    component = Astero.Components.get(componentType);

                    if (component) {
                        // Use icon (Remix Icon class) if available, otherwise fall back to image
                        let iconHtml = component.icon
                            ? `<div class="component-icon"><i class="${component.icon}"></i></div>`
                            : '';

                        item = generateElements(
                            `<li data-section="${group}" data-drag-type="component" data-type="${componentType}" data-search="${component.name.toLowerCase()}">
                                ${iconHtml}<span class="name">${component.name}</span>
                                <div class="add-section-btn" title="Add component"><i class="ri-add-line"></i></div>
                            </li>`,
                        )[0];

                        // Only use background image if no icon is set
                        if (!component.icon && component.image) {
                            item.style.backgroundImage =
                                'url(' +
                                Astero.imgBaseUrl +
                                component.image +
                                ')';
                            item.style.backgroundRepeat = 'no-repeat';
                        }

                        componentsSubList.append(item);
                    }
                }
            }
        });
    },

    /**
     * Load section groups into the sections panel
     */
    /**
     * Load section groups into the sections panel
     * Updated to use Astero.Registry (Phase 1.4)
     */
    loadSectionGroups: async function () {
        let sectionsList = document.querySelectorAll('.sections-list');
        let item = {};

        // Helper to format category name for display
        // - Preserves all-uppercase names (like "CTA", "FAQ", "API")
        // - Otherwise applies Title Case
        const formatCategoryName = (str) => {
            // If it's all uppercase or all uppercase with hyphens, keep it
            if (/^[A-Z0-9]+(-[A-Z0-9]+)*$/.test(str)) {
                return str;
            }
            // Otherwise, apply Title Case
            return str.replace(/\b\w/g, (c) => c.toUpperCase());
        };

        // Use a Map to deduplicate by lowercase key, but preserve original casing
        // Priority: prefer all-uppercase > original case from theme > builtin
        const categoryMap = new Map(); // key: lowercase, value: original cased name

        if (Astero.Registry) {
            for (const provider of Object.values(Astero.Registry.providers)) {
                try {
                    const cats = await provider.getCategories('section');
                    cats.forEach((c) => {
                        const key = c.toLowerCase();
                        const existing = categoryMap.get(key);
                        // Keep the version that's all-uppercase, or the first one we see
                        if (
                            !existing ||
                            (c === c.toUpperCase() &&
                                existing !== existing.toUpperCase())
                        ) {
                            categoryMap.set(key, c);
                        }
                    });
                } catch (e) {
                    console.error(
                        `Error loading categories from ${provider.name}`,
                        e,
                    );
                }
            }
        }

        // Also add any already in SectionsGroup as fallback
        if (Astero.SectionsGroup) {
            Object.keys(Astero.SectionsGroup).forEach((c) => {
                const key = c.toLowerCase();
                if (!categoryMap.has(key)) {
                    categoryMap.set(key, c);
                }
            });
        }

        // Sort by lowercase key but use original value
        const sortedCategories = Array.from(categoryMap.entries())
            .sort((a, b) => a[0].localeCompare(b[0]))
            .map(([key, value]) => ({ key, name: value }));

        for (const list of sectionsList) {
            let type = list.dataset.type;
            let isChecked = type === 'addbox' ? 'checked' : '';
            list.replaceChildren();

            for (const { key: group, name: originalName } of sortedCategories) {
                // Fetch blocks via Registry (Unified) - this already merges by priority
                const sections = await Astero.Registry.loadCategory(
                    'section',
                    group,
                );

                if (sections.length === 0) continue;

                // Sort sections: theme first, then database, then builtin
                const sortedSections = sections.sort((a, b) => {
                    const order = { theme: 0, database: 1, builtin: 2 };
                    return (order[a._source] ?? 99) - (order[b._source] ?? 99);
                });

                // Format the display name (preserves all-caps like CTA)
                const displayName = formatCategoryName(originalName);

                list.append(
                    generateElements(
                        `<li class="header" data-section="${group}" data-search="">
                            <label class="header" for="${type}_sectionhead_${group}">
                                ${displayName}<div class="header-arrow"><i class="ri-subtract-line icon-minus"></i><i class="ri-add-line icon-plus"></i></div>
                            </label>
                            <input class="header_check" type="checkbox" id="${type}_sectionhead_${group}" ${isChecked}>
                            <ol></ol>
                        </li>`,
                    )[0],
                );

                let sectionsSubList = list.querySelector(
                    'li[data-section="' + group + '"] ol',
                );

                sortedSections.forEach((section) => {
                    // Normalize image path
                    let image = section.image;
                    if (
                        image &&
                        image.indexOf('/') === -1 &&
                        image.indexOf('http') === -1
                    ) {
                        image = Astero.imgBaseUrl + image;
                    }

                    // Add 'no-preview' class if no image
                    const noPreviewClass = !image ? 'no-preview' : '';

                    item = generateElements(
                        `<li data-section="${group}" data-drag-type="section" data-type="${section.slug}" data-search="${section.name.toLowerCase()}" class="${noPreviewClass}">
                            <span class="name">${section.name}</span>
                            <div class="add-section-btn" title="Add section"><i class="ri-add-line"></i></div>
                        </li>`,
                    )[0];

                    if (image) {
                        Object.assign(item.style, {
                            backgroundImage: 'url(' + image + ')',
                            backgroundRepeat: 'no-repeat',
                        });
                    }

                    sectionsSubList.append(item);
                });
            }
        }
    },

    /**
     * Load block groups into the blocks panel
     * Updated to use Astero.Registry (Phase 1.4)
     */
    loadBlockGroups: async function () {
        let blocksList = document.querySelectorAll('.blocks-list');
        let item = {};

        // Helper to format category name for display
        // - Preserves all-uppercase names (like "CTA", "FAQ", "API")
        // - Otherwise applies Title Case
        const formatCategoryName = (str) => {
            if (/^[A-Z0-9]+(-[A-Z0-9]+)*$/.test(str)) {
                return str;
            }
            return str.replace(/\b\w/g, (c) => c.toUpperCase());
        };

        // Use a Map to deduplicate by lowercase key, but preserve original casing
        const categoryMap = new Map();

        if (Astero.Registry) {
            for (const provider of Object.values(Astero.Registry.providers)) {
                try {
                    const cats = await provider.getCategories('block');
                    cats.forEach((c) => {
                        const key = c.toLowerCase();
                        const existing = categoryMap.get(key);
                        if (
                            !existing ||
                            (c === c.toUpperCase() &&
                                existing !== existing.toUpperCase())
                        ) {
                            categoryMap.set(key, c);
                        }
                    });
                } catch (e) {
                    console.error(
                        `Error loading categories from ${provider.name}`,
                        e,
                    );
                }
            }
        }

        if (Astero.BlocksGroup) {
            Object.keys(Astero.BlocksGroup).forEach((c) => {
                const key = c.toLowerCase();
                if (!categoryMap.has(key)) {
                    categoryMap.set(key, c);
                }
            });
        }

        const sortedCategories = Array.from(categoryMap.entries())
            .sort((a, b) => a[0].localeCompare(b[0]))
            .map(([key, value]) => ({ key, name: value }));

        for (const list of blocksList) {
            let type = list.dataset.type;
            let isChecked = type === 'addbox' ? 'checked' : '';
            list.replaceChildren();

            for (const { key: group, name: originalName } of sortedCategories) {
                const blocks = await Astero.Registry.loadCategory(
                    'block',
                    group,
                );

                if (blocks.length === 0) continue;

                // Sort blocks: theme first, then database, then builtin
                const sortedBlocks = blocks.sort((a, b) => {
                    const order = { theme: 0, database: 1, builtin: 2 };
                    return (order[a._source] ?? 99) - (order[b._source] ?? 99);
                });

                // Format the display name (preserves all-caps like CTA)
                const displayName = formatCategoryName(originalName);

                list.append(
                    generateElements(
                        `<li class="header" data-section="${group}" data-search="">
                            <label class="header" for="${type}_blockhead_${group}">
                                ${displayName}<div class="header-arrow"><i class="ri-subtract-line icon-minus"></i><i class="ri-add-line icon-plus"></i></div>
                            </label>
                            <input class="header_check" type="checkbox" id="${type}_blockhead_${group}" ${isChecked}>
                            <ol></ol>
                        </li>`,
                    )[0],
                );

                let blocksSubList = list.querySelector(
                    'li[data-section="' + group + '"] ol',
                );

                sortedBlocks.forEach((block) => {
                    // Add 'no-preview' class if no image
                    const noPreviewClass = !block.image ? 'no-preview' : '';

                    item = generateElements(
                        `<li data-section="${group}" data-drag-type="block" data-type="${block.slug}" data-search="${block.name.toLowerCase()}" class="${noPreviewClass}">
                            <span class="name">${block.name}</span>
                            <div class="add-section-btn" title="Add block"><i class="ri-add-line"></i></div>
                        </li>`,
                    )[0];

                    if (block.image) {
                        Object.assign(item.style, {
                            backgroundImage: 'url(' + block.image + ')',
                            backgroundRepeat: 'no-repeat',
                        });
                    }

                    blocksSubList.append(item);
                });
            }
        }
    },
});
