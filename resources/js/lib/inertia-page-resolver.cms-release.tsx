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
    '../../../modules/CMS/resources/js/pages/**/*.tsx',
);

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

let enabledModuleIdentifiers: Set<string> | null = null;

function buildPageRegistry(): Map<string, InertiaPageResolver> {
    const registry = new Map<string, InertiaPageResolver>();

    Object.entries(applicationPages).forEach(([path, resolver]) => {
        registry.set(normalizeApplicationPageName(path), resolver);
    });

    Object.entries(modulePages).forEach(([path, resolver]) => {
        const directoryName = extractModuleDirectoryName(path);
        const pageNamespace = extractModulePageNamespace(path);

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

    pageRegistry = buildPageRegistry();
}

export async function resolveInertiaPage(name: string): Promise<ComponentType> {
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