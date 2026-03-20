/**
 * Preview iframe interaction system.
 *
 * Injects hover highlights, selection outlines, floating toolbars on selected
 * sections, and "+" insert buttons between sections — all rendered inside the
 * iframe document so they scroll with content and scale with device mode.
 */

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export type PreviewInteractionCallbacks = {
    onSelectItem: (itemId: string) => void;
    onSelectElement: (itemId: string, path: number[]) => void;
    onMoveItem: (itemId: string, direction: 'up' | 'down') => void;
    onDuplicateItem: (itemId: string) => void;
    onRemoveItem: (itemId: string) => void;
    onInsertAt: (index: number) => void;
    onDragStart: (itemId: string) => void;
    onDragEnd: () => void;
};

export type PreviewInteractionState = {
    selectedItemId: string | null;
    selectedElementPath: number[];
    itemCount: number;
};

// ---------------------------------------------------------------------------
// CSS injected into the preview iframe
// ---------------------------------------------------------------------------

const INTERACTION_STYLE_ID = 'builder-preview-interactions';

function getInteractionCss(): string {
    return `
        /* ── Section wrapper base ── */
        section[data-builder-item] {
            position: relative;
            cursor: pointer;
            transition: outline-color 150ms ease, box-shadow 150ms ease;
            outline: 2px solid transparent;
            outline-offset: -2px;
        }

        /* ── Hover: blue dashed border ── */
        section[data-builder-item]:hover {
            outline: 2px dashed rgba(59, 130, 246, 0.5);
        }

        /* ── Selected: solid blue border ── */
        section[data-builder-item][data-builder-selected="true"] {
            outline: 2px solid rgba(59, 130, 246, 0.7);
        }

        /* ── Element hover inside selected section ── */
        section[data-builder-item][data-builder-selected="true"] [data-builder-element-hover="true"] {
            outline: 1px dashed rgba(249, 115, 22, 0.5);
            outline-offset: 1px;
        }

        /* ── Selected element: orange solid ── */
        [data-builder-element-selected="true"] {
            outline: 2px solid rgba(249, 115, 22, 0.8) !important;
            outline-offset: 2px;
        }

        /* ── Section label badge (top-left) ── */
        .builder-section-label {
            position: absolute;
            top: -1px;
            left: 8px;
            transform: translateY(-100%);
            display: none;
            align-items: center;
            gap: 4px;
            background: rgba(59, 130, 246, 0.9);
            color: #fff;
            font-size: 10px;
            font-family: system-ui, -apple-system, sans-serif;
            font-weight: 600;
            line-height: 1;
            padding: 3px 8px;
            border-radius: 4px 4px 0 0;
            white-space: nowrap;
            pointer-events: none;
            z-index: 10000;
        }

        section[data-builder-item]:hover .builder-section-label,
        section[data-builder-item][data-builder-selected="true"] .builder-section-label {
            display: flex;
        }

        /* ── Floating toolbar (top-right of selected section) ── */
        .builder-section-toolbar {
            position: absolute;
            top: -1px;
            right: 8px;
            transform: translateY(-100%);
            display: none;
            align-items: center;
            gap: 2px;
            background: rgba(59, 130, 246, 0.9);
            padding: 2px 4px;
            border-radius: 4px 4px 0 0;
            z-index: 10001;
            font-family: system-ui, -apple-system, sans-serif;
        }

        section[data-builder-item][data-builder-selected="true"] .builder-section-toolbar {
            display: flex;
        }

        .builder-section-toolbar button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            padding: 0;
            margin: 0;
            border: none;
            border-radius: 3px;
            background: transparent;
            color: rgba(255, 255, 255, 0.85);
            cursor: pointer;
            transition: background-color 100ms ease, color 100ms ease;
        }

        .builder-section-toolbar button:hover {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .builder-section-toolbar button[data-action="remove"]:hover {
            background: rgba(239, 68, 68, 0.7);
            color: #fff;
        }

        .builder-section-toolbar button svg {
            width: 14px;
            height: 14px;
            pointer-events: none;
        }

        .builder-toolbar-separator {
            width: 1px;
            height: 16px;
            background: rgba(255, 255, 255, 0.3);
            margin: 0 2px;
        }

        /* ── Insert "+" button between sections ── */
        .builder-insert-button {
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            height: 24px;
            margin: -4px 0;
            z-index: 9999;
            opacity: 0;
            transition: opacity 150ms ease;
        }

        .builder-insert-button:hover {
            opacity: 1;
        }

        .builder-insert-button::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 18px;
            right: 18px;
            height: 1px;
            background: rgba(59, 130, 246, 0.4);
            pointer-events: none;
        }

        .builder-insert-button button {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            padding: 0;
            margin: 0;
            border: 2px solid rgba(59, 130, 246, 0.6);
            border-radius: 50%;
            background: #fff;
            color: rgb(59, 130, 246);
            cursor: pointer;
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 16px;
            font-weight: 400;
            line-height: 1;
            transition: background-color 100ms ease, border-color 100ms ease, transform 100ms ease;
        }

        .builder-insert-button button:hover {
            background: rgb(59, 130, 246);
            border-color: rgb(59, 130, 246);
            color: #fff;
            transform: scale(1.15);
        }

        .builder-insert-button button svg {
            width: 14px;
            height: 14px;
            pointer-events: none;
        }

        /* ── Drag ghost ── */
        section[data-builder-item].builder-dragging {
            opacity: 0.4;
            outline: 2px dashed rgba(59, 130, 246, 0.4) !important;
        }

        /* ── Drag handle cursor ── */
        .builder-section-toolbar button[data-action="drag"] {
            cursor: grab;
        }

        .builder-section-toolbar button[data-action="drag"]:active {
            cursor: grabbing;
        }

        /* ── Element tag badge ── */
        .builder-element-tag {
            position: absolute;
            transform: translateY(-100%);
            display: none;
            align-items: center;
            background: rgba(249, 115, 22, 0.85);
            color: #fff;
            font-size: 9px;
            font-family: system-ui, -apple-system, sans-serif;
            font-weight: 600;
            line-height: 1;
            padding: 2px 6px;
            border-radius: 3px 3px 0 0;
            white-space: nowrap;
            pointer-events: none;
            z-index: 10002;
        }

        [data-builder-element-selected="true"] > .builder-element-tag {
            display: flex;
        }
    `;
}

