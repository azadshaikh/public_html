import { normalizeInertiaNavigationUrl } from './inertia-session-reload.js';

export const QUICK_OPEN_RECENT_LIMIT = 8;
export const QUICK_OPEN_RECENT_STORAGE_KEY = 'app:quick-open:recent-urls';

const SEARCH_DELIMITER_REGEX = /[^\p{L}\p{N}]+/gu;

export function normalizeSearchText(value) {
    if (typeof value !== 'string') {
        return '';
    }

    return value
        .toLowerCase()
        .replace(SEARCH_DELIMITER_REGEX, ' ')
        .trim()
        .replace(/\s+/g, ' ');
}

function compactSearchText(value) {
    return normalizeSearchText(value).replace(/\s+/g, '');
}

function tokenizeSearchText(value) {
    const normalizedValue = normalizeSearchText(value);

    return normalizedValue === '' ? [] : normalizedValue.split(' ');
}

function normalizeSearchTerms(values) {
    if (!Array.isArray(values)) {
        return [];
    }

    return values
        .filter((value) => typeof value === 'string' && value.trim() !== '')
        .map((value) => value.trim());
}

function normalizeQuickOpenUrl(url) {
    return normalizeInertiaNavigationUrl(url);
}

function normalizeRecentQuickOpenUrls(urls) {
    if (!Array.isArray(urls)) {
        return [];
    }

    const seenUrls = new Set();
    const normalizedUrls = [];

    for (const value of urls) {
        const normalizedUrl = normalizeQuickOpenUrl(value);

        if (!normalizedUrl || seenUrls.has(normalizedUrl)) {
            continue;
        }

        seenUrls.add(normalizedUrl);
        normalizedUrls.push(normalizedUrl);
    }

    return normalizedUrls;
}

export function readRecentQuickOpenUrls(storage) {
    if (!storage) {
        return [];
    }

    try {
        const rawValue = storage.getItem(QUICK_OPEN_RECENT_STORAGE_KEY);

        if (typeof rawValue !== 'string' || rawValue.trim() === '') {
            return [];
        }

        return normalizeRecentQuickOpenUrls(JSON.parse(rawValue));
    } catch {
        return [];
    }
}

export function writeRecentQuickOpenUrls(storage, urls) {
    if (!storage) {
        return;
    }

    storage.setItem(
        QUICK_OPEN_RECENT_STORAGE_KEY,
        JSON.stringify(
            normalizeRecentQuickOpenUrls(urls).slice(
                0,
                QUICK_OPEN_RECENT_LIMIT,
            ),
        ),
    );
}

export function pushRecentQuickOpenUrl(
    storage,
    url,
    limit = QUICK_OPEN_RECENT_LIMIT,
) {
    const normalizedUrl = normalizeQuickOpenUrl(url);

    if (!storage || !normalizedUrl) {
        return [];
    }

    const nextUrls = [
        normalizedUrl,
        ...readRecentQuickOpenUrls(storage).filter(
            (storedUrl) => storedUrl !== normalizedUrl,
        ),
    ].slice(0, limit);

    writeRecentQuickOpenUrls(storage, nextUrls);

    return nextUrls;
}

export function isQuickOpenShortcut(event) {
    if (!event || typeof event !== 'object') {
        return false;
    }

    if (
        typeof event.key !== 'string' ||
        typeof event.ctrlKey !== 'boolean' ||
        typeof event.metaKey !== 'boolean'
    ) {
        return false;
    }

    if (
        event.defaultPrevented === true ||
        event.altKey === true ||
        event.shiftKey === true
    ) {
        return false;
    }

    if (!(event.ctrlKey || event.metaKey)) {
        return false;
    }

    return event.key.toLowerCase() === 'k';
}

