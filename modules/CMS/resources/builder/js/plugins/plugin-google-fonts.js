window.GoogleFontsManager = {
    url: 'https://fonts.googleapis.com/css2?display=swap&family=',
    activeFonts: [],

    updateFontList: function () {
        const root = typeof globalThis !== 'undefined' ? globalThis : window;
        const Astero = root.Astero;
        if (!Astero?.Builder?.frameHead) return;

        let googleFontsLink =
            Astero.Builder.frameHead.querySelector('#google-fonts-link');

        if (this.activeFonts.length == 0) {
            if (googleFontsLink) googleFontsLink.remove();
            return;
        }

        if (!googleFontsLink) {
            googleFontsLink = generateElements(
                `<link id="google-fonts-link" href="" rel="stylesheet">`,
            )[0];
            Astero.Builder.frameHead.append(googleFontsLink);
        }

        googleFontsLink.setAttribute(
            'href',
            this.url + this.activeFonts.join('&family='),
        );
    },

    removeFont: function (fontName) {
        let index = this.activeFonts.lastIndexOf(fontName);
        if (index !== -1) {
            this.activeFonts.splice(index, 1);
        }
        this.updateFontList();
    },

    addFont: function (fontName) {
        this.activeFonts.push(fontName);
        this.updateFontList();
    },
};

(function () {
    const root = typeof globalThis !== 'undefined' ? globalThis : window;
    const Astero = root.Astero;
    if (!Astero?.FontsManager?.addProvider) return;

    Astero.FontsManager.addProvider('google', GoogleFontsManager);
})();

let googleFonts = {};
let googlefontNames = [];
//load google fonts list and update wyswyg font selector and style tab font-family list
(function () {
    const root = typeof globalThis !== 'undefined' ? globalThis : window;
    const Astero = root.Astero;
    if (!Astero?.FontsManager?.addFontList) return;

    const googleFontsUrl = new URL(
        '/resources/google-fonts.json',
        window.location.origin,
    ).toString();

    fetch(googleFontsUrl)
        .then((response) => {
            if (!response.ok) {
                throw new Error(response);
            }
            return response.json();
        })
        .then((data) => {
            Astero.FontsManager.addFontList('google', 'Google Fonts', data);
        })
        .catch((error) => {
            console.warn(
                '[GoogleFonts] Failed to load google-fonts.json',
                error,
            );
            if (typeof window.displayToast === 'function') {
                window.displayToast(
                    'bg-danger',
                    'Error',
                    'Error loading google fonts!',
                );
            } else if (Astero?.Gui?.notify) {
                Astero.Gui.notify('Error loading google fonts!', 'error');
            }
        });
})();
