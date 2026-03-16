// Import Coloris from npm package (CSS + JS)
import '@melloware/coloris/dist/coloris.css';
import Coloris from '@melloware/coloris';

const __ASTERO_GLOBAL__ = typeof globalThis !== 'undefined' ? globalThis : window;

const colorisOptions = {
    el: '.coloris',
    theme: 'polaroid',
    formatToggle: true,
};

// Initialize Coloris
Coloris.init();
Coloris(colorisOptions);

// Expose to window for any external usage
window.Coloris = Coloris;

// Enhance the existing global ColorInput (defined in builder-inputs.js)
// without redefining it from scratch.
if (__ASTERO_GLOBAL__.ColorInput) {
    const baseSetValue = __ASTERO_GLOBAL__.ColorInput.setValue?.bind(__ASTERO_GLOBAL__.ColorInput);

    __ASTERO_GLOBAL__.ColorInput = {
        ...__ASTERO_GLOBAL__.ColorInput,
        setValue: function (value) {
            if (baseSetValue) {
                baseSetValue(value);
            }

            // Keep the Coloris button preview in sync.
            const el = this.element?.[0] || this.element;
            const field = el?.closest ? el.closest('.clr-field') : null;
            if (field && value) {
                field.style.color = value;
            }
        },
    };
}