function createQuickOpenEntry(item, section, ancestors, sectionOrder, order) {
    if (
        typeof item.url !== 'string' ||
        item.url.trim() === '' ||
        item.url.trim() === '#'
    ) {
        return null;
    }

    const method =
        typeof item.attributes?.method === 'string'
            ? item.attributes.method.toLowerCase()
            : null;

    if (method && method !== 'get') {
        return null;
    }

    const quickOpen =
        item.quick_open && typeof item.quick_open === 'object'
            ? item.quick_open
            : {};

    if (quickOpen.enabled === false) {
        return null;
    }

    const trail = ancestors.join(' / ');
    const normalizedUrl = normalizeQuickOpenUrl(item.url);

    if (!normalizedUrl) {
        return null;
    }

    const aliases = normalizeSearchTerms(quickOpen.aliases);
    const keywords = normalizeSearchTerms(quickOpen.keywords);
    const description =
        typeof quickOpen.description === 'string' &&
        quickOpen.description.trim() !== ''
            ? quickOpen.description.trim()
            : null;

    return {
        id: `${section.key}:${item.key}:${normalizedUrl}`,
        key: item.key,
        label: item.label,
        description,
        url: item.url,
        normalizedUrl,
        icon: item.icon ?? null,
        sectionKey: section.key,
        sectionLabel: section.label,
        sectionArea: section.area,
        trail,
        aliases,
        keywords,
        priority:
            typeof quickOpen.priority === 'number'
                ? quickOpen.priority
                : Number.parseInt(String(quickOpen.priority ?? '0'), 10) || 0,
        hardReload: item.hard_reload === true,
        target: item.target ?? null,
        sidebarVisible: item.sidebar_visible !== false,
        order,
        sectionOrder,
    };
}

function flattenNavigationItems(items, section, ancestors, entries, context) {
    for (const item of items ?? []) {
        const entry = createQuickOpenEntry(
            item,
            section,
            ancestors,
            context.sectionOrder,
            context.order,
        );

        if (entry) {
            entries.push(entry);
            context.order += 1;
        }

        if (Array.isArray(item.children) && item.children.length > 0) {
            flattenNavigationItems(
                item.children,
                section,
                [...ancestors, item.label],
                entries,
                context,
            );
        }
    }
}

export function flattenNavigationForQuickOpen(navigation) {
    const entries = [];
    const sections = [
        ...(navigation?.top ?? []),
        ...(navigation?.cms ?? []),
        ...(navigation?.modules ?? []),
        ...(navigation?.bottom ?? []),
    ];
    const context = {
        order: 0,
        sectionOrder: 0,
    };

    for (const section of sections) {
        flattenNavigationItems(
            section.items ?? [],
            section,
            [],
            entries,
            context,
        );
        context.sectionOrder += 1;
    }

    return entries;
}

export function resolveRecentQuickOpenEntries(entries, recentUrls) {
    const entryByUrl = new Map();

    for (const entry of entries) {
        if (!entryByUrl.has(entry.normalizedUrl)) {
            entryByUrl.set(entry.normalizedUrl, entry);
        }
    }

    return normalizeRecentQuickOpenUrls(recentUrls)
        .map((recentUrl) => entryByUrl.get(recentUrl) ?? null)
        .filter(Boolean);
}

function getAcronym(value) {
    return tokenizeSearchText(value)
        .map((token) => token[0] ?? '')
        .join('');
}

function scoreTermBucket(
    terms,
    query,
    exactWeight,
    prefixWeight,
    includeWeight,
) {
    let bestScore = 0;

    for (const term of terms) {
        const normalizedTerm = normalizeSearchText(term);

        if (normalizedTerm === '') {
            continue;
        }

        if (normalizedTerm === query) {
            bestScore = Math.max(bestScore, exactWeight);
            continue;
        }

        if (normalizedTerm.startsWith(query)) {
            bestScore = Math.max(bestScore, prefixWeight);
            continue;
        }

        if (normalizedTerm.includes(query)) {
            bestScore = Math.max(bestScore, includeWeight);
        }
    }

    return bestScore;
}

