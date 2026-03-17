/**
 * Astero Builder - HTML Operations
 *
 * Handles HTML manipulation, saving, and AJAX operations:
 * - getHtml / setHtml
 * - removeHelpers
 * - saveElement (session storage)
 * - saveAjax
 */

// Extend Astero.Builder with HTML operations
Object.assign(Astero.Builder, {
    /**
     * Remove helper attributes and tags from HTML
     */
    removeHelpers: function (html, keepHelperAttributes) {
        if (keepHelperAttributes === undefined) {
            keepHelperAttributes = false;
        }
        // Remove entire tags that have the data-astero-helpers attribute
        html = html.replace(/<[^>]+\s+data-astero-helpers[^>]*>/gi, '');

        // Remove helper attributes
        if (!keepHelperAttributes) {
            html = html.replace(
                /\s*data-astero-\w+(=["'][^"']*["'])?\s*/gi,
                '',
            );
        }

        html = html.replaceAll('astero-hidden', '');
        return html;
    },

    /**
     * Get the complete HTML of the edited document
     */
    getHtml: function (keepHelperAttributes = true) {
        let doc = window.FrameDocument;
        let hasDoctpe = doc.doctype !== null;
        let html = '';

        // Clean up editing artifacts
        doc.querySelectorAll('[contenteditable]').forEach((e) =>
            e.removeAttribute('contenteditable'),
        );
        doc.querySelectorAll('[spellcheck="false"]').forEach((e) =>
            e.removeAttribute('spellcheck'),
        );
        doc.querySelectorAll('[spellchecker]').forEach((e) =>
            e.removeAttribute('spellchecker'),
        );
        doc.querySelectorAll('script[src^="chrome-extension://"]').forEach(
            (e) => e.remove(),
        );
        doc.querySelectorAll('script[src^="moz-extension://"]').forEach((e) =>
            e.remove(),
        );

        window.dispatchEvent(
            new CustomEvent('astero.getHtml.before', { detail: doc }),
        );

        // Build DOCTYPE if present
        if (hasDoctpe) {
            html =
                '<!DOCTYPE ' +
                doc.doctype.name +
                (doc.doctype.publicId
                    ? ' PUBLIC "' + doc.doctype.publicId + '"'
                    : '') +
                (!doc.doctype.publicId && doc.doctype.systemId
                    ? ' SYSTEM'
                    : '') +
                (doc.doctype.systemId
                    ? ' "' + doc.doctype.systemId + '"'
                    : '') +
                '>\n';
        }

        Astero.FontsManager.cleanUnusedFonts();

        html += doc.documentElement.outerHTML;
        html = this.removeHelpers(html, keepHelperAttributes);

        window.dispatchEvent(
            new CustomEvent('astero.getHtml.after', { detail: doc }),
        );
        window.dispatchEvent(
            new CustomEvent('astero.getHtml.filter', { detail: html }),
        );

        return html;
    },

    /**
     * Set HTML content in the iframe
     */
    setHtml: function (html) {
        console.log('Setting HTML content in the iframe');

        function getTag(html, tag, outerHtml = false) {
            const start = html.indexOf('<' + tag);
            const end = html.indexOf('</' + tag);

            if (start >= 0 && end >= 0) {
                if (outerHtml) return html.slice(start, end + 3 + tag.length);
                else return html.slice(html.indexOf('>', start) + 1, end);
            } else {
                return html;
            }
        }

        if (this.runJsOnSetHtml) {
            this.frameBody.innerHTML = getTag(html, 'body');
        } else {
            window.FrameDocument.body.innerHTML = getTag(html, 'body');
        }

        // Set head HTML only if changed to avoid page flicker
        let headHtml = getTag(html, 'head');
        if (window.FrameDocument.head.innerHTML != headHtml) {
            window.FrameDocument.head.innerHTML = headHtml;
        }
    },

    /**
     * Save element as reusable section/block (session only)
     * Note: Persistent storage would require backend API implementation
     */
    saveElement: function (element, type, name, callback) {
        if (type === 'section') {
            Astero.Sections.add('reusable/' + name, {
                name,
                image: 'img/logo-small.png',
                html: element.outerHTML,
            });

            if (!Astero.SectionsGroup['Reusable']) {
                Astero.SectionsGroup['Reusable'] = [];
            }
            Astero.SectionsGroup['Reusable'].push('reusable/' + name);
            Astero.Builder.loadSectionGroups();
        } else {
            Astero.Blocks.add('reusable/' + name, {
                name,
                image: 'img/logo-small.png',
                html: element.outerHTML,
            });

            if (!Astero.BlocksGroup['Reusable']) {
                Astero.BlocksGroup['Reusable'] = [];
            }
            Astero.BlocksGroup['Reusable'].push('reusable/' + name);
            Astero.Builder.loadBlockGroups();
        }

        displayToast(
            'bg-success',
            'Save',
            `${type} "${name}" saved to session`,
        );
        if (callback) callback({ success: true });
    },
});
