export const INERTIA_HARD_RELOAD_PAGE_LIMIT = 15;
export const INERTIA_HARD_RELOAD_STORAGE_KEY =
    'app:inertia-hard-reload:navigation-count';

export function normalizeInertiaNavigationUrl(url) {
    if (typeof url !== 'string' || url.trim() === '') {
        return null;
    }

    try {
        const resolvedUrl = new URL(url, 'https://example.test');

        return `${resolvedUrl.pathname}${resolvedUrl.search}`;
    } catch {
        return url;
    }
}

export function shouldTrackInertiaNavigation(visit, nextPageUrl, lastPageUrl) {
    if (!visit || typeof visit !== 'object') {
        return false;
    }

    if (typeof visit.method !== 'string' || visit.method.toLowerCase() !== 'get') {
        return false;
    }

    if (visit.prefetch === true) {
        return false;
    }

    if (Array.isArray(visit.only) && visit.only.length > 0) {
        return false;
    }

    if (Array.isArray(visit.except) && visit.except.length > 0) {
        return false;
    }

    const normalizedNextPageUrl = normalizeInertiaNavigationUrl(nextPageUrl);
    const normalizedLastPageUrl = normalizeInertiaNavigationUrl(lastPageUrl);

    if (!normalizedNextPageUrl || !normalizedLastPageUrl) {
        return false;
    }

    return normalizedNextPageUrl !== normalizedLastPageUrl;
}

export function readInertiaNavigationCount(storage) {
    if (!storage) {
        return 0;
    }

    const rawCount = storage.getItem(INERTIA_HARD_RELOAD_STORAGE_KEY);
    const count = Number.parseInt(rawCount ?? '0', 10);

    return Number.isFinite(count) && count > 0 ? count : 0;
}

export function writeInertiaNavigationCount(storage, count) {
    if (!storage) {
        return;
    }

    storage.setItem(INERTIA_HARD_RELOAD_STORAGE_KEY, String(Math.max(0, count)));
}

export function incrementInertiaNavigationCount(storage) {
    const nextCount = readInertiaNavigationCount(storage) + 1;

    writeInertiaNavigationCount(storage, nextCount);

    return nextCount;
}

export function resetInertiaNavigationCount(storage) {
    if (!storage) {
        return;
    }

    storage.removeItem(INERTIA_HARD_RELOAD_STORAGE_KEY);
}

export function shouldForceInertiaHardReload(
    navigationCount,
    threshold = INERTIA_HARD_RELOAD_PAGE_LIMIT,
) {
    return threshold > 0 && navigationCount >= threshold;
}
