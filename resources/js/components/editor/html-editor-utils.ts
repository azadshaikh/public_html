export type HtmlEditorProps = {
    id: string;
    value: string;
    onBlur?: () => void;
    onChange: (value: string) => void;
    placeholder?: string;
    invalid?: boolean;
    className?: string;
};

function escapeHtml(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function plainTextToHtml(value: string): string {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '<p></p>';
    }

    const paragraphs = trimmed
        .split(/\n{2,}/)
        .map((paragraph) => paragraph.trim())
        .filter(Boolean)
        .map(
            (paragraph) =>
                `<p>${escapeHtml(paragraph).replace(/\n/g, '<br />')}</p>`,
        );

    return paragraphs.join('');
}

export function containsHtml(value: string): boolean {
    return /<[^>]+>/.test(value);
}

/**
 * Check whether the HTML string contains at least one block-level element.
 * Without block wrappers, Slate's deserializer creates top-level text nodes
 * that lack the required `children` array, crashing during rendering.
 */
function hasBlockElement(html: string): boolean {
    return /<(p|h[1-6]|blockquote|div|ul|ol|li|hr|pre|table|section|article|header|footer|main|nav|aside|figure|figcaption|details|summary|form|fieldset)[\s>/]/i.test(
        html,
    );
}

export function normalizeHtmlEditorValue(value: string): string {
    const trimmed = value.trim();

    if (trimmed === '') {
        return '<p></p>';
    }

    if (!containsHtml(trimmed)) {
        return plainTextToHtml(trimmed);
    }

    // Inline-only HTML (e.g. legacy content with <strong> but no <p> wrapper)
    // must be wrapped in a block element so Slate can render it.
    if (!hasBlockElement(trimmed)) {
        return `<p>${trimmed}</p>`;
    }

    return trimmed;
}

export function hasMeaningfulHtmlContent(value: string): boolean {
    const normalized = normalizeHtmlEditorValue(value);
    const plainText = normalized
        .replace(/<[^>]+>/g, ' ')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&#039;/g, "'")
        .replace(/&quot;/g, '"')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/\s+/g, ' ')
        .trim();

    return plainText.length > 0;
}
