import type { ReactComponent } from '@inertiajs/react'

type InertiaPageModule = {
    default: ReactComponent;
};

type InertiaPageResolver = () => Promise<InertiaPageModule>;

const applicationPages = import.meta.glob<InertiaPageModule>('../pages/**/*.tsx');
const pluginPages = import.meta.glob<InertiaPageModule>(
    '../../../plugins/*/resources/js/pages/**/*.tsx',
);

function normalizeApplicationPageName(path: string): string {
    return path.replace('../pages/', '').replace(/\.tsx$/, '');
}

function normalizePluginPageName(path: string): string {
    const matches = path.match(
        /^\.\.\/\.\.\/\.\.\/plugins\/([^/]+)\/resources\/js\/pages\/(.+)\.tsx$/,
    );

    if (!matches) {
        throw new Error(`Unable to normalize plugin page path [${path}].`);
    }

    return matches[2];
}

function buildPageRegistry(): Map<string, InertiaPageResolver> {
    const registry = new Map<string, InertiaPageResolver>();

    Object.entries(applicationPages).forEach(([path, resolver]) => {
        registry.set(normalizeApplicationPageName(path), resolver);
    });

    Object.entries(pluginPages).forEach(([path, resolver]) => {
        registry.set(normalizePluginPageName(path), resolver);
    });

    return registry;
}

const pageRegistry = buildPageRegistry();

export async function resolveInertiaPage(name: string): Promise<ReactComponent> {
    const resolver = pageRegistry.get(name);

    if (!resolver) {
        throw new Error(`Unable to resolve the Inertia page [${name}].`);
    }

    const page = await resolver();

    return page.default;
}