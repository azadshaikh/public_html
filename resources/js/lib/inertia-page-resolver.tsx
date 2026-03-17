import type { ComponentType } from 'react';

type InertiaPageModule = {
    default: ComponentType;
};

type InertiaPageResolver = () => Promise<InertiaPageModule>;

type SharedModuleDescriptor = {
    name?: string;
    slug?: string;
    inertiaNamespace?: string;
};

const applicationPages = import.meta.glob<InertiaPageModule>(
    '../pages/**/*.tsx',
);
const modulePages = import.meta.glob<InertiaPageModule>(
    '../../../modules/*/resources/js/pages/**/*.tsx',
);

/**
 * Module page path pattern — extracts the folder name (module directory name)
 * and the relative page path from the glob key.
 */
const MODULE_PAGE_PATTERN =
    /^\.\.\/\.\.\/\.\.\/modules\/([^/]+)\/resources\/js\/pages\/(.+)\.tsx$/;

function normalizeApplicationPageName(path: string): string {
    return path.replace('../pages/', '').replace(/\.tsx$/, '');
}

function extractModuleDirectoryName(path: string): string | null {
    const matches = path.match(MODULE_PAGE_PATTERN);

    return matches?.[1] ?? null;
}

function normalizeModulePageName(path: string): string {
    const matches = path.match(MODULE_PAGE_PATTERN);

    if (!matches) {
        throw new Error(`Unable to normalize module page path [${path}].`);
    }

    return matches[2];
}

function normalizeModuleIdentifier(value: string): string {
    return value.trim().replace(/^\/+|\/+$/g, '').toLowerCase();
}

function extractModulePageNamespace(path: string): string | null {
    const [namespace] = normalizeModulePageName(path).split('/');

    return namespace ?? null;
}

/**
 * Set of enabled module identifiers.
 *
 * Includes any shared module `name`, `slug`, and `inertiaNamespace`
 * values so page filtering stays resilient when the module display name,
 * slug, and on-disk directory name differ.
 * Populated on first page resolution from the Inertia initial page props.
 */
let enabledModuleIdentifiers: Set<string> | null = null;

function buildPageRegistry(): Map<string, InertiaPageResolver> {
    const registry = new Map<string, InertiaPageResolver>();

    Object.entries(applicationPages).forEach(([path, resolver]) => {
        registry.set(normalizeApplicationPageName(path), resolver);
    });

    Object.entries(modulePages).forEach(([path, resolver]) => {
        const directoryName = extractModuleDirectoryName(path);
        const pageNamespace = extractModulePageNamespace(path);

        // Skip pages from disabled modules. When enabledModuleNames is null
        // (first full-page load before props are available), include all pages
        // as a safe fallback — the server-side module.enabled middleware is
        // the authoritative guard.
        if (
            enabledModuleIdentifiers !== null &&
            ![directoryName, pageNamespace]
                .filter((identifier): identifier is string => identifier !== null)
                .map(normalizeModuleIdentifier)
                .some((identifier) => enabledModuleIdentifiers?.has(identifier) === true)
        ) {
            return;
        }

        registry.set(normalizeModulePageName(path), resolver);
    });

    return registry;
}

let pageRegistry: Map<string, InertiaPageResolver> | null = null;

/**
 * Initialise the enabled-modules set from shared Inertia props.
 * Called once during `createInertiaApp` setup so the very first page
 * resolution already filters disabled modules.
 */
export function initModulePageFilter(
    modules: { items: SharedModuleDescriptor[] } | undefined,
): void {
    if (modules?.items) {
        enabledModuleIdentifiers = new Set(
            modules.items.flatMap((moduleItem) =>
                [
                    moduleItem.name,
                    moduleItem.slug,
                    moduleItem.inertiaNamespace,
                ]
                    .filter((value): value is string => typeof value === 'string')
                    .map(normalizeModuleIdentifier),
            ),
        );
    } else {
        enabledModuleIdentifiers = null;
    }

    // (Re)build the page registry with the now-known enabled set.
    pageRegistry = buildPageRegistry();
}

export async function resolveInertiaPage(name: string): Promise<ComponentType> {
    // Lazy-build on first call if initModulePageFilter was not called.
    if (pageRegistry === null) {
        pageRegistry = buildPageRegistry();
    }

    const resolver = pageRegistry.get(name);

    if (!resolver) {
        throw new Error(`Unable to resolve the Inertia page [${name}].`);
    }

    const page = await resolver();

    return page.default;
}
