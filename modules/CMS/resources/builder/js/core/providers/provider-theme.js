/**
 * Theme Block Provider
 *
 * Fetches blocks and sections from the active theme via the ThemeBlockService API.
 * Handles manifest retrieval, caching, and HTML rendering.
 */
class ThemeProvider extends Astero.BlockProvider {
    constructor() {
        super('theme', 100); // High priority (overrides built-in and database)

        this.sectionsManifest = null;
        this.blocksManifest = null;
        this.htmlCache = new Map();

        // State tracking
        this.initialized = false;
        this.themeName = null;
    }

    /**
     * Initialize the provider with the current theme name
     * This is called by the builder when it starts
     */
    async init() {
        if (this.initialized) return;

        // Get current theme from builder configuration or global Astero object
        // Astero.config.theme should be populated by the builder backend load
        this.themeName = window.Astero?.config?.theme || 'default';
        console.log(
            `[ThemeProvider] Initializing for theme: ${this.themeName}`,
        );

        try {
            // Load manifests in parallel
            // Routes are defined in web.php under cms.builder.theme.*
            // URL format: /{admin_slug}/cms/builder/theme/{type}?theme={themeName}

            // We need to construct the URL correctly based on the admin prefix
            // Typically available as Astero.baseUrl or similar, but let's assume relative path works
            // if we are in the iframe.
            // Better to use the global configured routes if available, or construct standard path.
            // Astero.routes.theme_manifest might be useful if injected.
            // Fallback to standard conventions:
            const baseUrl = window.Astero?.config?.builderUrl || '/cms/builder';

            const [sectionsRes, blocksRes] = await Promise.all([
                fetch(`${baseUrl}/theme/sections?theme=${this.themeName}`),
                fetch(`${baseUrl}/theme/blocks?theme=${this.themeName}`),
            ]);

            if (sectionsRes.ok) {
                this.sectionsManifest = await sectionsRes.json();
            } else {
                console.warn(
                    '[ThemeProvider] Failed to load sections manifest',
                    sectionsRes.statusText,
                );
                this.sectionsManifest = { items: [] };
            }

            if (blocksRes.ok) {
                this.blocksManifest = await blocksRes.json();
            } else {
                console.warn(
                    '[ThemeProvider] Failed to load blocks manifest',
                    blocksRes.statusText,
                );
                this.blocksManifest = { items: [] };
            }

            this.initialized = true;
            console.log(
                `[ThemeProvider] Found ${this.sectionsManifest.items.length} sections, ${this.blocksManifest.items.length} blocks`,
            );
        } catch (e) {
            console.error('[ThemeProvider] Initialization failed', e);
            this.sectionsManifest = { items: [] };
            this.blocksManifest = { items: [] };
        }
    }

    /**
     * Get categories
     */
    async getCategories(type) {
        if (!this.initialized) await this.init();

        const manifest =
            type === 'section' ? this.sectionsManifest : this.blocksManifest;
        if (!manifest || !manifest.items) return [];

        return [...new Set(manifest.items.map((item) => item.category))];
    }

    /**
     * Get blocks for a category
     */
    async getBlocks(category, type) {
        if (!this.initialized) await this.init();

        const manifest =
            type === 'section' ? this.sectionsManifest : this.blocksManifest;
        if (!manifest || !manifest.items) return [];

        // Filter and map to Registry format
        return manifest.items
            .filter(
                (item) =>
                    item.category.toLowerCase() === category.toLowerCase(),
            )
            .map((item) => {
                const mappedItem = {
                    slug: item.slug, // "category/name"
                    id: item.id || item.slug,
                    name: item.name,
                    category: item.category,
                    image: item.image, // URL to preview image
                    description: item.description,
                    html: item.html || null, // May be null - fetched on-demand via fetchBlockHtml
                    type: type, // 'section' or 'block' - used by Registry.getBlockHtml
                    source: 'theme',
                };

                // Register into legacy storage for drag-drop compatibility
                // builder-dragdrop.js uses Astero.Blocks.get(slug) synchronously
                // Note: HTML may be lazy-loaded later
                if (type === 'section') {
                    if (window.Astero && Astero.Sections) {
                        Astero.Sections.add(item.slug, mappedItem);
                    }
                } else {
                    if (window.Astero && Astero.Blocks) {
                        Astero.Blocks.add(item.slug, mappedItem);
                    }
                }

                return mappedItem;
            });
    }

