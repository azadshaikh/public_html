/**
 * Astero Builder - Link Protection System
 *
 * Prevents accidental navigation by disabling links outside enabled areas:
 * - _initLinkProtection
 * - _disableLinksOutsideEnabledAreas
 * - _restoreDisabledLinks
 */

// Extend Astero.Builder with link protection operations
Object.assign(Astero.Builder, {
    /**
     * Initialize link protection - remove href from links outside enabled areas
     */
    _initLinkProtection: function () {
        let self = this;

        // Apply link protection: remove href attributes
        self._disableLinksOutsideEnabledAreas();

        // Re-apply protection when content changes
        window.addEventListener('astero.iframe.loaded', function () {
            setTimeout(() => {
                self._disableLinksOutsideEnabledAreas();
            }, 100);
        });
    },

    /**
     * Remove href attributes from links outside data-astero-enabled areas
     */
    _disableLinksOutsideEnabledAreas: function () {
        try {
            if (!this.frameDoc) {
                console.log('Frame document not ready for link disabling');
                return;
            }

            const allLinks = this.frameDoc.querySelectorAll('a[href]');

            allLinks.forEach((link) => {
                // Check if link is inside a data-astero-enabled area
                const isInsideEnabledArea = link.closest('[data-astero-enabled]');

                if (!isInsideEnabledArea) {
                    // Store original href for potential restore
                    if (!link.dataset.originalHref) {
                        link.dataset.originalHref = link.getAttribute('href');
                    }

                    // Remove href attribute completely - makes link non-functional
                    link.removeAttribute('href');

                    // Mark as disabled for identification
                    link.classList.add('astero-disabled-link');
                }
            });

            console.log(
                `Removed href from ${allLinks.length - this.frameDoc.querySelectorAll('[data-astero-enabled] a').length} links outside enabled areas`
            );
        } catch (error) {
            console.error('Error disabling links:', error);
        }
    },

    /**
     * Restore href attributes to all disabled links
     */
    _restoreDisabledLinks: function () {
        try {
            if (!this.frameDoc) return;

            const disabledLinks = this.frameDoc.querySelectorAll('.astero-disabled-link');

            disabledLinks.forEach((link) => {
                // Restore original href if it exists
                if (link.dataset.originalHref) {
                    link.setAttribute('href', link.dataset.originalHref);
                    delete link.dataset.originalHref;
                }

                // Remove disabled class
                link.classList.remove('astero-disabled-link');
            });

            console.log(`Restored ${disabledLinks.length} disabled links`);
        } catch (error) {
            console.error('Error restoring links:', error);
        }
    },
});
