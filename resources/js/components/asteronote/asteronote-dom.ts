import type {
    AsteroNoteAlign,
    AsteroNoteBlockTag,
    AsteroNoteFormatState,
    AsteroNoteImagePayload,
    AsteroNoteListType,
    AsteroNoteTableAction,
    AsteroNoteVideoPayload,
} from '@/components/asteronote/asteronote-types';
import {
    hasMeaningfulHtmlContent,
    normalizeHtmlEditorValue,
} from '@/components/asteronote/html-editor-utils';

const blockTags = new Set<AsteroNoteBlockTag>([
    'p',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'blockquote',
    'pre',
]);

const allowedTags = new Set([
    'a',
    'blockquote',
    'br',
    'code',
    'em',
    'figcaption',
    'figure',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'hr',
    'iframe',
    'img',
    'li',
    'ol',
    'p',
    'pre',
    's',
    'small',
    'source',
    'strong',
    'sub',
    'sup',
    'table',
    'tbody',
    'td',
    'th',
    'thead',
    'tr',
    'u',
    'ul',
    'video',
]);

const allowedAttributes = new Map<string, Set<string>>([
    ['a', new Set(['href', 'target', 'rel'])],
    [
        'iframe',
        new Set([
            'allow',
            'allowfullscreen',
            'frameborder',
            'height',
            'src',
            'title',
            'width',
        ]),
    ],
    [
        'img',
        new Set([
            'alt',
            'height',
            'loading',
            'sizes',
            'src',
            'srcset',
            'width',
        ]),
    ],
    ['ol', new Set(['start', 'type'])],
    ['source', new Set(['src', 'type'])],
    ['td', new Set(['colspan', 'rowspan'])],
    ['th', new Set(['colspan', 'rowspan', 'scope'])],
    [
        'video',
        new Set([
            'controls',
            'height',
            'playsinline',
            'poster',
            'preload',
            'src',
            'width',
        ]),
    ],
]);

const allowedStyleProperties = new Set([
    'height',
    'list-style-type',
    'max-width',
    'text-align',
    'width',
]);

export const defaultAsteroNoteFormatState: AsteroNoteFormatState = {
    blockTag: 'p',
    listType: null,
    listStyleType: null,
    align: 'left',
    bold: false,
    italic: false,
    underline: false,
    strikethrough: false,
    link: false,
    inTable: false,
};

