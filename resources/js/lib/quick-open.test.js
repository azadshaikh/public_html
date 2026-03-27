import assert from 'node:assert/strict';
import test from 'node:test';
import {
    flattenNavigationForQuickOpen,
    getQuickOpenResults,
    isQuickOpenShortcut,
    normalizeSearchText,
    pushRecentQuickOpenUrl,
    QUICK_OPEN_RECENT_LIMIT,
    readRecentQuickOpenUrls,
    resolveRecentQuickOpenEntries,
    scoreQuickOpenEntry,
} from './quick-open.js';

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

test('flattenNavigationForQuickOpen includes search-only items and enabled action entries', () => {
    const entries = flattenNavigationForQuickOpen({
        top: [
            {
                key: 'core',
                label: 'Core',
                area: 'top',
                items: [
                    {
                        key: 'dashboard',
                        label: 'Dashboard',
                        url: '/dashboard',
                        icon: null,
                        active: false,
                        active_patterns: [],
                        children: [],
                        hasChildren: false,
                    },
                    {
                        key: 'settings',
                        label: 'Settings',
                        url: '/settings',
                        icon: null,
                        active: false,
                        active_patterns: [],
                        children: [
                            {
                                key: 'users',
                                label: 'Users',
                                url: '/settings/users',
                                icon: null,
                                active: false,
                                active_patterns: [],
                                children: [],
                                hasChildren: false,
                            },
                            {
                                key: 'users-create',
                                label: 'Create User',
                                url: '/settings/users/create',
                                icon: null,
                                active: false,
                                active_patterns: [],
                                sidebar_visible: false,
                                quick_open: {
                                    priority: 250,
                                    keywords: ['add user'],
                                },
                                children: [],
                                hasChildren: false,
                            },
                        ],
                        hasChildren: true,
                    },
                ],
            },
        ],
        cms: [],
        modules: [],
        bottom: [
            {
                key: 'account',
                label: 'Account',
                area: 'bottom',
                items: [
                    {
                        key: 'logout',
                        label: 'Log out',
                        url: '/logout',
                        icon: null,
                        active: false,
                        active_patterns: [],
                        attributes: {
                            method: 'post',
                        },
                        quick_open: {
                            aliases: ['Sign out'],
                            keywords: ['end session'],
                        },
                        children: [],
                        hasChildren: false,
                    },
                ],
            },
        ],
    });

    assert.deepEqual(
        entries.map((entry) => ({
            label: entry.label,
            url: entry.url,
            trail: entry.trail,
            sectionLabel: entry.sectionLabel,
        })),
        [
            {
                label: 'Dashboard',
                url: '/dashboard',
                trail: '',
                sectionLabel: 'Core',
            },
            {
                label: 'Settings',
                url: '/settings',
                trail: '',
                sectionLabel: 'Core',
            },
            {
                label: 'Users',
                url: '/settings/users',
                trail: 'Settings',
                sectionLabel: 'Core',
            },
            {
                label: 'Create User',
                url: '/settings/users/create',
                trail: 'Settings',
                sectionLabel: 'Core',
            },
            {
                label: 'Log out',
                url: '/logout',
                trail: '',
                sectionLabel: 'Account',
            },
        ],
    );

    assert.equal(entries.at(-1)?.method, 'post');
});

test('flattenNavigationForQuickOpen excludes disabled quick-open actions', () => {
    const entries = flattenNavigationForQuickOpen({
        top: [],
        cms: [],
        modules: [],
        bottom: [
            {
                key: 'account',
                label: 'Account',
                area: 'bottom',
                items: [
                    {
                        key: 'logout',
                        label: 'Log out',
                        url: '/logout',
                        icon: null,
                        active: false,
                        active_patterns: [],
                        attributes: {
                            method: 'post',
                        },
                        quick_open: {
                            enabled: false,
                        },
                        children: [],
                        hasChildren: false,
                    },
                ],
            },
        ],
    });

    assert.deepEqual(entries, []);
});

