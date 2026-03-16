/**
 * BlockProvider Interface/Base Class
 *
 * All block providers should extend or implement this interface.
 */
class BlockProvider {
    constructor(name, priority = 10) {
        this.name = name;
        this.priority = priority;
    }

    /**
     * Get unique categories available in this provider
     * @param {string} type - 'section' or 'block'
     * @return {Promise<string[]>}
     */
    async getCategories(type) {
        return [];
    }

    /**
     * Get blocks for a specific category
     * @param {string} category
     * @param {string} type - 'section' or 'block'
     * @return {Promise<Array>}
     */
    async getBlocks(category, type) {
        return [];
    }

    /**
     * Get a specific block by slug
     * @param {string} slug
     * @return {Promise<Object>}
     */
    async getBlock(slug) {
        return null;
    }
}

/**
 * Astero.Registry
 *
 * Central registry for managing blocks and sections from multiple sources
 * (Built-in, Database, Theme, etc.)
 */
class Registry {
    constructor() {
        this.providers = {};
        this.loadedCategories = new Set();
        this.cache = {}; // Memory cache for blocks
        this.htmlCache = new Map(); // Memory cache for rendered HTML
    }

    /**
     * Register a block provider
     * @param {string} name
     * @param {BlockProvider} providerInstance
     */
    addProvider(name, providerInstance) {
        this.providers[name] = providerInstance;
        console.log(`[Registry] Added provider: ${name}`);
    }

    /**
     * Load blocks for a specific category from all providers
     * @param {string} type - 'section' or 'block'
     * @param {string} category
     */
    async loadCategory(type, category) {
        const cacheKey = `${type}:${category}`;

        // Return from cache if available
        if (this.cache[cacheKey]) {
            return this.cache[cacheKey];
        }

        const providerKeys = Object.keys(this.providers);

        // Fetch from all providers in parallel
        const promises = providerKeys.map((key) => {
            return this.providers[key].getBlocks(category, type).catch((err) => {
                console.error(`[Registry] Error loading from provider ${key}:`, err);
                return [];
            });
        });

        const results = await Promise.all(promises);

        // Map results back to providers for priority handling
        const blocksByProvider = {};
        providerKeys.forEach((key, index) => {
            blocksByProvider[key] = results[index];
        });

        // Merge based on priority
        const merged = this.mergeByPriority(blocksByProvider);

        // Store in cache
        this.cache[cacheKey] = merged;
        this.loadedCategories.add(cacheKey);

        return merged;
    }

    /**
     * Merge blocks from providers based on priority logic
     * Theme > Database > Built-in
     *
     * @param {Object} blocksByProvider - { builtin: [], theme: [] }
     */
    mergeByPriority(blocksByProvider) {
        const blockMap = new Map();

        // Sort providers by priority (ascending) so higher priority overwrites lower
        const sortedProviders = Object.values(this.providers).sort((a, b) => a.priority - b.priority);

        for (const provider of sortedProviders) {
            const blocks = blocksByProvider[provider.name] || [];

            blocks.forEach((block) => {
                // Determine slug key (some blocks might use different ID fields, normalize here)
                // Assuming block.slug or block.id exists
                const slug = block.slug || block.id;

                if (slug) {
                    blockMap.set(slug, {
                        ...block,
                        _source: provider.name, // Tag with source
                    });
                }
            });
        }

        return Array.from(blockMap.values());
    }

    /**
     * Get HTML content for a block (lazy load support)
     * @param {Object} block
     */
    async getBlockHtml(block) {
        // If html is a function, await it
        if (typeof block.html === 'function') {
            return await block.html();
        }

        // If html exists, return it directly
        if (block.html) {
            return block.html;
        }

        // Lazy load: fetch HTML from provider if not present
        // This supports metadata-only manifests
        const source = block._source || block.source;
        const provider = this.providers[source];

        if (provider && typeof provider.fetchBlockHtml === 'function') {
            try {
                // Use block.type if available, otherwise default to 'block'
                const type = block.type || 'block';
                const result = await provider.fetchBlockHtml(block.slug, type);
                if (result && result.html) {
                    block.html = result.html; // Cache on the block object
                    return result.html;
                }
            } catch (e) {
                console.error(`[Registry] Failed to fetch HTML for ${block.slug}:`, e);
            }
        }

        return block.html || '';
    }
}

// Attach to Global Astero Object
Astero.BlockProvider = BlockProvider;
Astero.Registry = new Registry();