export function escapeHtmlAttr(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('"', '&quot;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
}

export function escapeHtmlContent(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
}

/** @deprecated Use escapeHtmlAttr instead */
function escapeAttribute(value: string): string {
    return escapeHtmlAttr(value);
}

function sanitizeStyle(value: string): string {
    const safeEntries = value
        .split(';')
        .map((entry) => entry.trim())
        .filter(Boolean)
        .map((entry) => {
            const [property, ...raw] = entry.split(':');
            const normalizedProperty = property.trim().toLowerCase();

            if (!allowedStyleProperties.has(normalizedProperty)) {
                return null;
            }

            const normalizedValue = raw.join(':').trim();
            const lowerValue = normalizedValue.toLowerCase();

            if (
                lowerValue.includes('expression(') ||
                lowerValue.includes('javascript:') ||
                lowerValue.includes('url(')
            ) {
                return null;
            }

            return `${normalizedProperty}: ${normalizedValue}`;
        })
        .filter((entry): entry is string => entry !== null);

    return safeEntries.join('; ');
}

function isSafeUrl(
    value: string,
    { allowData = false }: { allowData?: boolean } = {},
): boolean {
    const normalized = value.trim().toLowerCase();

    if (normalized === '') {
        return true;
    }

    if (normalized.startsWith('/')) {
        return true;
    }

    if (allowData && normalized.startsWith('data:image/')) {
        return true;
    }

    return /^(https?:|mailto:|tel:|ftp:)/.test(normalized);
}

function renameElement(element: HTMLElement, tagName: string): HTMLElement {
    const replacement = document.createElement(tagName);

    for (const attribute of Array.from(element.attributes)) {
        replacement.setAttribute(attribute.name, attribute.value);
    }

    while (element.firstChild) {
        replacement.appendChild(element.firstChild);
    }

    element.replaceWith(replacement);

    return replacement;
}

function unwrapElement(element: HTMLElement): void {
    const parent = element.parentNode;

    if (!parent) {
        return;
    }

    while (element.firstChild) {
        parent.insertBefore(element.firstChild, element);
    }

    parent.removeChild(element);
}

function sanitizeElementAttributes(element: HTMLElement): void {
    const tag = element.tagName.toLowerCase();
    const tagAttributes = allowedAttributes.get(tag) ?? new Set<string>();

    for (const attribute of Array.from(element.attributes)) {
        const attributeName = attribute.name.toLowerCase();
        const attributeValue = attribute.value;

        if (attributeName === 'style') {
            const sanitizedStyle = sanitizeStyle(attributeValue);

            if (sanitizedStyle === '') {
                element.removeAttribute('style');
            } else {
                element.setAttribute('style', sanitizedStyle);
            }

            continue;
        }

        if (!tagAttributes.has(attributeName)) {
            element.removeAttribute(attribute.name);
            continue;
        }

        if (attributeName === 'href' && !isSafeUrl(attributeValue)) {
            element.removeAttribute(attribute.name);
            continue;
        }

        if (attributeName === 'src') {
            const allowData = tag === 'img';
            const allowIframe =
                tag === 'iframe' &&
                /^(https?:)?\/\/(www\.)?(youtube\.com|youtu\.be|player\.vimeo\.com|www\.dailymotion\.com)/i.test(
                    attributeValue,
                );

            if (!allowIframe && !isSafeUrl(attributeValue, { allowData })) {
                element.removeAttribute(attribute.name);
            }
        }
    }
}

export function sanitizeAsteroNoteHtml(html: string): string {
    const normalized = normalizeHtmlEditorValue(html);
    const container = document.createElement('div');
    container.innerHTML = normalized;

    const cleanNode = (node: Node): void => {
        if (node.nodeType === Node.COMMENT_NODE) {
            node.parentNode?.removeChild(node);
            return;
        }

        if (node.nodeType !== Node.ELEMENT_NODE) {
            return;
        }

        let element = node as HTMLElement;
        let tag = element.tagName.toLowerCase();

        if (tag === 'b') {
            element = renameElement(element, 'strong');
            tag = 'strong';
        } else if (tag === 'i') {
            element = renameElement(element, 'em');
            tag = 'em';
        } else if (tag === 'strike') {
            element = renameElement(element, 's');
            tag = 's';
        }

        if (tag === 'span' || tag === 'font') {
            for (const child of Array.from(element.childNodes)) {
                cleanNode(child);
            }
            unwrapElement(element);
            return;
        }

        if (tag === 'script' || tag === 'style') {
            element.remove();
            return;
        }

        if (!allowedTags.has(tag)) {
            if (tag === 'div') {
                element = renameElement(element, 'p');
            } else {
                for (const child of Array.from(element.childNodes)) {
                    cleanNode(child);
                }
                unwrapElement(element);
                return;
            }
        }

        sanitizeElementAttributes(element);

        for (const child of Array.from(element.childNodes)) {
            cleanNode(child);
        }
    };

    for (const child of Array.from(container.childNodes)) {
        cleanNode(child);
    }

    for (const child of Array.from(container.childNodes)) {
        if (child.nodeType === Node.TEXT_NODE && child.textContent?.trim()) {
            const paragraph = document.createElement('p');
            paragraph.textContent = child.textContent;
            container.replaceChild(paragraph, child);
            continue;
        }

        if (child.nodeType !== Node.ELEMENT_NODE) {
            continue;
        }

        const element = child as HTMLElement;
        const tag = element.tagName.toLowerCase();

        if (
            !blockTags.has(tag as AsteroNoteBlockTag) &&
            !['figure', 'hr', 'table', 'ul', 'ol', 'video', 'iframe'].includes(
                tag,
            )
        ) {
            const wrapper = document.createElement('p');
            element.replaceWith(wrapper);
            wrapper.appendChild(element);
        }
    }

    const cleaned = container.innerHTML.trim();

    if (!hasMeaningfulHtmlContent(cleaned)) {
        return '';
    }

    return cleaned;
}

export function isSelectionInside(root: HTMLElement | null): boolean {
    const selection = window.getSelection();

    if (!root || !selection || selection.rangeCount === 0) {
        return false;
    }

    const { anchorNode, focusNode } = selection;

    return (
        !!anchorNode &&
        !!focusNode &&
        root.contains(anchorNode) &&
        root.contains(focusNode)
    );
}

export function saveSelectionRange(root: HTMLElement | null): Range | null {
    if (!isSelectionInside(root)) {
        return null;
    }

    const selection = window.getSelection();

    if (!selection || selection.rangeCount === 0) {
        return null;
    }

    return selection.getRangeAt(0).cloneRange();
}

export function restoreSelectionRange(
    root: HTMLElement | null,
    range: Range | null,
): void {
    if (!root || !range) {
        return;
    }

    const selection = window.getSelection();

    if (!selection) {
        return;
    }

    root.focus();
    selection.removeAllRanges();
    selection.addRange(range);
}

function getNearestElement(
    node: Node | null,
    root: HTMLElement | null,
): HTMLElement | null {
    let current = node;

    while (current && current !== root) {
        if (current instanceof HTMLElement) {
            return current;
        }

        current = current.parentNode;
    }

    return root;
}

function getClosestBlock(
    node: Node | null,
    root: HTMLElement | null,
): HTMLElement | null {
    let current: Node | null = node;

    while (current && current !== root) {
        if (current instanceof HTMLElement) {
            const tag = current.tagName.toLowerCase();

            if (
                blockTags.has(tag as AsteroNoteBlockTag) ||
                tag === 'li' ||
                tag === 'td' ||
                tag === 'th'
            ) {
                return current;
            }
        }

        current = current.parentNode;
    }

    return null;
}

function getBlockTagFromSelection(
    root: HTMLElement | null,
): AsteroNoteBlockTag {
    if (!isSelectionInside(root)) {
        return 'p';
    }

    const selection = window.getSelection();
    const block = getClosestBlock(selection?.anchorNode ?? null, root);

    if (!block) {
        return 'p';
    }

    const tag = block.tagName.toLowerCase();

    if (tag === 'li') {
        const list = block.closest('ul, ol');
        return list ? 'p' : 'p';
    }

    if (blockTags.has(tag as AsteroNoteBlockTag)) {
        return tag as AsteroNoteBlockTag;
    }

    return 'p';
}

function getListContext(root: HTMLElement | null): {
    type: AsteroNoteListType;
    styleType: string | null;
    root: HTMLElement | null;
} {
    if (!isSelectionInside(root)) {
        return { type: null, styleType: null, root: null };
    }

    const selection = window.getSelection();
    const element = getNearestElement(selection?.anchorNode ?? null, root);
    const list = element?.closest('ul, ol') ?? null;

    if (!(list instanceof HTMLElement)) {
        return { type: null, styleType: null, root: null };
    }

    return {
        type: list.tagName.toLowerCase() as AsteroNoteListType,
        styleType: list.style.listStyleType || null,
        root: list,
    };
}

function getCurrentAlignment(root: HTMLElement | null): AsteroNoteAlign {
    if (!isSelectionInside(root)) {
        return 'left';
    }

    const selection = window.getSelection();
    let current: Node | null = selection?.anchorNode ?? null;

    while (current && current !== root) {
        if (current instanceof HTMLElement) {
            const style = window.getComputedStyle(current);
            let textAlign = style.textAlign.toLowerCase();

            if (textAlign === 'start') {
                textAlign = 'left';
            }

            if (textAlign === 'end') {
                textAlign = 'right';
            }

            if (
                textAlign === 'left' ||
                textAlign === 'center' ||
                textAlign === 'right' ||
                textAlign === 'justify'
            ) {
                return textAlign;
            }
        }

        current = current.parentNode;
    }

    return 'left';
}

export function getClosestLink(
    root: HTMLElement | null,
): HTMLAnchorElement | null {
    if (!isSelectionInside(root)) {
        return null;
    }

    const selection = window.getSelection();
    const element = getNearestElement(selection?.anchorNode ?? null, root);

    return element?.closest('a') ?? null;
}

export function getCurrentFormatState(
    root: HTMLElement | null,
): AsteroNoteFormatState {
    if (!root || !isSelectionInside(root)) {
        return defaultAsteroNoteFormatState;
    }

    const list = getListContext(root);

    return {
        blockTag: getBlockTagFromSelection(root),
        listType: list.type,
        listStyleType: list.styleType,
        align: getCurrentAlignment(root),
        bold: queryCommandState('bold'),
        italic: queryCommandState('italic'),
        underline: queryCommandState('underline'),
        strikethrough: queryCommandState('strikeThrough'),
        link: getClosestLink(root) !== null,
        inTable: getCurrentTableCell(root) !== null,
    };
}

export function getSelectionRect(root: HTMLElement | null): DOMRect | null {
    if (!root || !isSelectionInside(root)) {
        return null;
    }

    const selection = window.getSelection();

    if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
        return null;
    }

    const range = selection.getRangeAt(0);
    const rect = range.getBoundingClientRect();

    if (!rect.width && !rect.height) {
        return null;
    }

    return rect;
}

