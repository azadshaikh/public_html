import assert from 'node:assert/strict';
import test from 'node:test';
import {
    INERTIA_HARD_RELOAD_PAGE_LIMIT,
    incrementInertiaNavigationCount,
    normalizeInertiaNavigationUrl,
    readInertiaNavigationCount,
    resetInertiaNavigationCount,
    shouldForceInertiaHardReload,
    shouldTrackInertiaNavigation,
} from './inertia-session-reload.js';

function createStorage() {
    const values = new Map();

    return {
        getItem(key) {
            return values.has(key) ? values.get(key) : null;
        },
        setItem(key, value) {
            values.set(key, String(value));
        },
        removeItem(key) {
            values.delete(key);
        },
    };
}

test('normalizeInertiaNavigationUrl keeps only path and search', () => {
    assert.equal(
        normalizeInertiaNavigationUrl('https://one.xip.net.in/admin/users?page=2#top'),
        '/admin/users?page=2',
    );
    assert.equal(normalizeInertiaNavigationUrl('/admin/users?page=2'), '/admin/users?page=2');
    assert.equal(normalizeInertiaNavigationUrl(''), null);
});

test('shouldTrackInertiaNavigation ignores partial reloads and repeated urls', () => {
    assert.equal(
        shouldTrackInertiaNavigation(
            { method: 'get', prefetch: false, only: [], except: [] },
            '/admin/users',
            '/admin/dashboard',
        ),
        true,
    );
    assert.equal(
        shouldTrackInertiaNavigation(
            { method: 'get', prefetch: false, only: ['users'], except: [] },
            '/admin/users',
            '/admin/dashboard',
        ),
        false,
    );
    assert.equal(
        shouldTrackInertiaNavigation(
            { method: 'get', prefetch: false, only: [], except: [] },
            '/admin/users',
            '/admin/users',
        ),
        false,
    );
    assert.equal(
        shouldTrackInertiaNavigation(
            { method: 'post', prefetch: false, only: [], except: [] },
            '/admin/users',
            '/admin/dashboard',
        ),
        false,
    );
});

test('navigation count increments and resets cleanly', () => {
    const storage = createStorage();

    assert.equal(readInertiaNavigationCount(storage), 0);
    assert.equal(incrementInertiaNavigationCount(storage), 1);
    assert.equal(incrementInertiaNavigationCount(storage), 2);
    assert.equal(readInertiaNavigationCount(storage), 2);

    resetInertiaNavigationCount(storage);

    assert.equal(readInertiaNavigationCount(storage), 0);
});

test('shouldForceInertiaHardReload flips at the configured threshold', () => {
    assert.equal(
        shouldForceInertiaHardReload(INERTIA_HARD_RELOAD_PAGE_LIMIT - 1),
        false,
    );
    assert.equal(
        shouldForceInertiaHardReload(INERTIA_HARD_RELOAD_PAGE_LIMIT),
        true,
    );
    assert.equal(shouldForceInertiaHardReload(100, 0), false);
});
