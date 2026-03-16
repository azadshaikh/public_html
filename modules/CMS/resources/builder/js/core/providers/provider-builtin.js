/**
 * Built-in Block Provider
 *
 * Adapts the legacy Astero.Sections and Astero.Blocks storage into the
 * new Registry provider pattern. This ensures backward compatibility
 * with existing section definitions, while adding lazy loading support.
 */
class BuiltinProvider extends Astero.BlockProvider {
    constructor() {
        super('builtin', 0); // Lowest priority

        // Lazy Loading Map
        this.sectionImports = {
            Hero: () => import('../../sections/landing/hero.js'),
            Features: () => import('../../sections/landing/features.js'),
            Base: () => import('../../sections/landing/base.js'),
            'Contact form': () => import('../../sections/landing/contact-form.js'),
            Footer: () => import('../../sections/landing/footer.js'),
            Navigation: () => import('../../sections/landing/navigation.js'),
            Posts: () => import('../../sections/landing/posts.js'),
            'Pricing table': () => import('../../sections/landing/pricing-table.js'),
            Products: () => import('../../sections/landing/products.js'),
            Showcase: () => import('../../sections/landing/showcase.js'),
            Team: () => import('../../sections/landing/team.js'),
            Testimonials: () => import('../../sections/landing/testimonials.js'),
        };

        this.blockImports = {
            Bootstrap: () => import('../../blocks/bootstrap5/index.js'),
        };
    }

    /**
     * Get categories
     */
    async getCategories(type) {
        if (type === 'section') {
            // Combine already loaded categories + available lazy ones
            const lazyCats = Object.keys(this.sectionImports);
            const loadedCats = Object.keys(Astero.SectionsGroup || {});
            return [...new Set([...lazyCats, ...loadedCats])];
        } else if (type === 'block') {
            const lazyCats = Object.keys(this.blockImports);
            const loadedCats = Object.keys(Astero.BlocksGroup || {});
            return [...new Set([...lazyCats, ...loadedCats])];
        }
        return [];
    }

    /**
     * Get blocks for a category by looking up the legacy groups
     * Triggers lazy load if needed.
     */
    async getBlocks(category, type) {
        // Normalize Category Key (Case-insensitive matching)
        let exactCategory = category;

        const imports = type === 'section' ? this.sectionImports : this.blockImports;
        const groupObjGlobal = type === 'section' ? Astero.SectionsGroup : Astero.BlocksGroup;

        // Find exact category name from known imports or loaded groups
        const knownCategories = [...Object.keys(imports), ...Object.keys(groupObjGlobal || {})];
        exactCategory = knownCategories.find((k) => k.toLowerCase() === category.toLowerCase()) || category;

        // Check if we need to load it
        // We check the global object freshly here in case it was just loaded
        const currentGroupObj = type === 'section' ? Astero.SectionsGroup : Astero.BlocksGroup;

        if (!currentGroupObj || !currentGroupObj[exactCategory]) {
            if (imports[exactCategory]) {
                try {
                    // console.log(`[BuiltinProvider] Lazy loading ${type} category: ${exactCategory}`);
                    await imports[exactCategory]();
                } catch (e) {
                    console.error(`[BuiltinProvider] Failed to load ${exactCategory}`, e);
                }
            }
        }

        let items = [];
        let groupResult = null;
        let getFn = null;

        if (type === 'section') {
            groupResult = Astero.SectionsGroup;
            getFn = (slug) => Astero.Sections.get(slug);
        } else if (type === 'block') {
            groupResult = Astero.BlocksGroup;
            getFn = (slug) => Astero.Blocks.get(slug);
        }

        if (groupResult && groupResult[exactCategory]) {
            const slugs = groupResult[exactCategory];

            items = slugs
                .map((slug) => {
                    const item = getFn(slug);
                    if (!item) return null;

                    // Normalize item structure for Registry
                    return {
                        slug: slug,
                        id: slug, // Keep both for safety
                        name: item.name,
                        category: exactCategory,
                        image: item.image,
                        html: item.html,
                        description: item.description || '',
                        ...item, // Include any other properties
                    };
                })
                .filter((item) => item !== null);
        }

        return items;
    }

    /**
     * Get a single block by slug
     */
    async getBlock(slug) {
        let item = Astero.Sections.get(slug);
        if (!item) item = Astero.Blocks.get(slug);

        if (item) {
            return {
                slug: slug,
                id: slug,
                ...item,
            };
        }
        return null;
    }

    /**
     * Register a new block/section (Backward compatibility wrapper)
     * allows new code to register via provider if desired,
     * though Astero.Sections.add is preferred for now.
     */
    register(type, slug, data) {
        if (type === 'section') {
            Astero.Sections.add(slug, data);
        } else {
            Astero.Blocks.add(slug, data);
        }
    }
}

// Register with the global registry
// We wrap in a timeout or confirm Astero.Registry is available
if (window.Astero && Astero.Registry) {
    Astero.Registry.addProvider('builtin', new BuiltinProvider());
} else {
    // Fallback if loaded out of order, though editor.js should handle order
    window.addEventListener('load', () => {
        if (window.Astero && Astero.Registry) {
            Astero.Registry.addProvider('builtin', new BuiltinProvider());
        }
    });
}
