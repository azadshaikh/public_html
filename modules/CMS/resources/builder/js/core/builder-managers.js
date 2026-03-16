Astero.StyleManager = {
    styles: {},
    cssContainer: false,
    mobileWidth: '320px',
    tabletWidth: '768px',
    doc: false,
    inlineCSS: false,
    currentElement: null,
    currentSelector: null,
    state: '', //hover, active etc

    init: function (doc) {
        if (doc) {
            this.doc = doc;

            // let style = false;
            // let _style = false;

            // //check if editor style is present
            // for (let i = 0; i < doc.styleSheets.length; i++) {
            //         _style = doc.styleSheets[i];
            //         if (_style.ownerNode.id && _style.ownerNode.id == "pagebuilder-styles") {
            //             style = _style.ownerNode;
            //             break;
            //         }
            // }

            // //if style element does not exist create it
            // if (!style) {
            //     style = generateElements('<div id="pagebuilder-styles"></div>')[0];
            //     doc.head.append(style);
            //     return this.cssContainer = style;
            // }

            // Remove legacy div containers if present
            let styleDivs = doc.querySelectorAll('div[id="pagebuilder-styles"]');
            styleDivs.forEach((div) => div.parentNode.removeChild(div));

            // Look for the style tag
            let style = doc.getElementById('pagebuilder-styles');

            // If style element does not exist, create it as a style tag
            if (!style) {
                style = doc.createElement('style');
                style.id = 'pagebuilder-styles';
                if (doc.head) {
                    doc.head.append(style);
                } else if (doc.body) {
                    doc.body.append(style);
                } else {
                    doc.appendChild(style);
                }
            }

            // If it's a div (legacy), convert to style tag
            if (style.tagName.toLowerCase() === 'div') {
                const newStyle = doc.createElement('style');
                newStyle.id = 'pagebuilder-styles';
                newStyle.textContent = style.textContent || '';
                style.parentNode.replaceChild(newStyle, style);
                style = newStyle;
            }

            //if it exists
            this.cssContainer = style;
            this.loadStyles();

            return this.cssContainer;
        }
    },

    loadStyles: function () {
        // Load css from style#pagebuilder-styles
        this.styles = {};
        let styleEl = this.doc.getElementById('pagebuilder-styles');
        if (styleEl) {
            this.styles['main'] = styleEl.textContent || '';
        }
    },
    getSelectorForElement: function (element) {
        if (!element) return '';

        let currentElement = element;
        let selector = [];

        while (currentElement.parentElement) {
            let elementSelector = '';
            let classSelector = Array.from(currentElement.classList)
                .map(function (className) {
                    if (Astero.Builder.ignoreClasses.indexOf(className) == -1) {
                        return '.' + className;
                    }
                })
                .join('');

            //element (tag) selector
            let tag = currentElement.tagName.toLowerCase();
            //exclude top most element body unless the parent element is body
            if (tag == 'body' && selector.length > 1) {
                break;
            }

            //stop at a unique element (with id)
            if (currentElement.id) {
                elementSelector = '#' + currentElement.id;
                selector.push(elementSelector);
                break;
            } else if (classSelector) {
                //class selector
                elementSelector = classSelector;
            } else {
                //element selector
                elementSelector = tag;
            }

            if (elementSelector) {
                selector.push(elementSelector);
            }

            currentElement = currentElement.parentElement;
        }

        return selector.reverse().join(' > ');
    },

    setState: function (state) {
        this.state = state;
    },

    addSelectorState: function (selector) {
        return selector + (this.state ? ':' + this.state : '');
    },

    setStyle: function (element, styleProp, value) {
        let selector;

        if (typeof element !== 'string') {
            const node = element;

            // Force inline styles for all editor-applied styles.
            if (value === '' || value === null || typeof value === 'undefined') {
                node.style.removeProperty(styleProp);
            } else {
                node.style.setProperty(styleProp, value);
            }
            return element;
        }

        selector = element;

        // Only use CSS styles when element is passed as string selector.
        if (this.inlineCSS && Astero.Builder?.selectedEl) {
            const node = Astero.Builder.selectedEl;
            if (value === '' || value === null || typeof value === 'undefined') {
                node.style.removeProperty(styleProp);
            } else {
                node.style.setProperty(styleProp, value);
            }
            return node;
        }

        selector = this.addSelectorState(selector);

        const media = document.getElementById('canvas').classList.contains('tablet')
            ? 'tablet'
            : document.getElementById('canvas').classList.contains('mobile')
              ? 'mobile'
              : 'desktop';

        //styles[media][selector][styleProp] = value
        if (!this.styles[media]) {
            this.styles[media] = {};
        }
        if (!this.styles[media][selector]) {
            this.styles[media][selector] = {};
        }
        if (!this.styles[media][selector][styleProp]) {
            this.styles[media][selector][styleProp] = {};
        }
        this.styles[media][selector][styleProp] = value;

        this.generateCss(media);

        return element;
        //uncomment bellow code to set css in element's style attribute
        //return element.css(styleProp, value);
    },

    setCss: function (css) {
        // Use textContent for style elements (safer and works properly)
        if (this.cssContainer) {
            this.cssContainer.textContent = css;
        }
        this.loadStyles();
    },

    getCss: function () {
        // Refresh container reference in case DOM changed
        if (this.doc) {
            this.cssContainer = this.doc.getElementById('pagebuilder-styles');
        }
        // Use textContent for style elements
        return this.cssContainer ? this.cssContainer.textContent : '';
    },

    generateCss: function (media) {
        //let css = "";
        //for (selector in this.styles[media]) {

        //	css += `${selector} {`;
        //	for (property in this.styles[media][selector]) {
        //		value = this.styles[media][selector][property];
        //		css += `${property}: ${value};`;
        //	}
        //	css += '}';
        //}

        //this.cssContainer.innerHTML = css;

        //return element;
        //refresh container element to avoid issues with changes from code editor
        this.cssContainer = this.doc.getElementById('pagebuilder-styles');

        let css = '';
        for (const media in this.styles) {
            if (media === 'tablet' || media === 'mobile') {
                css += `@media screen and (max-width: ${media === 'tablet' ? this.tabletWidth : this.mobileWidth}){\n\n`;
            }
            for (const selector in this.styles[media]) {
                css += `${selector} {\n`;
                for (const property in this.styles[media][selector]) {
                    const value = this.styles[media][selector][property];
                    css += `\t${property}: ${value};\n`;
                }
                css += '}\n\n';
            }
            if (media === 'tablet' || media === 'mobile') {
                css += `}\n\n`;
            }
        }

        return (this.cssContainer.textContent = css);
    },

    _getCssStyle: function (element, styleProp) {
        let value = '',
            el,
            selector,
            media;

        el = element;
        if (el != this.currentElement) {
            selector = this.getSelectorForElement(el);
            this.currentElement = el;
            this.currentSelector = selector;
        } else {
            selector = this.currentSelector;
        }

        selector = this.addSelectorState(selector);
        media = document.getElementById('canvas').classList.contains('tablet')
            ? 'tablet'
            : document.getElementById('canvas').classList.contains('mobile')
              ? 'mobile'
              : 'desktop';

        if (el.style && el.style.length > 0 && el.style[styleProp]) {
            //check inline
            value = el.style[styleProp];
        } else if (
            this.styles[media] !== undefined &&
            this.styles[media][selector] !== undefined &&
            this.styles[media][selector][styleProp] !== undefined
        ) {
            //check defined css
            value = this.styles[media][selector][styleProp];

            if (styleProp == 'font-family') {
            }
        } else if (window.getComputedStyle) {
            value = document.defaultView.getDefaultComputedStyle
                ? document.defaultView.getDefaultComputedStyle(el, null).getPropertyValue(styleProp)
                : window.getComputedStyle(el, null).getPropertyValue(styleProp);
        }

        return value;
    },

    getStyle: function (element, styleProp) {
        return this._getCssStyle(element, styleProp);
    },
};

