/**
 * Database Block Provider
 *
 * Fetches blocks from the Astero Design Blocks API (DesignBlock model).
 * Replaces the parsing logic previously in astero-design-blocks.js.
 */
class DatabaseProvider extends Astero.BlockProvider {
    constructor() {
        super('database', 5); // Higher priority than built-in (0), lower than theme (10)
        this.cache = {
            blocks: {},
            sections: {},
            categories: {
                blocks: [],
                sections: [],
            },
        };
        this.loaded = false;
        this.loadingPromise = null;
    }

    /**
     * Ensure data is loaded from API
     */
    async loadData() {
        if (this.loaded) return;
        if (this.loadingPromise) return this.loadingPromise;

        this.loadingPromise = new Promise((resolve, reject) => {
            // Astero.designBlocksUrl should be defined in builder layout/view similar to blocksurl
            // Falling back to window.blocksurl or specific API endpoint if needed
            const url = window.blocksurl || '/api/builder/blocks';

            fetch(url)
                .then((response) => response.json())
                .then((data) => {
                    if (data.success) {
                        this.processData(data);
                        this.loaded = true;
                        resolve();
                    } else {
                        console.error('[DatabaseProvider] Failed to load:', data.message);
                        resolve(); // Resolve anyway to avoid blocking
                    }
                })
                .catch((err) => {
                    console.error('[DatabaseProvider] Network error:', err);
                    resolve();
                });
        });

        return this.loadingPromise;
    }

    /**
     * Process API response into internal cache
     */
    processData(data) {
        // Process Blocks
        if (data.blocks) {
            Object.keys(data.blocks).forEach((category) => {
                this.cache.categories.blocks.push(category);
                this.cache.blocks[category] = data.blocks[category].map((item) => this.mapItem(item, 'block'));
            });
        }

        // Process Sections
        if (data.sections) {
            Object.keys(data.sections).forEach((category) => {
                this.cache.categories.sections.push(category);
                this.cache.sections[category] = data.sections[category].map((item) => this.mapItem(item, 'section'));
            });
        }
    }

    /**
     * Map API item to Block format
     */
    mapItem(item, type = 'block') {
        return {
            slug: item.id,
            id: item.id,
            name: item.name || item.title,
            image: item.image,
            html: item.html,
            description: '',
            css: item.css || '',
            js: item.js || '',
            type: type, // 'block' or 'section' - used by Registry.getBlockHtml
            source: 'database',
            _original: item,
        };
    }

    /**
     * Get categories
     */
    async getCategories(type) {
        await this.loadData();
        if (type === 'section') {
            return this.cache.categories.sections;
        } else if (type === 'block') {
            return this.cache.categories.blocks;
        }
        return [];
    }

    /**
     * Get blocks for category
     */
    async getBlocks(category, type) {
        await this.loadData();

        let pool = {};
        if (type === 'section') pool = this.cache.sections;
        else if (type === 'block') pool = this.cache.blocks;

        // Case-insensitive lookup
        const key = Object.keys(pool).find((k) => k.toLowerCase() === category.toLowerCase());

        return key ? pool[key] : [];
    }

    /**
     * Get single block (inefficient scan, but ok for now)
     */
    async getBlock(slug) {
        await this.loadData();

        // Scan all sections and blocks
        const all = [...Object.values(this.cache.sections).flat(), ...Object.values(this.cache.blocks).flat()];

        return all.find((b) => b.slug === slug) || null;
    }
}

// Register
if (window.Astero && Astero.Registry) {
    Astero.Registry.addProvider('database', new DatabaseProvider());
} else {
    window.addEventListener('load', () => {
        if (window.Astero && Astero.Registry) {
            Astero.Registry.addProvider('database', new DatabaseProvider());
        }
    });
}