export function scoreQuickOpenEntry(entry, search, recentUrls = []) {
    const normalizedQuery = normalizeSearchText(search);

    if (normalizedQuery === '') {
        return 1;
    }

    const queryTokens = tokenizeSearchText(normalizedQuery);
    const labelWords = tokenizeSearchText(entry.label);
    const aliasWords = entry.aliases.flatMap((alias) =>
        tokenizeSearchText(alias),
    );
    const keywordWords = entry.keywords.flatMap((keyword) =>
        tokenizeSearchText(keyword),
    );
    const contextWords = tokenizeSearchText(
        [entry.sectionLabel, entry.trail, entry.description]
            .filter(Boolean)
            .join(' '),
    );
    const searchDocument = normalizeSearchText(
        [
            entry.label,
            entry.description,
            entry.sectionLabel,
            entry.trail,
            ...entry.aliases,
            ...entry.keywords,
        ]
            .filter(Boolean)
            .join(' '),
    );

    let score = 0;

    score += scoreTermBucket([entry.label], normalizedQuery, 1400, 1100, 800);
    score += scoreTermBucket(entry.aliases, normalizedQuery, 1200, 960, 700);
    score += scoreTermBucket(entry.keywords, normalizedQuery, 900, 760, 620);
    score += scoreTermBucket(
        [entry.sectionLabel, entry.trail, entry.description].filter(Boolean),
        normalizedQuery,
        420,
        320,
        200,
    );

    const compactQuery = compactSearchText(normalizedQuery);
    const labelAcronym = getAcronym(
        [entry.label, entry.trail].filter(Boolean).join(' '),
    );

    if (compactQuery.length > 1 && labelAcronym.startsWith(compactQuery)) {
        score += 260;
    }

    if (compactSearchText(entry.label).includes(compactQuery)) {
        score += 160;
    }

    for (const token of queryTokens) {
        if (labelWords.includes(token)) {
            score += 220;
            continue;
        }

        if (labelWords.some((word) => word.startsWith(token))) {
            score += 180;
            continue;
        }

        if (aliasWords.includes(token)) {
            score += 170;
            continue;
        }

        if (aliasWords.some((word) => word.startsWith(token))) {
            score += 145;
            continue;
        }

        if (keywordWords.includes(token)) {
            score += 135;
            continue;
        }

        if (keywordWords.some((word) => word.startsWith(token))) {
            score += 115;
            continue;
        }

        if (contextWords.includes(token)) {
            score += 100;
            continue;
        }

        if (contextWords.some((word) => word.startsWith(token))) {
            score += 80;
            continue;
        }

        if (searchDocument.includes(token)) {
            score += 45;
            continue;
        }

        return 0;
    }

    if (
        new Set(normalizeRecentQuickOpenUrls(recentUrls)).has(
            entry.normalizedUrl,
        )
    ) {
        score += 35;
    }

    score += entry.priority;

    return score;
}

export function getQuickOpenResults(entries, search, recentUrls = []) {
    const normalizedQuery = normalizeSearchText(search);

    if (normalizedQuery === '') {
        return entries
            .map((entry) => ({
                entry,
                score: 1,
            }))
            .sort((left, right) => {
                if (left.entry.sectionOrder !== right.entry.sectionOrder) {
                    return left.entry.sectionOrder - right.entry.sectionOrder;
                }

                return left.entry.order - right.entry.order;
            });
    }

    return entries
        .map((entry) => ({
            entry,
            score: scoreQuickOpenEntry(entry, normalizedQuery, recentUrls),
        }))
        .filter((result) => result.score > 0)
        .sort((left, right) => {
            if (left.score !== right.score) {
                return right.score - left.score;
            }

            if (left.entry.priority !== right.entry.priority) {
                return right.entry.priority - left.entry.priority;
            }

            if (left.entry.sectionOrder !== right.entry.sectionOrder) {
                return left.entry.sectionOrder - right.entry.sectionOrder;
            }

            return left.entry.order - right.entry.order;
        });
}
