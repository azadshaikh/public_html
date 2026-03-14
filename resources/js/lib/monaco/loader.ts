const MONACO_VERSION = '0.54.0';
const MONACO_CDN_BASE = `https://cdn.jsdelivr.net/npm/monaco-editor@${MONACO_VERSION}/min/vs`;
const MONACO_LOADER_SELECTOR = 'script[data-monaco-amd-loader]';

type MonacoSubscription = {
    dispose: () => void;
};

export type MonacoEditorModel = {
    getValue: () => string;
    setValue: (value: string) => void;
};

export type MonacoEditorConstructionOptions = {
    value: string;
    language?: string;
    theme?: string;
    automaticLayout?: boolean;
    readOnly?: boolean;
    minimap?: {
        enabled?: boolean;
    };
    lineNumbers?: 'on' | 'off' | 'relative' | 'interval';
    scrollBeyondLastLine?: boolean;
    wordWrap?: 'off' | 'on' | 'wordWrapColumn' | 'bounded';
    fontSize?: number;
    fontFamily?: string;
    padding?: {
        top?: number;
        bottom?: number;
    };
    renderLineHighlight?: 'all' | 'line' | 'gutter' | 'none';
    scrollbar?: {
        vertical?: 'auto' | 'visible' | 'hidden';
        horizontal?: 'auto' | 'visible' | 'hidden';
    };
    tabSize?: number;
    insertSpaces?: boolean;
    bracketPairColorization?: {
        enabled?: boolean;
    };
    [key: string]: unknown;
};

export type MonacoStandaloneEditor = {
    getValue: () => string;
    setValue: (value: string) => void;
    getModel: () => MonacoEditorModel | null;
    updateOptions: (options: Partial<MonacoEditorConstructionOptions>) => void;
    onDidChangeModelContent: (listener: () => void) => MonacoSubscription;
    layout: () => void;
    focus?: () => void;
    dispose: () => void;
};

export type MonacoThemeDefinition = {
    base: 'vs' | 'vs-dark' | 'hc-black';
    inherit: boolean;
    rules: Array<Record<string, unknown>>;
    colors: Record<string, string>;
};

export type MonacoModule = {
    editor: {
        create: (
            container: HTMLElement,
            options: MonacoEditorConstructionOptions,
        ) => MonacoStandaloneEditor;
        defineTheme: (themeName: string, themeData: MonacoThemeDefinition) => void;
        setTheme: (themeName: string) => void;
        setModelLanguage: (model: MonacoEditorModel, language: string) => void;
    };
};

type AmdRequire = {
    (
        modules: string[],
        onLoad: () => void,
        onError?: (error: unknown) => void,
    ): void;
    config: (config: {
        paths: Record<string, string>;
    }) => void;
};

declare global {
    interface Window {
        monaco?: MonacoModule;
        require?: AmdRequire;
        MonacoEnvironment?: {
            baseUrl?: string;
            getWorkerUrl?: (moduleId: string, label: string) => string;
        };
    }
}

let monacoLoaderPromise: Promise<MonacoModule> | null = null;
const registeredThemes = new Set<string>();

function getWorkerBootstrapUrl(): string {
    return `data:text/javascript;charset=utf-8,${encodeURIComponent(`self.MonacoEnvironment = { baseUrl: '${MONACO_CDN_BASE}/' }; importScripts('${MONACO_CDN_BASE}/base/worker/workerMain.js');`)}`;
}

function configureWorkers(): void {
    window.MonacoEnvironment = {
        getWorkerUrl: () => getWorkerBootstrapUrl(),
    };
}

function appendLoaderScript(): Promise<void> {
    return new Promise((resolve, reject) => {
        const existingScript = document.querySelector<HTMLScriptElement>(
            MONACO_LOADER_SELECTOR,
        );

        if (existingScript) {
            if (typeof window.require?.config === 'function') {
                resolve();

                return;
            }

            existingScript.addEventListener('load', () => resolve(), {
                once: true,
            });
            existingScript.addEventListener(
                'error',
                () => reject(new Error('Failed to load Monaco AMD loader.')),
                { once: true },
            );

            return;
        }

        const script = document.createElement('script');
        script.src = `${MONACO_CDN_BASE}/loader.min.js`;
        script.async = true;
        script.dataset.monacoAmdLoader = 'true';
        script.addEventListener('load', () => resolve(), { once: true });
        script.addEventListener(
            'error',
            () => reject(new Error('Failed to load Monaco AMD loader.')),
            { once: true },
        );
        document.head.appendChild(script);
    });
}

async function ensureAmdLoader(): Promise<AmdRequire> {
    if (typeof window === 'undefined') {
        throw new Error('Monaco can only be loaded in the browser.');
    }

    if (typeof window.require?.config === 'function') {
        return window.require;
    }

    await appendLoaderScript();

    if (typeof window.require?.config !== 'function') {
        throw new Error('Monaco AMD loader did not initialize correctly.');
    }

    return window.require;
}

export async function loadMonacoEditor(): Promise<MonacoModule> {
    if (typeof window === 'undefined') {
        throw new Error('Monaco can only be loaded in the browser.');
    }

    if (window.monaco?.editor) {
        return window.monaco;
    }

    if (monacoLoaderPromise) {
        return monacoLoaderPromise;
    }

    monacoLoaderPromise = (async () => {
        const amdRequire = await ensureAmdLoader();
        configureWorkers();
        amdRequire.config({
            paths: {
                vs: MONACO_CDN_BASE,
            },
        });

        return await new Promise<MonacoModule>((resolve, reject) => {
            amdRequire(
                ['vs/editor/editor.main'],
                () => {
                    if (!window.monaco?.editor) {
                        reject(new Error('Monaco failed to initialize.'));

                        return;
                    }

                    resolve(window.monaco);
                },
                (error) => reject(error),
            );
        });
    })().catch((error) => {
        monacoLoaderPromise = null;
        throw error;
    });

    return monacoLoaderPromise;
}

export function ensureMonacoTheme(
    monaco: MonacoModule,
    themeName: string,
    theme: MonacoThemeDefinition,
): void {
    if (registeredThemes.has(themeName)) {
        return;
    }

    monaco.editor.defineTheme(themeName, theme);
    registeredThemes.add(themeName);
}
