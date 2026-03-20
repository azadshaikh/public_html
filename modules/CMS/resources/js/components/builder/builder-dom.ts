import type { BuilderCanvasItem } from '../../types/cms';

export type BuilderElementPath = number[];

export type BuilderElementStyleValues = {
    backgroundColor: string;
    borderRadius: string;
    color: string;
    fontSize: string;
    fontWeight: string;
    height: string;
    marginBottom: string;
    marginLeft: string;
    marginRight: string;
    marginTop: string;
    opacity: string;
    paddingBottom: string;
    paddingLeft: string;
    paddingRight: string;
    paddingTop: string;
    textAlign: string;
    width: string;
};

export type BuilderEditableElement = {
    path: BuilderElementPath;
    pathKey: string;
    label: string;
    tagName: string;
    textContent: string;
    className: string;
    id: string;
    href: string;
    src: string;
    alt: string;
    styles: BuilderElementStyleValues;
    canEditText: boolean;
    isLink: boolean;
    isImage: boolean;
};

export type BuilderElementPatch = {
    alt?: string;
    className?: string;
    href?: string;
    id?: string;
    src?: string;
    textContent?: string;
    styles?: Partial<BuilderElementStyleValues>;
};

const EDITABLE_TAGS = new Set([
    'a',
    'article',
    'button',
    'div',
    'figcaption',
    'figure',
    'footer',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'header',
    'img',
    'label',
    'li',
    'main',
    'nav',
    'p',
    'section',
    'small',
    'span',
    'strong',
]);

const DEFAULT_STYLE_VALUES: BuilderElementStyleValues = {
    backgroundColor: '',
    borderRadius: '',
    color: '',
    fontSize: '',
    fontWeight: '',
    height: '',
    marginBottom: '',
    marginLeft: '',
    marginRight: '',
    marginTop: '',
    opacity: '',
    paddingBottom: '',
    paddingLeft: '',
    paddingRight: '',
    paddingTop: '',
    textAlign: '',
    width: '',
};

function parseHtml(html: string): HTMLDivElement {
    const document = new DOMParser().parseFromString(
        '<div id="builder-root"></div>',
        'text/html',
    );
    const container = document.getElementById('builder-root');

    if (!(container instanceof HTMLDivElement)) {
        throw new Error('Builder root container could not be created.');
    }

    container.innerHTML = html;

    return container;
}

function serializeHtml(container: HTMLDivElement): string {
    return container.innerHTML;
}

function getRootElement(container: HTMLDivElement): HTMLElement | null {
    return container.firstElementChild instanceof HTMLElement
        ? container.firstElementChild
        : null;
}

function toPathKey(path: BuilderElementPath): string {
    return path.join('.') || 'root';
}

function hasDirectTextContent(element: HTMLElement): boolean {
    return Array.from(element.childNodes).some(
        (node) =>
            node.nodeType === Node.TEXT_NODE && node.textContent?.trim() !== '',
    );
}

function buildElementLabel(element: HTMLElement): string {
    const tagName = element.tagName.toLowerCase();
    const text = element.textContent?.replace(/\s+/g, ' ').trim() ?? '';
    const textPreview = text !== '' ? `: ${text.slice(0, 42)}` : '';

    return `${tagName}${text.length > 42 ? `${textPreview}...` : textPreview}`;
}

function readStyleValues(element: HTMLElement): BuilderElementStyleValues {
    return {
        backgroundColor: element.style.backgroundColor,
        borderRadius: element.style.borderRadius,
        color: element.style.color,
        fontSize: element.style.fontSize,
        fontWeight: element.style.fontWeight,
        height: element.style.height,
        marginBottom: element.style.marginBottom,
        marginLeft: element.style.marginLeft,
        marginRight: element.style.marginRight,
        marginTop: element.style.marginTop,
        opacity: element.style.opacity,
        paddingBottom: element.style.paddingBottom,
        paddingLeft: element.style.paddingLeft,
        paddingRight: element.style.paddingRight,
        paddingTop: element.style.paddingTop,
        textAlign: element.style.textAlign,
        width: element.style.width,
    };
}

