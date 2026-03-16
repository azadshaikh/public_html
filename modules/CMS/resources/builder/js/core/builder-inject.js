/**
 * Astero Builder - Style/Script Injection
 *
 * Handles injection of component CSS and JS into the iframe:
 * - injectElementStyles
 * - _injectCSS / _injectJS
 * - getInjectedCSS / getInjectedJS
 * - clearInjectedStyles
 */

// Extend Astero.Builder with injection operations
Object.assign(Astero.Builder, {
    /**
     * Inject CSS and JS for dropped elements into pagebuilder-styles and pagebuilder-scripts
     */
    injectElementStyles: function (component) {
        if (!component || !this.frameDoc) return;

        try {
            // Inject CSS if present
            if (component.css && component.css.trim()) {
                let componentcss = component.css.trim();
                let updateComponentcss = componentcss.replace(
                    '<style>',
                    '<style data-componentid="' + component.id + '-style">'
                );
                this._injectCSS(updateComponentcss);
            }

            // Inject JS if present
            if (component.js && component.js.trim()) {
                let componentjs = component.js.trim();
                let updateComponentJs = componentjs.replace(
                    '<script>',
                    '<script data-componentid="' + component.id + '-script">'
                );
                this._injectJS(updateComponentJs);
            }
        } catch (error) {
            console.error('Error injecting element styles:', error);
        }
    },

    /**
     * Inject CSS into pagebuilder-styles element
     */
    _injectCSS: function (css) {
        if (!css || !this.frameDoc) return;

        let stylesElement = this.frameDoc.getElementById('pagebuilder-styles');

        // Create pagebuilder-styles element if it doesn't exist
        if (!stylesElement) {
            stylesElement = this.frameDoc.createElement('div');
            stylesElement.id = 'pagebuilder-styles';
            this.frameHead.appendChild(stylesElement);
        }

        // Extract data-componentid from the style tag
        let match = css.match(/<style[^>]*data-componentid=["']([^"']+)["'][^>]*>/);
        let componentId = match ? match[1] : null;

        if (componentId) {
            // Check if a style block with this data-componentid already exists
            let existingStyle = stylesElement.querySelector('style[data-componentid="' + componentId + '"]');
            if (existingStyle) {
                // Replace the content inside the style tag
                let cssContentMatch = css.match(/<style[^>]*>[\s\S]*?<\/style>/);
                if (cssContentMatch) {
                    // Extract only the CSS rules between <style>...</style>
                    let innerCss = css.replace(/<style[^>]*>|<\/style>/g, '');
                    existingStyle.textContent = innerCss;
                }
                return;
            }
        }

        // Append new CSS style block
        let tempDiv = document.createElement('div');
        tempDiv.innerHTML = css;
        let styleTag = tempDiv.querySelector('style');
        if (styleTag) {
            stylesElement.appendChild(styleTag);
        }
    },

    /**
     * Inject JS into pagebuilder-scripts element
     */
    _injectJS: function (js) {
        if (!js || !this.frameDoc) return;

        let scriptsElement = this.frameDoc.getElementById('pagebuilder-scripts');

        // Create pagebuilder-scripts element if it doesn't exist
        if (!scriptsElement) {
            scriptsElement = this.frameDoc.createElement('div');
            scriptsElement.id = 'pagebuilder-scripts';
            this.frameDoc.head.appendChild(scriptsElement);
        }

        // Check if this JS is already present to avoid duplicates
        const currentJS = scriptsElement.innerHTML;
        if (currentJS.includes(js)) {
            console.log('JS already exists, skipping injection');
            return;
        }

        // Append new JS
        const newJS = currentJS + '\n' + js;
        scriptsElement.innerHTML = newJS;
    },

    /**
     * Get all injected CSS from pagebuilder-styles
     */
    getInjectedCSS: function () {
        if (!this.frameDoc) return '';
        const stylesElement = this.frameDoc.getElementById('pagebuilder-styles');
        return stylesElement ? stylesElement.innerHTML : '';
    },

    /**
     * Get all injected JS from pagebuilder-scripts
     */
    getInjectedJS: function () {
        if (!this.frameDoc) return '';
        const scriptsElement = this.frameDoc.getElementById('pagebuilder-scripts');
        return scriptsElement ? scriptsElement.innerHTML : '';
    },

    /**
     * Clear all injected styles and scripts
     */
    clearInjectedStyles: function () {
        if (!this.frameDoc) return;

        const stylesElement = this.frameDoc.getElementById('pagebuilder-styles');
        if (stylesElement) {
            stylesElement.textContent = '';
        }

        const scriptsElement = this.frameDoc.getElementById('pagebuilder-scripts');
        if (scriptsElement) {
            scriptsElement.textContent = '';
        }

        console.log('Cleared all injected styles and scripts');
    },
});