test('pushRecentQuickOpenUrl deduplicates and caps the recent history', () => {
    const storage = createStorage();

    for (let index = 0; index < QUICK_OPEN_RECENT_LIMIT + 3; index += 1) {
        pushRecentQuickOpenUrl(storage, `/page-${index}`);
    }

    pushRecentQuickOpenUrl(storage, '/page-3');

    assert.deepEqual(readRecentQuickOpenUrls(storage), [
        '/page-3',
        '/page-10',
        '/page-9',
        '/page-8',
        '/page-7',
        '/page-6',
        '/page-5',
        '/page-4',
    ]);
});

test('resolveRecentQuickOpenEntries preserves recent order and ignores unknown urls', () => {
    const entries = [
        {
            label: 'Dashboard',
            normalizedUrl: '/dashboard',
        },
        {
            label: 'Users',
            normalizedUrl: '/users',
        },
        {
            label: 'Settings',
            normalizedUrl: '/settings',
        },
    ];

    const recentEntries = resolveRecentQuickOpenEntries(entries, [
        '/users',
        '/missing',
        '/dashboard',
    ]);

    assert.deepEqual(
        recentEntries.map((entry) => entry.label),
        ['Users', 'Dashboard'],
    );
});

test('normalizeSearchText strips punctuation and normalizes whitespace', () => {
    assert.equal(
        normalizeSearchText('  Create   Blog/Post  '),
        'create blog post',
    );
});

test('scoreQuickOpenEntry prefers aliases, keywords, and exact label matches', () => {
    const createPostEntry = {
        label: 'Create Post',
        description: 'Write and publish a new blog post',
        sectionLabel: 'CMS',
        trail: 'Blog',
        aliases: ['New Post'],
        keywords: ['create blog post', 'write post'],
        normalizedUrl: '/cms/posts/create',
        priority: 250,
    };

    const postsIndexEntry = {
        label: 'Posts',
        description: null,
        sectionLabel: 'CMS',
        trail: 'Blog',
        aliases: [],
        keywords: ['blog posts'],
        normalizedUrl: '/cms/posts',
        priority: 0,
    };

    assert.ok(
        scoreQuickOpenEntry(createPostEntry, 'create blog') >
        scoreQuickOpenEntry(postsIndexEntry, 'create blog'),
    );
    assert.ok(
        scoreQuickOpenEntry(createPostEntry, 'new post') >
        scoreQuickOpenEntry(postsIndexEntry, 'new post'),
    );
});

test('getQuickOpenResults returns strongest matches first', () => {
    const entries = [
        {
            id: '1',
            label: 'Posts',
            description: null,
            sectionLabel: 'CMS',
            sectionKey: 'cms',
            trail: 'Blog',
            aliases: [],
            keywords: ['blog posts'],
            normalizedUrl: '/cms/posts',
            priority: 0,
            order: 1,
            sectionOrder: 1,
        },
        {
            id: '2',
            label: 'Create Post',
            description: 'Write and publish a new blog post',
            sectionLabel: 'CMS',
            sectionKey: 'cms',
            trail: 'Blog',
            aliases: ['New Post'],
            keywords: ['create blog post', 'write post'],
            normalizedUrl: '/cms/posts/create',
            priority: 250,
            order: 2,
            sectionOrder: 1,
        },
    ];

    assert.deepEqual(
        getQuickOpenResults(entries, 'create blog').map(
            (result) => result.entry.label,
        ),
        ['Create Post'],
    );
});

test('isQuickOpenShortcut only accepts ctrl or cmd plus k', () => {
    assert.equal(
        isQuickOpenShortcut({
            key: 'k',
            ctrlKey: true,
            metaKey: false,
            altKey: false,
            shiftKey: false,
            defaultPrevented: false,
        }),
        true,
    );
    assert.equal(
        isQuickOpenShortcut({
            key: 'K',
            ctrlKey: false,
            metaKey: true,
            altKey: false,
            shiftKey: false,
            defaultPrevented: false,
        }),
        true,
    );
    assert.equal(
        isQuickOpenShortcut({
            key: 'p',
            ctrlKey: true,
            metaKey: false,
            altKey: false,
            shiftKey: false,
            defaultPrevented: false,
        }),
        false,
    );
    assert.equal(
        isQuickOpenShortcut({
            key: 'k',
            ctrlKey: true,
            metaKey: false,
            altKey: false,
            shiftKey: true,
            defaultPrevented: false,
        }),
        false,
    );
});