export function getElementPath(
    root: HTMLElement,
    target: HTMLElement,
): BuilderElementPath {
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

export function getElementByPath(
    root: HTMLElement,
    path: BuilderElementPath,
): HTMLElement | null {
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

export function collectEditableElements(
    html: string,
): BuilderEditableElement[] {
    const container = parseHtml(html);
    const root = getRootElement(container);

    if (root === null) {
        return [];
    }

    const results: BuilderEditableElement[] = [];
    const queue: HTMLElement[] = [root];

    while (queue.length > 0) {
        const element = queue.shift();

        if (!element) {
            continue;
        }

        if (EDITABLE_TAGS.has(element.tagName.toLowerCase())) {
            const path = getElementPath(root, element);

            results.push({
                alt: element.getAttribute('alt') ?? '',
                canEditText:
                    hasDirectTextContent(element) ||
                    element.children.length === 0,
                className: element.getAttribute('class') ?? '',
                href: element.getAttribute('href') ?? '',
                id: element.id,
                isImage: element.tagName.toLowerCase() === 'img',
                isLink: element.tagName.toLowerCase() === 'a',
                label: buildElementLabel(element),
                path,
                pathKey: toPathKey(path),
                src: element.getAttribute('src') ?? '',
                styles: readStyleValues(element),
                tagName: element.tagName.toLowerCase(),
                textContent:
                    element.textContent?.replace(/\s+/g, ' ').trim() ?? '',
            });
        }

        queue.push(
            ...Array.from(element.children).filter(
                (child): child is HTMLElement => child instanceof HTMLElement,
            ),
        );
    }

    return results;
}

export function findEditableElement(
    html: string,
    path: BuilderElementPath,
): BuilderEditableElement | null {
    return (
        collectEditableElements(html).find(
            (element) => element.pathKey === toPathKey(path),
        ) ?? null
    );
}

export function updateItemElement(
    item: BuilderCanvasItem,
    path: BuilderElementPath,
    patch: BuilderElementPatch,
): BuilderCanvasItem {
    const container = parseHtml(item.html);
    const root = getRootElement(container);

    if (root === null) {
        return item;
    }

    const target = getElementByPath(root, path) ?? root;

    if (patch.textContent !== undefined) {
        target.textContent = patch.textContent;
    }

    if (patch.href !== undefined) {
        setAttribute(target, 'href', patch.href);
    }

    if (patch.src !== undefined) {
        setAttribute(target, 'src', patch.src);
    }

    if (patch.alt !== undefined) {
        setAttribute(target, 'alt', patch.alt);
    }

    if (patch.id !== undefined) {
        setAttribute(target, 'id', patch.id);
    }

    if (patch.className !== undefined) {
        setAttribute(target, 'class', patch.className);
    }

    for (const [property, value] of Object.entries(patch.styles ?? {}) as Array<
        [keyof BuilderElementStyleValues, string | undefined]
    >) {
        if (value === undefined) {
            continue;
        }

        setStyleValue(target, property, value);
    }

    return {
        ...item,
        html: serializeHtml(container),
    };
}

export function setAttribute(
    element: HTMLElement,
    name: string,
    value: string,
): void {
    if (value.trim() === '') {
        element.removeAttribute(name);

        return;
    }

    element.setAttribute(name, value);
}

export function setStyleValue(
    element: HTMLElement,
    property: keyof BuilderElementStyleValues,
    value: string,
): void {
    const cssProperty = property.replace(
        /[A-Z]/g,
        (character) => `-${character.toLowerCase()}`,
    );

    if (value.trim() === '') {
        element.style.removeProperty(cssProperty);

        return;
    }

    element.style.setProperty(cssProperty, value);
}

export function getPreviewRootElement(wrapper: HTMLElement): HTMLElement {
    return wrapper.firstElementChild instanceof HTMLElement
        ? wrapper.firstElementChild
        : wrapper;
}

export function getDefaultStyleValues(): BuilderElementStyleValues {
    return { ...DEFAULT_STYLE_VALUES };
}