export function queryCommandState(command: string): boolean {
    try {
        return document.queryCommandState(command);
    } catch {
        return false;
    }
}

export function insertHtmlAtSelection(
    root: HTMLElement | null,
    html: string,
): void {
    if (!root) {
        return;
    }

    const selection = window.getSelection();

    if (!selection || selection.rangeCount === 0) {
        root.focus();
        root.insertAdjacentHTML('beforeend', html);
        return;
    }

    const range = selection.getRangeAt(0);
    range.deleteContents();

    const fragment = range.createContextualFragment(html);
    const lastNode = fragment.lastChild;
    range.insertNode(fragment);

    if (lastNode) {
        placeCaretAfter(lastNode);
    }
}

export function placeCaretAfter(node: Node): void {
    const selection = window.getSelection();

    if (!selection) {
        return;
    }

    const range = document.createRange();
    range.setStartAfter(node);
    range.collapse(true);

    selection.removeAllRanges();
    selection.addRange(range);
}

export function buildImageHtml(payload: AsteroNoteImagePayload): string {
    const attributes = [
        `src="${escapeAttribute(payload.src)}"`,
        `alt="${escapeAttribute(payload.alt ?? '')}"`,
        `loading="${escapeAttribute(payload.loading ?? 'lazy')}"`,
        'style="max-width: 100%; height: auto;"',
    ];

    if (payload.srcset) {
        attributes.push(`srcset="${escapeAttribute(payload.srcset)}"`);
    }

    if (payload.sizes) {
        attributes.push(`sizes="${escapeAttribute(payload.sizes)}"`);
    }

    if (payload.width) {
        attributes.push(`width="${escapeAttribute(payload.width)}"`);
    }

    if (payload.height) {
        attributes.push(`height="${escapeAttribute(payload.height)}"`);
    }

    return `<figure><img ${attributes.join(' ')} /><figcaption></figcaption></figure>`;
}