Astero.ScriptManager = {
    scripts: {},
    scriptContainer: false,
    doc: false,
    currentElement: null,
    currentSelector: null,

    init: function (doc) {
        if (doc) {
            this.doc = doc;

            // Look for existing pagebuilder-scripts element
            let script = doc.getElementById('pagebuilder-scripts');
            let existingContent = '';

            // Extract content from existing element
            if (script) {
                existingContent = script.textContent || '';

                // If it's a div (legacy), convert to script tag
                if (script.tagName.toLowerCase() === 'div') {
                    const newScript = doc.createElement('script');
                    newScript.id = 'pagebuilder-scripts';
                    newScript.textContent = existingContent;
                    script.parentNode.replaceChild(newScript, script);
                    script = newScript;
                }
            }

            // If element does not exist, create it as script tag
            if (!script) {
                script = doc.createElement('script');
                script.id = 'pagebuilder-scripts';
                if (doc.body) {
                    doc.body.append(script);
                } else if (doc.head) {
                    doc.head.append(script);
                } else {
                    doc.appendChild(script);
                }
            }

            // Remove any duplicate elements
            const allScriptContainers = doc.querySelectorAll('[id="pagebuilder-scripts"]');
            allScriptContainers.forEach((el) => {
                if (el !== script) {
                    el.parentNode.removeChild(el);
                }
            });

            this.scriptContainer = script;
            this.loadScripts();

            return this.scriptContainer;
        }
    },

    loadScripts: function () {
        // Load JS from pagebuilder-scripts element
        this.scripts = {};
        let scriptsEl = this.doc.getElementById('pagebuilder-scripts');
        if (scriptsEl) {
            this.scripts['main'] = scriptsEl.textContent || '';
        }
    },

    setScript: function (element, scriptContent) {
        // Add inline script to element
        if (element) {
            element.setAttribute('data-astero-script', scriptContent);
            return element;
        }
        // Add to main script container
        if (this.scriptContainer) {
            this.scriptContainer.textContent = scriptContent;
            this.scripts['main'] = scriptContent;
        }
        let scriptsEl = this.doc ? this.doc.getElementById('pagebuilder-scripts') : null;
        if (scriptsEl) {
            return (scriptsEl.textContent = scriptContent);
        }
    },

    getScript: function (element) {
        // Always read from pagebuilder-scripts if available
        let scriptsEl = this.doc ? this.doc.getElementById('pagebuilder-scripts') : null;
        if (scriptsEl) {
            return scriptsEl.textContent || '';
        }
        if (this.scriptContainer) {
            return this.scriptContainer.textContent || '';
        }
        return '';
    },

    removeScript: function (element) {
        if (element) {
            element.removeAttribute('data-astero-script');
        }
    },

    getJs: function (element) {
        return this.getScript(element);
    },

    setJs: function (scriptContent) {
        this.setScript(null, scriptContent);
    },

    // Alias maintained for legacy code editor plugins
    get jsContainer() {
        return this.scriptContainer;
    },
};

