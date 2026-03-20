/**
 * AST → HTML renderer.
 *
 * Converts the AST node tree into HTML strings for iframe injection.
 * Each rendered element gets a `data-ast-id` attribute for two-way binding.
 */

import {
    DEFAULT_TAG_MAP,
    type AstNode,
    type AstNodeId,
    type AstNodeMap,
} from './ast-types';

// ---------------------------------------------------------------------------
// Single node → HTML
// ---------------------------------------------------------------------------

function escapeHtml(text: string): string {
    return text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function styleObjectToString(styles: Record<string, string>): string {
    return Object.entries(styles)
        .filter(([, v]) => v !== '')
        .map(([k, v]) => `${kebabCase(k)}: ${v}`)
        .join('; ');
}

function kebabCase(str: string): string {
    return str.replace(/[A-Z]/g, (m) => `-${m.toLowerCase()}`);
}

/**
 * Self-closing tags that should not have a closing tag.
 */
const VOID_TAGS = new Set(['img', 'hr', 'br', 'input', 'meta', 'link']);

// ---------------------------------------------------------------------------
// Render node tree
// ---------------------------------------------------------------------------

/**
 * Render a single AST node and all its descendants to HTML.
 */
export function renderNodeToHtml(nodes: AstNodeMap, nodeId: AstNodeId): string {
    const node = nodes[nodeId];

    if (!node || node.hidden) {
        return '';
    }

    const tag = node.tagName ?? DEFAULT_TAG_MAP[node.type] ?? 'div';

    // Build attributes
    const attrs: string[] = [`data-ast-id="${node.id}"`];

    if (node.className) {
        attrs.push(`class="${escapeHtml(node.className)}"`);
    }

    const styleStr = styleObjectToString(node.styles);

    if (styleStr) {
        attrs.push(`style="${escapeHtml(styleStr)}"`);
    }

    // Type-specific attributes
    if (node.type === 'image') {
        if (node.props.src) {
            attrs.push(`src="${escapeHtml(String(node.props.src))}"`);
        }

        if (node.props.alt) {
            attrs.push(`alt="${escapeHtml(String(node.props.alt))}"`);
        }
    }

    if (node.type === 'link' || (node.type === 'button' && node.props.href)) {
        if (node.props.href) {
            attrs.push(`href="${escapeHtml(String(node.props.href))}"`);
        }
    }

    // Pass through custom attributes (attr_*)
    for (const [key, value] of Object.entries(node.props)) {
        if (key.startsWith('attr_') && value !== undefined && value !== null) {
            const attrName = key.slice(5);
            attrs.push(`${attrName}="${escapeHtml(String(value))}"`);
        }
    }

    const attrStr = attrs.length > 0 ? ` ${attrs.join(' ')}` : '';

    // Void tags
    if (VOID_TAGS.has(tag)) {
        return `<${tag}${attrStr} />`;
    }

    // Content
    let innerHtml = '';

    if (node.childIds.length > 0) {
        innerHtml = node.childIds
            .map((childId) => renderNodeToHtml(nodes, childId))
            .join('');
    } else if (node.props.content !== undefined && node.props.content !== null) {
        innerHtml = String(node.props.content);
    } else if (node.props.innerHTML !== undefined) {
        // Raw HTML content (for 'html' type nodes)
        innerHtml = String(node.props.innerHTML);
    }

    return `<${tag}${attrStr}>${innerHtml}</${tag}>`;
}

/**
 * Render a single AST node without `data-ast-id` attributes.
 * Used for the code editor dialog where IDs should be stripped.
 */
export function renderCleanNodeToHtml(nodes: AstNodeMap, nodeId: AstNodeId): string {
    return renderNodeToHtml(nodes, nodeId).replace(/ data-ast-id="[^"]*"/g, '');
}

/**
 * Render the full page content (all children of the root node).
 * Keeps `data-ast-id` attributes for iframe two-way binding.
 */
export function renderPageContent(nodes: AstNodeMap, rootNodeId: AstNodeId): string {
    const root = nodes[rootNodeId];

    if (!root) {
        return '';
    }

    return root.childIds
        .map((childId) => renderNodeToHtml(nodes, childId))
        .join('\n\n');
}

/**
 * Render the full page content with `data-ast-id` stripped.
 * Used for saving to the backend so IDs don't leak into live pages.
 */
export function renderCleanPageContent(nodes: AstNodeMap, rootNodeId: AstNodeId): string {
    const root = nodes[rootNodeId];

    if (!root) {
        return '';
    }

    return root.childIds
        .map((childId) => renderCleanNodeToHtml(nodes, childId))
        .join('\n\n');
}

/**
 * Render the page content with CSS/JS for injection into the iframe.
 */
export function renderForIframe(
    nodes: AstNodeMap,
    rootNodeId: AstNodeId,
    css: string,
    js: string,
): { html: string; css: string; js: string } {
    return {
        html: renderPageContent(nodes, rootNodeId),
        css,
        js,
    };
}