// ---------------------------------------------------------------------------
// SVG icon strings (inline, no external deps)
// ---------------------------------------------------------------------------

const SVG = {
    gripVertical: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="12" r="1"/><circle cx="9" cy="5" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="19" r="1"/></svg>`,
    arrowUp: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 7-7 7 7"/><path d="M12 19V5"/></svg>`,
    arrowDown: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="m19 12-7 7-7-7"/></svg>`,
    copy: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>`,
    trash: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>`,
    plus: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>`,
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function getPreviewRootElement(wrapper: HTMLElement): HTMLElement {
    return wrapper.firstElementChild instanceof HTMLElement
        ? wrapper.firstElementChild
        : wrapper;
}

function getElementPath(root: HTMLElement, target: HTMLElement): number[] {
    if (root === target) {
        return [];
    }

    const path: number[] = [];
    let current: HTMLElement | null = target;

    while (current !== null && current !== root) {
        const parent: HTMLElement | null = current.parentElement;

        if (parent === null) {
            return [];
        }

        const index = Array.from(parent.children).indexOf(current);

        if (index === -1) {
            return [];
        }

        path.unshift(index);
        current = parent;
    }

    return path;
}

function getElementByPath(root: HTMLElement, path: number[]): HTMLElement | null {
    let current: HTMLElement | null = root;

    for (const index of path) {
        if (current === null) {
            return null;
        }

        const child = current.children.item(index);
        current = child instanceof HTMLElement ? child : null;
    }

    return current;
}

// ---------------------------------------------------------------------------
// Section label + toolbar injection
// ---------------------------------------------------------------------------

function createSectionLabel(wrapper: HTMLElement): HTMLDivElement {
    const label = wrapper.ownerDocument.createElement('div');
    label.className = 'builder-section-label';
    label.textContent = wrapper.dataset.builderCategory || 'Section';

    return label;
}

function createSectionToolbar(
    wrapper: HTMLElement,
    itemId: string,
    itemIndex: number,
    itemCount: number,
    callbacks: PreviewInteractionCallbacks,
): HTMLDivElement {
    const doc = wrapper.ownerDocument;
    const toolbar = doc.createElement('div');
    toolbar.className = 'builder-section-toolbar';

    const makeButton = (action: string, title: string, svgHtml: string): HTMLButtonElement => {
        const btn = doc.createElement('button');
        btn.dataset.action = action;
        btn.title = title;
        btn.innerHTML = svgHtml;

        return btn;
    };

    // Drag handle
    const dragBtn = makeButton('drag', 'Drag to reorder', SVG.gripVertical);

    // Separator
    const sep1 = doc.createElement('div');
    sep1.className = 'builder-toolbar-separator';

    // Move up
    const upBtn = makeButton('moveUp', 'Move up', SVG.arrowUp);

    if (itemIndex <= 0) {
        upBtn.style.opacity = '0.35';
        upBtn.style.pointerEvents = 'none';
    }

    // Move down
    const downBtn = makeButton('moveDown', 'Move down', SVG.arrowDown);

    if (itemIndex >= itemCount - 1) {
        downBtn.style.opacity = '0.35';
        downBtn.style.pointerEvents = 'none';
    }

    // Separator
    const sep2 = doc.createElement('div');
    sep2.className = 'builder-toolbar-separator';

    // Duplicate
    const dupBtn = makeButton('duplicate', 'Duplicate', SVG.copy);

    // Delete
    const delBtn = makeButton('remove', 'Remove section', SVG.trash);

    toolbar.append(dragBtn, sep1, upBtn, downBtn, sep2, dupBtn, delBtn);

    // Event delegation on toolbar
    toolbar.addEventListener('mousedown', (event) => {
        const target = (event.target as HTMLElement).closest('button');

        if (!target) {
            return;
        }

        event.stopPropagation();
        event.preventDefault();

        const action = target.dataset.action;

        switch (action) {
            case 'moveUp':
                callbacks.onMoveItem(itemId, 'up');
                break;
            case 'moveDown':
                callbacks.onMoveItem(itemId, 'down');
                break;
            case 'duplicate':
                callbacks.onDuplicateItem(itemId);
                break;
            case 'remove':
                callbacks.onRemoveItem(itemId);
                break;
            case 'drag':
                callbacks.onDragStart(itemId);
                break;
        }
    });

    return toolbar;
}

function createInsertButton(
    doc: Document,
    index: number,
    callbacks: PreviewInteractionCallbacks,
): HTMLDivElement {
    const container = doc.createElement('div');
    container.className = 'builder-insert-button';
    container.dataset.insertIndex = String(index);

    const btn = doc.createElement('button');
    btn.title = 'Add section here';
    btn.innerHTML = SVG.plus;
    container.appendChild(btn);

    btn.addEventListener('click', (event) => {
        event.stopPropagation();
        event.preventDefault();
        callbacks.onInsertAt(index);
    });

    return container;
}

// ---------------------------------------------------------------------------
// Main setup/teardown
// ---------------------------------------------------------------------------

export function setupPreviewInteractions(
    iframeDocument: Document,
    state: PreviewInteractionState,
    callbacks: PreviewInteractionCallbacks,
): () => void {
    // 1. Inject styles
    let styleTag = iframeDocument.getElementById(INTERACTION_STYLE_ID) as HTMLStyleElement | null;

    if (styleTag === null) {
        styleTag = iframeDocument.createElement('style');
        styleTag.id = INTERACTION_STYLE_ID;
        styleTag.textContent = getInteractionCss();
        iframeDocument.head.appendChild(styleTag);
    }

    const wrappers = Array.from(
        iframeDocument.querySelectorAll<HTMLElement>('section[data-builder-item]'),
    );

    const cleanupFunctions: (() => void)[] = [];

    // 2. For each section wrapper, inject label, toolbar, and wire up events
    wrappers.forEach((wrapper, index) => {
        const itemId = wrapper.dataset.builderItem ?? '';
        const isSelected = itemId === state.selectedItemId;

        // Selection attribute
        wrapper.dataset.builderSelected = isSelected ? 'true' : 'false';

        // Clear previous overlays (in case of re-run)
        wrapper.querySelectorAll('.builder-section-label, .builder-section-toolbar').forEach((el) => el.remove());

        // Inject label badge
        const label = createSectionLabel(wrapper);
        wrapper.appendChild(label);

        // Inject toolbar (only renders visible for selected via CSS)
        const toolbar = createSectionToolbar(wrapper, itemId, index, state.itemCount, callbacks);
        wrapper.appendChild(toolbar);

        // Clear old element highlights
        wrapper.querySelectorAll('[data-builder-element-selected="true"]').forEach((el) => {
            el.removeAttribute('data-builder-element-selected');
        });

        wrapper.querySelectorAll('[data-builder-element-hover="true"]').forEach((el) => {
            el.removeAttribute('data-builder-element-hover');
        });

        wrapper.querySelectorAll('.builder-element-tag').forEach((el) => el.remove());

        // If selected, highlight the selected element
        if (isSelected) {
            const rootElement = getPreviewRootElement(wrapper);
            const selectedPreviewElement =
                getElementByPath(rootElement, state.selectedElementPath) ?? rootElement;
            selectedPreviewElement.setAttribute('data-builder-element-selected', 'true');

            // Add element tag badge
            const tagName = selectedPreviewElement.tagName.toLowerCase();
            const tagBadge = iframeDocument.createElement('div');
            tagBadge.className = 'builder-element-tag';
            tagBadge.textContent = tagName;

            if (selectedPreviewElement.style.position === '' || selectedPreviewElement.style.position === 'static') {
                selectedPreviewElement.style.position = 'relative';
            }

            selectedPreviewElement.appendChild(tagBadge);
        }
    });

    // 3. Inject "+" insert buttons between sections (and at the end)
    const shell = iframeDocument.querySelector('[data-astero-enabled]')
        ?? iframeDocument.querySelector('.builder-preview-shell');

    if (shell) {
        // Remove old insert buttons
        shell.querySelectorAll('.builder-insert-button').forEach((el) => el.remove());

        // Insert before each section and after the last
        const sectionElements = Array.from(shell.querySelectorAll(':scope > section[data-builder-item]'));

        sectionElements.forEach((section, index) => {
            const insertBtn = createInsertButton(iframeDocument, index, callbacks);
            section.parentNode?.insertBefore(insertBtn, section);
        });

        // After last section
        if (sectionElements.length > 0) {
            const lastInsert = createInsertButton(iframeDocument, sectionElements.length, callbacks);
            shell.appendChild(lastInsert);
        }
    }

    // 4. Click handler for selecting sections + elements
    const handlePreviewClick = (event: MouseEvent) => {
        event.preventDefault();
        event.stopPropagation();

        const target = event.target instanceof HTMLElement ? event.target : null;

        if (target === null) {
            return;
        }

        // Don't handle clicks on toolbar/insert buttons
        if (
            target.closest('.builder-section-toolbar') ||
            target.closest('.builder-insert-button') ||
            target.closest('.builder-section-label')
        ) {
            return;
        }

        const wrapper = target.closest<HTMLElement>('section[data-builder-item]');

        if (wrapper === null) {
            return;
        }

        const itemId = wrapper.dataset.builderItem;

        if (!itemId) {
            return;
        }

        const rootElement = getPreviewRootElement(wrapper);
        const selectedElementTarget = target.closest('*');
        const element = selectedElementTarget instanceof HTMLElement ? selectedElementTarget : rootElement;
        const path = getElementPath(rootElement, element);

        callbacks.onSelectElement(itemId, path);
    };

    // 5. Hover handler for element highlighting
    let lastHoveredElement: HTMLElement | null = null;

    const handlePreviewMouseover = (event: MouseEvent) => {
        const target = event.target instanceof HTMLElement ? event.target : null;

        if (target === null) {
            return;
        }

        // Don't highlight toolbar/insert elements
        if (
            target.closest('.builder-section-toolbar') ||
            target.closest('.builder-insert-button') ||
            target.closest('.builder-section-label') ||
            target.closest('.builder-element-tag')
        ) {
            return;
        }

        // Clear previous hover
        if (lastHoveredElement && lastHoveredElement !== target) {
            lastHoveredElement.removeAttribute('data-builder-element-hover');
            lastHoveredElement = null;
        }

        const wrapper = target.closest<HTMLElement>('section[data-builder-item]');

        if (!wrapper || wrapper.dataset.builderSelected !== 'true') {
            return;
        }

        // Only highlight elements inside selected section
        if (target !== wrapper && !target.closest('.builder-section-toolbar') && !target.closest('.builder-section-label')) {
            target.setAttribute('data-builder-element-hover', 'true');
            lastHoveredElement = target;
        }
    };

    const handlePreviewMouseout = (event: MouseEvent) => {
        const target = event.target instanceof HTMLElement ? event.target : null;

        if (target) {
            target.removeAttribute('data-builder-element-hover');
        }

        if (lastHoveredElement) {
            lastHoveredElement.removeAttribute('data-builder-element-hover');
            lastHoveredElement = null;
        }
    };

    // 6. Attach all listeners
    wrappers.forEach((wrapper) => {
        wrapper.addEventListener('click', handlePreviewClick, true);
    });

    iframeDocument.addEventListener('mouseover', handlePreviewMouseover, true);
    iframeDocument.addEventListener('mouseout', handlePreviewMouseout, true);

    // 7. Link protection — prevent navigation for all links in the iframe
    const handleLinkProtection = (event: Event) => {
        const target = event.target instanceof HTMLElement ? event.target : null;
        const anchor = target?.closest('a');

        if (anchor) {
            event.preventDefault();
            event.stopPropagation();
        }
    };

    iframeDocument.addEventListener('click', handleLinkProtection, false);

    // 8. Prevent form submissions
    const handleFormProtection = (event: Event) => {
        event.preventDefault();
        event.stopPropagation();
    };

    iframeDocument.addEventListener('submit', handleFormProtection, true);

    // 9. Return cleanup
    return () => {
        wrappers.forEach((wrapper) => {
            wrapper.removeEventListener('click', handlePreviewClick, true);
        });

        iframeDocument.removeEventListener('mouseover', handlePreviewMouseover, true);
        iframeDocument.removeEventListener('mouseout', handlePreviewMouseout, true);
        iframeDocument.removeEventListener('click', handleLinkProtection, false);
        iframeDocument.removeEventListener('submit', handleFormProtection, true);
    };
}