Astero.ContentManager = {
    getAttr: function (element, attrName) {
        return element.getAttribute(attrName);
    },

    setAttr: function (element, attrName, value) {
        return element.setAttribute(attrName, value);
    },

    setHtml: function (element, html) {
        return (element.innerHTML = html);
    },

    getHtml: function (element) {
        return element.innerHTML;
    },

    setText: function (element, text) {
        return (element.textContent = text);
    },

    getText: function (element) {
        return element.textContent;
    },
};

Astero.FontsManager = {
    activeFonts: [],
    providers: {}, //{"google":GoogleFontsManager};

    addFontList: function (provider, groupName, fontList) {
        let fonts = {};
        let fontNames = [];

        let fontSelect = generateElements("<optgroup label='" + groupName + "'></optgroup>")[0];
        for (const font in fontList) {
            fontNames.push({ text: font, value: font, 'data-provider': provider });
            let option = new Option(font, font);
            option.dataset.provider = provider;
            //option.style.setProperty("font-family", font);//font preview if the fonts are loaded in editor
            fontSelect.append(option);
        }

        // Legacy WYSIWYG toolbar select (may not exist when using AsteroNote Inline)
        const fontFamilySelect = document.getElementById('font-family');
        if (fontFamilySelect) {
            fontFamilySelect.append(fontSelect);
        }

        let list = Astero.Components.getProperty('_base', 'font-family');
        if (list) {
            list.onChange = function (node, value, input, component) {
                let option = input.options[input.selectedIndex];
                Astero.FontsManager.addFont(option.dataset.provider, value, node);
                return node;
            };

            list.data.options.push({ optgroup: groupName });
            list.data.options = list.data.options.concat(fontNames);

            Astero.Components.updateProperty('_base', 'font-family', { data: list.data });

            //update default font list
            fontList = list.data.options;
        }
    },

    addProvider: function (provider, Obj) {
        this.providers[provider] = Obj;
    },

    //add also element so we can keep track of the used fonts to remove unused ones
    addFont: function (provider, fontFamily, element = false) {
        if (!provider) return;

        let providerObj = this.providers[provider];
        if (providerObj) {
            providerObj.addFont(fontFamily);
            this.activeFonts.push({ provider, fontFamily, element });
        }
    },

    removeFont: function (provider, fontFamily) {
        if (!provider) return;

        let providerObj = this.providers[provider];
        if (provider != 'default' && providerObj) {
            providerObj.removeFont(fontFamily);
        }
    },

    //check if the added fonts are still used for the elements they were set and remove unused ones
    cleanUnusedFonts: function () {
        for (const i in this.activeFonts) {
            let elementFont = this.activeFonts[i];
            if (elementFont.element) {
                if (
                    Astero.StyleManager.getStyle(elementFont.element, 'font-family').replaceAll('"', '') !=
                    elementFont.fontFamily
                ) {
                    this.removeFont(elementFont.provider, elementFont.fontFamily);
                }
            }
        }
    },
};