export function buildVideoHtml(payload: AsteroNoteVideoPayload): string | null {
    const url = payload.url.trim();

    if (url === '') {
        return null;
    }

    const youtubeMatch =
        /(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|v\/))([A-Za-z0-9_-]{6,})/i.exec(
            url,
        );

    if (youtubeMatch) {
        return `<iframe src="https://www.youtube.com/embed/${escapeAttribute(youtubeMatch[1])}" title="Embedded video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen width="560" height="315"></iframe>`;
    }

    const vimeoMatch =
        /vimeo\.com\/(?:channels\/\w+\/|groups\/\w+\/videos\/)?([0-9]+)/i.exec(
            url,
        );

    if (vimeoMatch) {
        return `<iframe src="https://player.vimeo.com/video/${escapeAttribute(vimeoMatch[1])}" title="Embedded video" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen width="560" height="315"></iframe>`;
    }

    const dailyMotionMatch =
        /(?:dailymotion\.com\/video\/|dai\.ly\/)([A-Za-z0-9]+)/i.exec(url);

    if (dailyMotionMatch) {
        return `<iframe src="https://www.dailymotion.com/embed/video/${escapeAttribute(dailyMotionMatch[1])}" title="Embedded video" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen width="560" height="315"></iframe>`;
    }

    if (/\.(mp4|webm|ogg)(\?.*)?$/i.test(url) && isSafeUrl(url)) {
        return `<video src="${escapeAttribute(url)}" controls preload="metadata" style="max-width: 100%;"></video>`;
    }

    return null;
}

