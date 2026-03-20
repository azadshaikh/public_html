/**
 * Iframe synchronization controller.
 *
 * Syncs the AST to the iframe DOM by injecting rendered HTML
 * into the [data-astero-enabled] content area.
 * Also manages style/script injection and DOM↔AST lookups.
 */

import { buildEffectivePageCss, renderPageContent } from './ast-to-html';
import type { AstNodeId, AstNodeMap } from './ast-types';

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------

const CONTENT_AREA_SELECTOR = '[data-astero-enabled]';
const BUILDER_STYLES_ID = 'builder-ast-styles';
const BUILDER_SCRIPTS_ID = 'builder-ast-scripts';

// ---------------------------------------------------------------------------
// Sync content to iframe
// ---------------------------------------------------------------------------

/**
 * Inject the rendered AST content into the iframe's content area.
 * Returns true if content area was found and updated.
 */
export function syncAstToIframe(
    iframeDoc: Document,
    nodes: AstNodeMap,
    rootNodeId: AstNodeId,
    css: string,
    js: string,
): boolean {
    const contentArea = iframeDoc.querySelector<HTMLElement>(CONTENT_AREA_SELECTOR);

    if (!contentArea) {
        return false;
    }

    // Render AST to HTML
    const html = renderPageContent(nodes, rootNodeId);

    // Only update if content changed (avoid unnecessary reflows)
    if (contentArea.innerHTML !== html) {
        contentArea.innerHTML = html;

        // Force AOS elements to be visible in the builder iframe.
        // AOS sets opacity:0 on [data-aos] elements until they scroll into view,
        // but in the builder context we need them always visible.
        neutralizeAosElements(contentArea);
    }

    // Sync styles
    syncStyles(iframeDoc, css, nodes);

    // Sync scripts (don't re-inject on every update — only when explicitly changed)
    syncScripts(iframeDoc, js, nodes);

    return true;
}

/**
 * Inject builder styles into <head>.
 */
function syncStyles(
    iframeDoc: Document,
    customCss: string,
    nodes: AstNodeMap,
): void {
    // Collect per-node CSS from custom data
    const nodeCss: string[] = [];

    for (const node of Object.values(nodes)) {
        const css = node.custom.css;

        if (typeof css === 'string' && css.trim()) {
            nodeCss.push(css.trim());
        }
    }

    const allCss = [...nodeCss, buildEffectivePageCss(nodes, customCss)].filter(Boolean).join('\n\n');

    let styleEl = iframeDoc.getElementById(BUILDER_STYLES_ID) as HTMLStyleElement | null;

    if (!styleEl) {
        styleEl = iframeDoc.createElement('style');
        styleEl.id = BUILDER_STYLES_ID;
        iframeDoc.head.appendChild(styleEl);
    }

    if (styleEl.textContent !== allCss) {
        styleEl.textContent = allCss;
    }
}

/**
 * Inject builder scripts into <body>.
 */
function syncScripts(
    iframeDoc: Document,
    customJs: string,
    nodes: AstNodeMap,
): void {
    const nodeJs: string[] = [];

    for (const node of Object.values(nodes)) {
        const js = node.custom.js;

        if (typeof js === 'string' && js.trim()) {
            nodeJs.push(js.trim());
        }
    }

    const allJs = [...nodeJs, customJs].filter(Boolean).join('\n\n');

    // Remove old and re-create (scripts don't re-execute on textContent change)
    const oldScript = iframeDoc.getElementById(BUILDER_SCRIPTS_ID);

    if (oldScript) {
        oldScript.remove();
    }

    if (allJs) {
        const scriptEl = iframeDoc.createElement('script');
        scriptEl.id = BUILDER_SCRIPTS_ID;
        scriptEl.textContent = allJs;
        iframeDoc.body.appendChild(scriptEl);
    }
}

// ---------------------------------------------------------------------------
// AOS neutralization
// ---------------------------------------------------------------------------

/**
 * Force AOS-animated elements to be visible inside the builder iframe.
 *
 * AOS CSS applies `opacity: 0` and transforms to `[data-aos]` elements,
 * then the AOS JS adds `.aos-animate` when they scroll into view.
 * After content injection, AOS doesn't re-observe new elements, so they
 * stay invisible. Adding `aos-init aos-animate` forces the "animated-in"
 * state, making them always visible during editing.
 */
function neutralizeAosElements(container: HTMLElement): void {
    const aosElements = container.querySelectorAll<HTMLElement>('[data-aos]');

    for (const el of aosElements) {
        el.classList.add('aos-init', 'aos-animate');
    }
}

// ---------------------------------------------------------------------------
// DOM ↔ AST lookups
// ---------------------------------------------------------------------------

/**
 * Given a DOM element inside the iframe, find the AST node ID it belongs to.
 */
export function getAstIdFromElement(el: HTMLElement): AstNodeId | null {
    const astEl = el.closest<HTMLElement>('[data-ast-id]');

    return astEl?.dataset.astId ?? null;
}

/**
 * Given an AST node ID, find the corresponding DOM element in the iframe.
 */
export function getElementByAstId(iframeDoc: Document, nodeId: AstNodeId): HTMLElement | null {
    return iframeDoc.querySelector<HTMLElement>(`[data-ast-id="${nodeId}"]`);
}

/**
 * Get the bounding rect of an AST node's DOM element, relative to the iframe document.
 */
export function getNodeRect(
    iframeDoc: Document,
    nodeId: AstNodeId,
): DOMRect | null {
    const el = getElementByAstId(iframeDoc, nodeId);

    return el?.getBoundingClientRect() ?? null;
}

/**
 * Get the content area element.
 */
export function getContentArea(iframeDoc: Document): HTMLElement | null {
    return iframeDoc.querySelector<HTMLElement>(CONTENT_AREA_SELECTOR);
}
