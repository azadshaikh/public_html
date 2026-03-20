'use client';

import {
    type BuilderEditableElement,
    type BuilderElementStyleValues,
} from '../../../components/builder/builder-dom';
import { ROOT_NODE_ID, type AstNode, type AstNodeMap } from '../../../components/builder/core/ast-types';
import { createEmptyPageAst, parseHtmlToAst } from '../../../components/builder/core/ast-helpers';
import { renderNodeToHtml } from '../../../components/builder/core/ast-to-html';
import type { BuilderCanvasItem } from '../../../types/cms';

const VOID_ELEMENTS = new Set([
    'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
    'link', 'meta', 'param', 'source', 'track', 'wbr',
]);

export const BUILDER_PANEL_LAYOUT_STORAGE_KEY = 'cms-builder-panel-layout';
export const BUILDER_PANEL_WIDTHS_STORAGE_KEY = 'cms-builder-panel-widths';
export const DEFAULT_LEFT_PANEL_SIZE = 15;
export const DEFAULT_RIGHT_PANEL_SIZE = 15;
export const MIN_SIDEBAR_PERCENTAGE = 12;
export const MAX_SIDEBAR_PERCENTAGE = 25;

export type BuilderPanelLayout = {
    left: number;
    center: number;
    right: number;
};

export type FooterEditorTab = 'html' | 'css' | 'js';
export type FooterEditorDrafts = Record<FooterEditorTab, string>;

export function formatHtmlForDisplay(html: string): string {
    const tokens = html.replace(/>\s*</g, '>\n<').split('\n');
    let indent = 0;
    const lines: string[] = [];

    for (const token of tokens) {
        const trimmed = token.trim();

        if (!trimmed) {
            continue;
        }

        const isClosing = /^<\//.test(trimmed);
        const tagMatch = trimmed.match(/^<\/?([a-zA-Z][a-zA-Z0-9-]*)/);
        const tagName = tagMatch?.[1]?.toLowerCase() ?? '';
        const isSelfClosing = /\/>$/.test(trimmed) || VOID_ELEMENTS.has(tagName);
        const isOpening = /^<[a-zA-Z]/.test(trimmed) && !isClosing;

        if (isClosing) {
            indent = Math.max(0, indent - 1);
        }

        lines.push('  '.repeat(indent) + trimmed);

        if (isOpening && !isSelfClosing) {
            indent++;
        }
    }

    return lines.join('\n');
}

export function buildInitialAst(items: BuilderCanvasItem[], css: string, js: string) {
    if (items.length === 0) {
        const ast = createEmptyPageAst();
        ast.css = css;
        ast.js = js;

        return ast;
    }

    const ast = createEmptyPageAst();
    ast.css = css;
    ast.js = js;

    for (const item of items) {
        const trimmed = item.html.trim();

        if (!trimmed) {
            continue;
        }

        const htmlToUse = /^<section[\s>]/i.test(trimmed) ? trimmed : `<section>${trimmed}</section>`;
        const parsed = parseHtmlToAst(htmlToUse, 'section', item.label || 'Section', {});
        const parsedRoot = parsed.nodes[parsed.rootId];

        if (parsedRoot) {
            parsedRoot.parentId = ROOT_NODE_ID;
            ast.nodes[parsed.rootId] = parsedRoot;
            ast.nodes[ROOT_NODE_ID].childIds.push(parsed.rootId);

            for (const [id, node] of Object.entries(parsed.nodes)) {
                if (id !== parsed.rootId) {
                    ast.nodes[id] = node;
                }
            }
        }
    }

    return ast;
}

export function astToCanvasItems(nodes: AstNodeMap, rootNodeId: string): BuilderCanvasItem[] {
    const root = nodes[rootNodeId];

    if (!root) {
        return [];
    }

    return root.childIds.map((childId) => {
        const node = nodes[childId];

        return {
            uid: childId,
            catalog_id: null,
            type: node?.type ?? 'section',
            category: 'section',
            label: node?.displayName ?? 'Section',
            html: renderNodeToHtml(nodes, childId),
            css: '',
            js: '',
            preview_image_url: null,
            source: 'database' as const,
        };
    });
}

export function clampSidebarPercentage(value: number): number {
    return Math.min(MAX_SIDEBAR_PERCENTAGE, Math.max(MIN_SIDEBAR_PERCENTAGE, value));
}

export function normalizePanelLayout(layout?: Partial<BuilderPanelLayout> | null): BuilderPanelLayout {
    const left = layout?.left === 0 ? 0 : clampSidebarPercentage(layout?.left ?? DEFAULT_LEFT_PANEL_SIZE);
    const right = layout?.right === 0 ? 0 : clampSidebarPercentage(layout?.right ?? DEFAULT_RIGHT_PANEL_SIZE);

    return {
        left,
        center: 100 - left - right,
        right,
    };
}

