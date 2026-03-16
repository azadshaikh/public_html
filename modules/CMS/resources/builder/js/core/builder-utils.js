/**
 * Astero Builder - Utility Functions
 *
 * Core utility functions used throughout the builder:
 * - Template rendering (tmpl)
 * - DOM element creation (generateElements)
 * - Element positioning (offset)
 * - Type checking (isElement)
 * - Form serialization (nestedFormData, buildParams)
 * - Debouncing (delay)
 */

// Ensure the builder global namespace exists early.
const __ASTERO_GLOBAL__ = typeof globalThis !== 'undefined' ? globalThis : window;
__ASTERO_GLOBAL__.Astero = __ASTERO_GLOBAL__.Astero || {};

// =============================================
// TEMPLATE ENGINE
// Simple JavaScript Templating - John Resig
// https://johnresig.com/ - MIT Licensed
// =============================================
(function (global) {
    let cache = {};
    let startTag = '{%';
    let endTag = '%}';
    let re1 = new RegExp(`((^|${endTag})[^\t]*)'`, 'g');
    let re2 = new RegExp(`\t=(.*?)${endTag}`, 'g');

    global.tmpl = function tmpl(str, data) {
        let fn = /^[-a-zA-Z0-9]+$/.test(str)
            ? (cache[str] = cache[str] || tmpl(document.getElementById(str).innerHTML))
            : new Function(
                  'obj',
                  'let p=[],print=function(){p.push.apply(p,arguments);};' +
                      "with(obj){p.push('" +
                      str
                          .replace(/[\r\t\n]/g, ' ')
                          .split(startTag)
                          .join('\t')
                          .replace(re1, '$1\r')
                          .replace(re2, "',$1,'")
                          .split('\t')
                          .join("');")
                          .split(endTag)
                          .join("p.push('")
                          .split('\r')
                          .join("\\'") +
                      "');}return p.join('');"
              );
        return data ? fn(data) : fn;
    };
})(typeof globalThis !== 'undefined' ? globalThis : window);

// =============================================
// FORM SERIALIZATION
// =============================================

function buildParams(prefix, obj, add) {
    let rbracket = /\[\]$/;

    if (Array.isArray(obj)) {
        for (const key in obj) {
            let v = obj[key];
            if (rbracket.test(prefix)) {
                add(prefix, v);
            } else {
                buildParams(prefix + '[' + (typeof v === 'object' && v != null ? key : '') + ']', v, add);
            }
        }
    } else if (typeof obj === 'object') {
        for (const name in obj) {
            buildParams(prefix + '[' + name + ']', obj[name], add);
        }
    } else {
        add(prefix, obj);
    }
}

function nestedFormData(a) {
    let prefix,
        s = [],
        add = function (key, valueOrFunction) {
            let value = typeof valueOrFunction === 'function' ? valueOrFunction() : valueOrFunction;
            s[s.length] = encodeURIComponent(key) + '=' + encodeURIComponent(value == null ? '' : value);
        };

    if (a == null) {
        return '';
    }

    if (Array.isArray(a) || Object.is(a)) {
        for (const key in a) {
            let v = a[key];
            add(key, v);
        }
    } else {
        for (const prefix in a) {
            buildParams(prefix, a[prefix], add);
        }
    }

    return s.join('&');
}

// =============================================
// DOM UTILITIES
// =============================================

function isElement(obj) {
    return (
        typeof obj === 'object' &&
        obj.nodeType === 1 &&
        typeof obj.style === 'object' &&
        typeof obj.ownerDocument === 'object'
    );
}

function generateElements(html) {
    const template = document.createElement('template');
    template.innerHTML = html.trim();
    return template.content.children;
}

function offset(el) {
    if (!el) return { top: 0, left: 0 };
    const box = el.getBoundingClientRect();
    const doc = el.ownerDocument;
    const win = doc.defaultView || window;
    const docElem = doc.documentElement;
    return {
        top: box.top + win.pageYOffset - docElem.clientTop,
        left: box.left + win.pageXOffset - docElem.clientLeft,
    };
}

// =============================================
// TIMING UTILITIES
// =============================================

let delay = (function () {
    let timers = new Map();
    return function (callback, ms, key) {
        const timerKey = key || callback.toString();
        if (timers.has(timerKey)) clearTimeout(timers.get(timerKey));
        timers.set(
            timerKey,
            setTimeout(() => {
                timers.delete(timerKey);
                callback();
            }, ms)
        );
    };
})();

// =============================================
// CONFIRMATION DIALOG
// =============================================

/**
 * Show a confirmation dialog before destructive actions
 * @param {Object} options - Configuration options
 * @param {string} options.title - Dialog title
 * @param {string} options.message - Dialog message
 * @param {string} options.confirmText - Confirm button text
 * @param {string} options.cancelText - Cancel button text
 * @param {boolean} options.dangerous - If true, styles confirm button as danger
 * @returns {Promise<boolean>} - Resolves true if confirmed, false if cancelled
 */
function confirmAction(options = {}) {
    const {
        title = 'Are you sure?',
        message = 'This action cannot be undone.',
        confirmText = 'Yes, Delete',
        cancelText = 'Cancel',
        dangerous = true,
    } = options;

    return new Promise((resolve) => {
        // Remove any existing modal
        const existingModal = document.getElementById('builder-confirm-modal');
        if (existingModal) {
            existingModal.remove();
        }

        const confirmBtnClass = dangerous ? 'btn-danger' : 'btn-primary';
        const iconClass = dangerous ? 'ri-error-warning-line text-danger' : 'ri-question-line text-warning';

        const modalHtml = `
            <div class="modal fade" id="builder-confirm-modal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content">
                        <div class="modal-header border-0 pb-0">
                            <h6 class="modal-title d-flex align-items-center gap-2">
                                <i class="${iconClass}" style="font-size: 1.25rem;"></i>
                                ${title}
                            </h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body py-2">
                            <p class="mb-0 text-muted small">${message}</p>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">${cancelText}</button>
                            <button type="button" class="btn btn-sm ${confirmBtnClass}" data-confirm-action>${confirmText}</button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        const modalEl = document.getElementById('builder-confirm-modal');
        const modal = new bootstrap.Modal(modalEl);

        let confirmed = false;

        // Confirm button
        modalEl.querySelector('[data-confirm-action]').addEventListener('click', () => {
            confirmed = true;
            modal.hide();
        });

        // On hidden, call appropriate callback and cleanup
        modalEl.addEventListener(
            'hidden.bs.modal',
            () => {
                modalEl.remove();
                resolve(confirmed);
            },
            { once: true }
        );

        modal.show();
    });
}

/**
 * Shortcut for delete confirmation
 * @param {string} itemName - Name of item being deleted (e.g., "section", "block", "element")
 * @returns {Promise<boolean>}
 */
function confirmDelete(itemName = 'this element') {
    return confirmAction({
        title: 'Delete ' + itemName.charAt(0).toUpperCase() + itemName.slice(1) + '?',
        message: `Are you sure you want to delete ${itemName}? You can undo this action.`,
        confirmText: 'Delete',
        cancelText: 'Cancel',
        dangerous: true,
    });
}

// =============================================
// EXPOSE GLOBALLY
// All utilities need to be available to other modules
// =============================================
window.tmpl = tmpl;
window.buildParams = buildParams;
window.nestedFormData = nestedFormData;
window.isElement = isElement;
window.generateElements = generateElements;
window.offset = offset;
window.delay = delay;
window.confirmAction = confirmAction;
window.confirmDelete = confirmDelete;