    /**
     * Get a single block by slug
     * This is where we fetch the actual HTML
     */
    async getBlock(slug) {
        if (!this.initialized) await this.init();

        const type = this.sectionsManifest?.items.find((i) => i.slug === slug)
            ? 'sections'
            : 'blocks';

        // Check cache
        const cacheKey = `${type}:${slug}`;
        if (this.htmlCache.has(cacheKey)) {
            // Merge cached HTML with manifest data
            return this._mergeWithManifest(
                slug,
                type,
                this.htmlCache.get(cacheKey),
            );
        }

        // If manifest already has HTML, return it
        const manifest =
            type === 'sections' ? this.sectionsManifest : this.blocksManifest;
        const item = manifest.items.find((i) => i.slug === slug);
        if (item && item.html) {
            return this._mergeWithManifest(slug, type, item.html);
        }

        try {
            const baseUrl = window.Astero?.config?.builderUrl || '/cms/builder';
            const response = await fetch(`${baseUrl}/theme/${type}/render`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content'),
                },
                body: JSON.stringify({
                    theme: this.themeName,
                    slug: slug,
                }),
            });

            if (!response.ok) throw new Error('Render failed');

            const data = await response.json();
            this.htmlCache.set(cacheKey, data.html); // Persist in memory

            // Also update legacy storage if needed
            if (type === 'sections' && window.Astero && Astero.Sections) {
                Astero.Sections.add(slug, { ...item, html: data.html });
            } else if (window.Astero && Astero.Blocks) {
                Astero.Blocks.add(slug, { ...item, html: data.html });
            }

            return this._mergeWithManifest(slug, type, data.html);
        } catch (e) {
            console.error(`[ThemeProvider] Failed to render ${slug}`, e);
            return null;
        }
    }

    _mergeWithManifest(slug, type, html) {
        const manifest =
            type === 'sections' ? this.sectionsManifest : this.blocksManifest;
        const item = manifest.items.find((i) => i.slug === slug);

        if (!item)
            return {
                slug,
                html,
                type: type === 'sections' ? 'section' : 'block',
            };

        return {
            slug: item.slug,
            id: item.id || item.slug,
            name: item.name,
            category: item.category,
            image: item.image,
            description: item.description,
            html: html,
            type: type === 'sections' ? 'section' : 'block',
            source: 'theme',
        };
    }

    /**
     * Fetch HTML for a specific block/section (called by Registry.getBlockHtml)
     * @param {string} slug - Block slug (e.g., "hero/hero-gradient")
     * @param {string} type - 'block' or 'section'
     * @returns {Promise<{html: string}|null>}
     */
    async fetchBlockHtml(slug, type) {
        if (!this.initialized) await this.init();

        // Normalize type for API endpoint ('blocks' or 'sections')
        const apiType = type === 'section' ? 'sections' : 'blocks';
        const cacheKey = `${apiType}:${slug}`;

        // Check cache first
        if (this.htmlCache.has(cacheKey)) {
            return { html: this.htmlCache.get(cacheKey) };
        }

        // Check if manifest already has HTML
        const manifest =
            apiType === 'sections'
                ? this.sectionsManifest
                : this.blocksManifest;
        const item = manifest?.items?.find((i) => i.slug === slug);
        if (item?.html) {
            return { html: item.html };
        }

        // Fetch from server
        try {
            const baseUrl = window.Astero?.config?.builderUrl || '/cms/builder';
            const response = await fetch(`${baseUrl}/theme/${apiType}/render`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content'),
                },
                body: JSON.stringify({
                    theme: this.themeName,
                    slug: slug,
                }),
            });

            if (!response.ok)
                throw new Error(`Render failed: ${response.status}`);

            const data = await response.json();
            this.htmlCache.set(cacheKey, data.html);

            // Update legacy storage
            if (apiType === 'sections' && window.Astero?.Sections) {
                const existing = Astero.Sections.get(slug) || {};
                Astero.Sections.add(slug, { ...existing, html: data.html });
            } else if (window.Astero?.Blocks) {
                const existing = Astero.Blocks.get(slug) || {};
                Astero.Blocks.add(slug, { ...existing, html: data.html });
            }

            return { html: data.html };
        } catch (e) {
            console.error(
                `[ThemeProvider] Failed to fetch HTML for ${slug}:`,
                e,
            );
            return null;
        }
    }
}

// Register with the global registry
if (window.Astero && Astero.Registry) {
    Astero.Registry.addProvider('theme', new ThemeProvider());
} else {
    window.addEventListener('load', () => {
        if (window.Astero && Astero.Registry) {
            Astero.Registry.addProvider('theme', new ThemeProvider());
        }
    });
}