export function isStoredPanelLayout(value: unknown): value is Partial<BuilderPanelLayout> {
    if (!value || typeof value !== 'object') {
        return false;
    }

    const candidate = value as Record<string, unknown>;

    return ['left', 'center', 'right'].some((key) => typeof candidate[key] === 'number');
}

export function buildAstFromPageContent(html: string, css: string, js: string) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(`<body>${html}</body>`, 'text/html');
    const ast = createEmptyPageAst();

    ast.css = css;
    ast.js = js;

    for (const child of Array.from(doc.body.children)) {
        const parsed = parseHtmlToAst(child.outerHTML, 'section');
        const parsedRoot = parsed.nodes[parsed.rootId];

        if (!parsedRoot) {
            continue;
        }

        parsedRoot.parentId = ROOT_NODE_ID;
        ast.nodes[parsed.rootId] = parsedRoot;
        ast.nodes[ROOT_NODE_ID].childIds.push(parsed.rootId);

        for (const [id, node] of Object.entries(parsed.nodes)) {
            if (id !== parsed.rootId) {
                ast.nodes[id] = node;
            }
        }
    }

    return ast;
}

export function toEditableElement(node: AstNode): BuilderEditableElement {
    const tagName = (node.tagName ?? node.type).toLowerCase();
    const hoverStyles = node.props.hoverStyles;
    const focusStyles = node.props.focusStyles;

    return {
        alt: typeof node.props.alt === 'string' ? node.props.alt : '',
        buttonType: typeof node.props.attr_type === 'string' ? node.props.attr_type : '',
        canEditText: typeof node.props.content === 'string' || tagName === 'a' || tagName === 'button',
        className: node.className,
        disabled: node.props.attr_disabled === true || node.props.attr_disabled === 'true' || node.props.attr_disabled === 'disabled',
        focusStyles: typeof focusStyles === 'object' && focusStyles !== null ? focusStyles as Record<string, string> : {},
        href: typeof node.props.href === 'string' ? node.props.href : '',
        hoverStyles: typeof hoverStyles === 'object' && hoverStyles !== null ? hoverStyles as Record<string, string> : {},
        id: typeof node.props.attr_id === 'string' ? node.props.attr_id : '',
        isButton: tagName === 'button',
        isImage: node.type === 'image',
        isLink: node.type === 'link' || tagName === 'a',
        label: node.displayName,
        path: [],
        pathKey: node.id,
        rel: typeof node.props.attr_rel === 'string' ? node.props.attr_rel : '',
        src: typeof node.props.src === 'string' ? node.props.src : '',
        styles: {
            backgroundColor: node.styles.backgroundColor ?? '',
            borderBottomLeftRadius: node.styles.borderBottomLeftRadius ?? '',
            borderBottomRightRadius: node.styles.borderBottomRightRadius ?? '',
            borderColor: node.styles.borderColor ?? '',
            borderRadius: node.styles.borderRadius ?? '',
            borderStyle: node.styles.borderStyle ?? '',
            borderTopLeftRadius: node.styles.borderTopLeftRadius ?? '',
            borderTopRightRadius: node.styles.borderTopRightRadius ?? '',
            borderWidth: node.styles.borderWidth ?? '',
            boxShadow: node.styles.boxShadow ?? '',
            color: node.styles.color ?? '',
            fontSize: node.styles.fontSize ?? '',
            fontWeight: node.styles.fontWeight ?? '',
            height: node.styles.height ?? '',
            letterSpacing: node.styles.letterSpacing ?? '',
            lineHeight: node.styles.lineHeight ?? '',
            marginBottom: node.styles.marginBottom ?? '',
            marginLeft: node.styles.marginLeft ?? '',
            marginRight: node.styles.marginRight ?? '',
            marginTop: node.styles.marginTop ?? '',
            opacity: node.styles.opacity ?? '',
            paddingBottom: node.styles.paddingBottom ?? '',
            paddingLeft: node.styles.paddingLeft ?? '',
            paddingRight: node.styles.paddingRight ?? '',
            paddingTop: node.styles.paddingTop ?? '',
            textAlign: node.styles.textAlign ?? '',
            textDecoration: node.styles.textDecoration ?? '',
            width: node.styles.width ?? '',
        } satisfies BuilderElementStyleValues,
        tagName,
        target: typeof node.props.attr_target === 'string' ? node.props.attr_target : '',
        textContent: typeof node.props.content === 'string' ? node.props.content : '',
    };
}