export function createTableHtml(rows: number, columns: number): string {
    const safeRows = Math.max(1, Math.min(20, Math.trunc(rows)));
    const safeColumns = Math.max(1, Math.min(12, Math.trunc(columns)));

    const head = `<thead><tr>${Array.from({ length: safeColumns }, () => '<th scope="col"><br></th>').join('')}</tr></thead>`;
    const body = `<tbody>${Array.from({ length: safeRows }, () => `<tr>${Array.from({ length: safeColumns }, () => '<td><br></td>').join('')}</tr>`).join('')}</tbody>`;

    return `<table>${head}${body}</table>`;
}

export function getCurrentTableCell(
    root: HTMLElement | null,
): HTMLTableCellElement | null {
    if (!isSelectionInside(root)) {
        return null;
    }

    const selection = window.getSelection();
    const element = getNearestElement(selection?.anchorNode ?? null, root);
    const cell = element?.closest('td, th') ?? null;

    return cell instanceof HTMLTableCellElement ? cell : null;
}

function getTableContext(root: HTMLElement | null): {
    cell: HTMLTableCellElement;
    row: HTMLTableRowElement;
    section: HTMLTableSectionElement;
    table: HTMLTableElement;
    rowIndex: number;
    columnIndex: number;
} | null {
    const cell = getCurrentTableCell(root);

    if (!cell) {
        return null;
    }

    const row = cell.closest('tr');
    const section = row?.parentElement;
    const table = row?.closest('table');

    if (
        !(row instanceof HTMLTableRowElement) ||
        !(section instanceof HTMLTableSectionElement) ||
        !(table instanceof HTMLTableElement)
    ) {
        return null;
    }

    return {
        cell,
        row,
        section,
        table,
        rowIndex: Array.from(section.children).indexOf(row),
        columnIndex: Array.from(row.children).indexOf(cell),
    };
}

function ensureCellHasBreak(cell: HTMLTableCellElement): void {
    const html = cell.innerHTML.trim();

    if (
        html === '' ||
        html === '&nbsp;' ||
        html === '<br>' ||
        html === '<br/>' ||
        html === '<br />'
    ) {
        cell.innerHTML = '<br>';
    }
}

export function focusFirstTableCell(root: HTMLElement | null): void {
    const cell =
        root?.querySelector('tbody td') ?? root?.querySelector('td, th');

    if (!(cell instanceof HTMLTableCellElement)) {
        return;
    }

    ensureCellHasBreak(cell);

    const range = document.createRange();
    range.selectNodeContents(cell);
    range.collapse(true);

    const selection = window.getSelection();

    if (!selection) {
        return;
    }

    selection.removeAllRanges();
    selection.addRange(range);
}