Astero.ColorPalette = {
    colors: {},

    getAll: function () {
        return this.colors;
    },

    add: function (name, color) {
        this.colors[name] = color;
    },

    remove: function (color) {
        delete this.colors[color];
    },
};

function friendlyName(name) {
    name = name.replaceAll('--bs-', '').replace(/[-_]/g, ' ').trim();
    return (name = name[0].toUpperCase() + name.slice(1));
}

// Expose friendlyName globally for other modules
window.friendlyName = friendlyName;

Astero.ColorPaletteManager = {
    // Variables feature disabled - ColorPaletteManager functionality removed
    init: function (document) {
        // ColorPaletteManager initialization removed - variables feature disabled
    },
};

Astero.Config = {
    components: [],
    blocks: [],
    plugins: [],

    load: function (url = 'default.json') {
        if (!window.fetch) return;
        fetch(url)
            .then((response) => response.json())
            .then((data) => data)
            .catch(() => {});
    },
};

// Expose fontList globally for other modules
window.fontList = [
    // Also available as `let fontList` for local use
];
let fontList = (window.fontList = [
    {
        value: '',
        text: 'Default',
    },
    {
        value: 'Arial, Helvetica, sans-serif',
        text: 'Arial',
    },
    {
        value: "'Lucida Sans Unicode', 'Lucida Grande', sans-serif",
        text: 'Lucida Grande',
    },
    {
        value: "'Palatino Linotype', 'Book Antiqua', Palatino, serif",
        text: 'Palatino Linotype',
    },
    {
        value: "'Times New Roman', Times, serif",
        text: 'Times New Roman',
    },
    {
        value: 'Georgia, serif',
        text: 'Georgia, serif',
    },
    {
        value: 'Tahoma, Geneva, sans-serif',
        text: 'Tahoma',
    },
    {
        value: "'Comic Sans MS', cursive, sans-serif",
        text: 'Comic Sans',
    },
    {
        value: 'Verdana, Geneva, sans-serif',
        text: 'Verdana',
    },
    {
        value: 'Impact, Charcoal, sans-serif',
        text: 'Impact',
    },
    {
        value: "'Arial Black', Gadget, sans-serif",
        text: 'Arial Black',
    },
    {
        value: "'Trebuchet MS', Helvetica, sans-serif",
        text: 'Trebuchet',
    },
    {
        value: "'Courier New', Courier, monospace",
        text: 'Courier New',
    },
    {
        value: "'Brush Script MT', sans-serif",
        text: 'Brush Script',
    },
]);
