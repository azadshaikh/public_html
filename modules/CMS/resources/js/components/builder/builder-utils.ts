import type { BuilderCanvasItem, BuilderLibraryItem } from '../../types/cms';

export type BuilderDeviceMode = 'desktop' | 'tablet' | 'mobile';

const BOOTSTRAP_PREVIEW_CSS_URL =
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css';

export function createCanvasItem(item: BuilderLibraryItem): BuilderCanvasItem {
    return {
        uid: createUid(),
        catalog_id: item.id,
        type: item.type,
        category: item.category,
        label: item.name,
        html: item.html,
        css: item.css,
        js: item.js,
        preview_image_url: item.preview_image_url,
        source: item.source,
    };
}

export function createUid(): string {
    if (
        typeof crypto !== 'undefined' &&
        typeof crypto.randomUUID === 'function'
    ) {
        return crypto.randomUUID();
    }

    return `builder-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export function escapeInlineStyle(value: string): string {
    return value.replace(/<\/style>/gi, '<\\/style>');
}

export function escapeInlineScript(value: string): string {
    return value.replace(/<\/script>/gi, '<\\/script>');
}

export function buildPreviewDocument(
    items: BuilderCanvasItem[],
    customCss: string,
    customJs: string,
): string {
    const itemMarkup =
        items.length > 0
            ? items
                .map(
                    (item) =>
                        `<section data-builder-item="${item.uid}" data-builder-category="${item.category}">${item.html}</section>`,
                )
                .join('\n')
            : `
                <section class="builder-empty-state">
                    <h2>Your canvas is empty</h2>
                    <p>Add a section or block from the library to start composing this page.</p>
                </section>
            `;

    const itemCss = items
        .map((item) => item.css.trim())
        .filter(Boolean)
        .join('\n\n');
    const itemJs = items
        .map((item) => item.js.trim())
        .filter(Boolean)
        .join('\n\n');

    return `<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <link rel="stylesheet" href="${BOOTSTRAP_PREVIEW_CSS_URL}" />
        <style>
            :root {
                color-scheme: light;
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                background:
                    radial-gradient(circle at top, rgba(56, 189, 248, 0.08), transparent 28%),
                    linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
                color: #0f172a;
                font-family: "Google Sans", system-ui, sans-serif;
            }

            img {
                max-width: 100%;
                height: auto;
            }

            .builder-preview-shell {
                min-height: 100vh;
                padding: 18px;
            }

            .builder-preview-shell > section[data-builder-item] {
                position: relative;
            }

            .builder-preview-shell > section[data-builder-item] + section[data-builder-item] {
                margin-top: 16px;
            }

            .builder-empty-state {
                display: grid;
                place-items: center;
                min-height: calc(100vh - 36px);
                border: 1px dashed rgba(148, 163, 184, 0.8);
                border-radius: 24px;
                background: rgba(255, 255, 255, 0.92);
                padding: 28px;
                text-align: center;
            }

            .builder-empty-state h2 {
                margin: 0 0 8px;
                font-size: 1.35rem;
            }

            .builder-empty-state p {
                margin: 0;
                color: #475569;
                max-width: 28rem;
            }

            ${escapeInlineStyle(itemCss)}
            ${escapeInlineStyle(customCss)}
        </style>
    </head>
    <body>
        <main class="builder-preview-shell">${itemMarkup}</main>
        <script>${escapeInlineScript(itemJs)}<\/script>
        <script>${escapeInlineScript(customJs)}<\/script>
    </body>
</html>`;
}

export function extractErrorMessage(payload: unknown): string {
    if (typeof payload !== 'object' || payload === null) {
        return 'The builder could not save your changes.';
    }

    const errorPayload = payload as {
        message?: string;
        errors?: Record<string, string[] | string>;
    };

    if (
        typeof errorPayload.message === 'string' &&
        errorPayload.message.trim() !== ''
    ) {
        return errorPayload.message;
    }

    const firstError = Object.values(errorPayload.errors ?? {}).flat()[0];

    if (typeof firstError === 'string' && firstError.trim() !== '') {
        return firstError;
    }

    return 'The builder could not save your changes.';
}

export function getCsrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

/**
 * The selector that marks the editable content area inside the theme page.
 * Theme templates use this attribute on the container holding post/page content.
 */
export const CONTENT_AREA_SELECTOR = '[data-astero-enabled]';

const BUILDER_STYLES_ID = 'builder-injected-styles';
const BUILDER_SCRIPTS_ID = 'builder-injected-scripts';

/**
 * Inject builder canvas items into the `[data-astero-enabled]` container
 * of an iframe document that loaded the real page URL.
 *
 * Returns `true` when the content area was found and populated.
 */
export function injectBuilderContent(
    iframeDoc: Document,
    items: BuilderCanvasItem[],
    customCss: string,
    customJs: string,
): boolean {
    const contentArea = iframeDoc.querySelector<HTMLElement>(CONTENT_AREA_SELECTOR);

    if (!contentArea) {
        return false;
    }

    // Build section markup
    const itemMarkup =
        items.length > 0
            ? items
                .map(
                    (item) =>
                        `<section data-builder-item="${item.uid}" data-builder-category="${item.category}">${item.html}</section>`,
                )
                .join('\n')
            : `<div class="builder-empty-state" style="display:grid;place-items:center;min-height:300px;border:1px dashed rgba(148,163,184,0.8);border-radius:12px;background:rgba(255,255,255,0.92);padding:28px;text-align:center;margin:24px 0;">
                    <div><h2 style="margin:0 0 8px;font-size:1.25rem;">Your canvas is empty</h2>
                    <p style="margin:0;color:#475569;max-width:28rem;">Add a section or block from the library to start composing this page.</p></div>
               </div>`;

    contentArea.innerHTML = itemMarkup;

    // Inject / update builder styles in <head>
    const itemCss = items
        .map((item) => item.css.trim())
        .filter(Boolean)
        .join('\n\n');

    const allCss = [itemCss, customCss].filter(Boolean).join('\n\n');

    let styleEl = iframeDoc.getElementById(BUILDER_STYLES_ID) as HTMLStyleElement | null;

    if (!styleEl) {
        styleEl = iframeDoc.createElement('style');
        styleEl.id = BUILDER_STYLES_ID;
        iframeDoc.head.appendChild(styleEl);
    }

    styleEl.textContent = allCss;

    // Inject / update builder scripts
    const itemJs = items
        .map((item) => item.js.trim())
        .filter(Boolean)
        .join('\n\n');

    const allJs = [itemJs, customJs].filter(Boolean).join('\n\n');

    // Remove old script and re-create (scripts don't re-execute on textContent change)
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

    return true;
}