export function updateTableAtSelection(
    root: HTMLElement | null,
    action: AsteroNoteTableAction,
): void {
    const context = getTableContext(root);

    if (!context) {
        return;
    }

    const { cell, row, section, table, columnIndex } = context;

    if (action === 'delete-table') {
        const parent = table.parentNode;
        table.remove();

        if (parent) {
            const textNode = document.createTextNode('');
            parent.appendChild(textNode);
            placeCaretAfter(textNode);
            textNode.remove();
        }

        return;
    }

    if (action === 'delete-row') {
        if (section.rows.length === 1) {
            table.remove();
            return;
        }

        const targetRow = row.previousElementSibling ?? row.nextElementSibling;
        row.remove();

        const nextCell = targetRow?.children.item(Math.max(0, columnIndex));

        if (nextCell instanceof HTMLTableCellElement) {
            ensureCellHasBreak(nextCell);
            placeCaretInCell(nextCell);
        }

        return;
    }

    if (action === 'delete-column') {
        const rows = Array.from(table.rows);

        if (rows[0]?.cells.length === 1) {
            table.remove();
            return;
        }

        rows.forEach((tableRow) => {
            tableRow.deleteCell(columnIndex);
        });

        const nextCell =
            row.cells[Math.max(0, columnIndex - 1)] ?? row.cells[columnIndex];

        if (nextCell instanceof HTMLTableCellElement) {
            ensureCellHasBreak(nextCell);
            placeCaretInCell(nextCell);
        }

        return;
    }

    if (action === 'add-row-above' || action === 'add-row-below') {
        const newRow = document.createElement('tr');
        const totalColumns = row.cells.length;

        for (let index = 0; index < totalColumns; index += 1) {
            const newCell = document.createElement('td');
            newCell.innerHTML = '<br>';
            newRow.appendChild(newCell);
        }

        if (action === 'add-row-above') {
            row.before(newRow);
        } else {
            row.after(newRow);
        }

        const targetCell = newRow.cells[columnIndex] ?? newRow.cells[0];

        if (targetCell instanceof HTMLTableCellElement) {
            placeCaretInCell(targetCell);
        }

        return;
    }

    if (action === 'add-column-left' || action === 'add-column-right') {
        const insertIndex =
            action === 'add-column-left' ? columnIndex : columnIndex + 1;

        Array.from(table.rows).forEach((tableRow) => {
            const tagName =
                tableRow.parentElement?.tagName.toLowerCase() === 'thead'
                    ? 'th'
                    : 'td';
            const newCell = document.createElement(tagName);

            if (tagName === 'th') {
                newCell.setAttribute('scope', 'col');
            }

            newCell.innerHTML = '<br>';
            const referenceCell = tableRow.cells[insertIndex] ?? null;

            if (referenceCell) {
                referenceCell.before(newCell);
            } else {
                tableRow.appendChild(newCell);
            }
        });

        const newActiveCell = row.cells[insertIndex] ?? row.cells[columnIndex];

        if (newActiveCell instanceof HTMLTableCellElement) {
            placeCaretInCell(newActiveCell);
        }
    }

    cell.focus?.();
}

export function placeCaretInCell(cell: HTMLTableCellElement): void {
    ensureCellHasBreak(cell);

    const range = document.createRange();
    range.selectNodeContents(cell);
    range.collapse(true);

    const selection = window.getSelection();

    if (!selection) {
        return;
    }

    selection.removeAllRanges();
    selection.addRange(range);
}

export function convertListRoot(
    list: HTMLElement,
    type: 'ul' | 'ol',
): HTMLElement {
    const replacement = document.createElement(type);

    while (list.firstChild) {
        replacement.appendChild(list.firstChild);
    }

    replacement.style.listStyleType = list.style.listStyleType;

    list.replaceWith(replacement);

    return replacement;
}

export function getClosestListRoot(
    root: HTMLElement | null,
): HTMLElement | null {
    if (!isSelectionInside(root)) {
        return null;
    }

    const selection = window.getSelection();
    const element = getNearestElement(selection?.anchorNode ?? null, root);
    const list = element?.closest('ul, ol');

    return list instanceof HTMLElement ? list : null;
}

export function normalizeEditorOutput(html: string): string {
    const sanitized = sanitizeAsteroNoteHtml(html);

    if (!hasMeaningfulHtmlContent(sanitized)) {
        return '';
    }

    return sanitized;
}
