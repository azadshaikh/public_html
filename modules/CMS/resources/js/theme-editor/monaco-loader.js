let monacoLoaded = false;
let monacoLoadPromise = null;

export function loadMonaco() {
    if (monacoLoaded) {
        return Promise.resolve();
    }

    if (monacoLoadPromise) {
        return monacoLoadPromise;
    }

    monacoLoadPromise = new Promise((resolve, reject) => {
        const VS_BASE = 'https://cdn.jsdelivr.net/npm/monaco-editor@0.54.0/min/vs';

        // Check if already loaded
        if (typeof monaco !== 'undefined') {
            monacoLoaded = true;
            resolve();
            return;
        }

        // Ensure workers load correctly when Monaco is served from a CDN
        // (matches the working implementation used elsewhere in the app)
        window.MonacoEnvironment = window.MonacoEnvironment || {};
        window.MonacoEnvironment.getWorkerUrl = function () {
            return (
                'data:text/javascript;charset=utf-8,' +
                encodeURIComponent(`
                self.MonacoEnvironment = { baseUrl: '${VS_BASE}/' };
                importScripts('${VS_BASE}/base/worker/workerMain.js');
            `)
            );
        };

        // Check if loader already exists
        if (typeof window.require !== 'undefined' && window.require.config) {
            window.require.config({ paths: { vs: VS_BASE } });
            window.require(['vs/editor/editor.main'], () => {
                monacoLoaded = true;
                resolve();
            });
            return;
        }

        // Load the Monaco loader script
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/monaco-editor@0.54.0/min/vs/loader.min.js';
        script.onload = () => {
            window.require.config({ paths: { vs: VS_BASE } });
            window.require(['vs/editor/editor.main'], () => {
                monacoLoaded = true;
                resolve();
            });
        };
        script.onerror = reject;
        document.head.appendChild(script);
    });

    return monacoLoadPromise;
}
