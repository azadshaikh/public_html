import { useHttp } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { showAppToast } from '@/components/forms/form-success-toast';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useDirtyFormGuard } from '@/hooks/use-dirty-form-guard';
import { cn } from '@/lib/utils';
import { ThemeCustomizerDialogs } from '../../../../components/theme-customizer/customizer-dialogs';
import { ThemeCustomizerHeader } from '../../../../components/theme-customizer/customizer-header';
import { ThemeCustomizerPreviewPanel } from '../../../../components/theme-customizer/customizer-preview-panel';
import { ThemeCustomizerSidebar } from '../../../../components/theme-customizer/customizer-sidebar';
import {
    buildPreviewUrl,
    decodeCurrentPreviewLocation,
    normalizeFieldValue,
    toFormData,
} from '../../../../components/theme-customizer/customizer-utils';
import ThemeCustomizerLayout from '../../../../components/theme-customizer/theme-customizer-layout';
import type {
    CodeEditorState,
    DeviceMode,
    ThemeCustomizerPageProps,
    ThemeCustomizerSnapshot,
} from './types';

export default function ThemeCustomizerIndex({
    activeTheme,
    sections,
    initialValues,
    previewUrl,
    pickerMedia,
    pickerFilters,
    uploadSettings,
    pickerStatistics = null,
}: ThemeCustomizerPageProps) {
    const normalizedInitialValues = useMemo(
        () =>
            Object.fromEntries(
                Object.entries(initialValues).map(([key, value]) => [
                    key,
                    normalizeFieldValue(value),
                ]),
            ) as ThemeCustomizerSnapshot,
        [initialValues],
    );
    const defaultValues = useMemo(
        () =>
            Object.fromEntries(
                Object.entries(sections).flatMap(([, section]) =>
                    Object.entries(section.settings ?? {}).map(([fieldId, field]) => [
                        fieldId,
                        normalizeFieldValue(field.default),
                    ]),
                ),
            ) as ThemeCustomizerSnapshot,
        [sections],
    );
    const [values, setValues] = useState<ThemeCustomizerSnapshot>(normalizedInitialValues);
    const [deviceMode, setDeviceMode] = useState<DeviceMode>('desktop');
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
    const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [isRefreshingPreview, setIsRefreshingPreview] = useState(false);
    const [resetDialogOpen, setResetDialogOpen] = useState(false);
    const [importDialogOpen, setImportDialogOpen] = useState(false);
    const [importFile, setImportFile] = useState<File | null>(null);
    const [previewCss, setPreviewCss] = useState('');
    const [activeCodeEditor, setActiveCodeEditor] = useState<CodeEditorState | null>(null);
    const [iframeBaseUrl, setIframeBaseUrl] = useState(previewUrl);
    const [iframeReloadToken, setIframeReloadToken] = useState<string>('initial');
    const iframeRef = useRef<HTMLIFrameElement | null>(null);
    const latestPreviewCssRef = useRef('');
    const initialSerialized = useRef(JSON.stringify(normalizedInitialValues));
    const saveRequest = useHttp<
        ThemeCustomizerSnapshot,
        { success?: boolean; message?: string; error?: string }
    >(normalizedInitialValues);
    const resetRequest = useHttp<
        Record<string, never>,
        { success?: boolean; message?: string; error?: string }
    >({});
    const exportRequest = useHttp<
        Record<string, never>,
        {
            success?: boolean;
            filename?: string;
            data?: string;
            message?: string;
            error?: string;
        }
    >({});
    const importRequest = useHttp<
        { settings_file: File | null },
        { success?: boolean; message?: string; error?: string }
    >({ settings_file: null });

    const pickerAction = useMemo(() => {
        const action = route('cms.appearance.themes.customizer.index');

        return `${action}?preview_url=${encodeURIComponent(previewUrl)}`;
    }, [previewUrl]);

    const dirtyGuard = useDirtyFormGuard({
        enabled: JSON.stringify(values) !== initialSerialized.current,
    });

    const defaultOpenSections = useMemo(
        () => Object.keys(sections).slice(0, 2),
        [sections],
    );
    const fieldDefinitions = useMemo(
        () =>
            new Map(
                Object.values(sections).flatMap((section) =>
                    Object.entries(section.settings ?? {}),
                ),
            ),
        [sections],
    );

    const iframeSource = useMemo(
        () => buildPreviewUrl(iframeBaseUrl, iframeReloadToken),
        [iframeBaseUrl, iframeReloadToken],
    );

    const setFieldValue = useCallback(
        (fieldId: string, value: string | number | boolean) => {
            setValues((current) => ({
                ...current,
                [fieldId]: value,
            }));
        },
        [],
    );

    const applySnapshot = useCallback(
        (snapshot: ThemeCustomizerSnapshot) => {
            setValues(snapshot);
            initialSerialized.current = JSON.stringify(snapshot);
        },
        [],
    );

    const injectPreviewCss = useCallback((css: string) => {
        const iframe = iframeRef.current;
        if (!iframe) {
            return;
        }

        try {
            const iframeDocument =
                iframe.contentDocument ?? iframe.contentWindow?.document;

            if (!iframeDocument?.head) {
                return;
            }

            const existingStyle = iframeDocument.getElementById(
                'react-theme-customizer-preview-css',
            );

            if (existingStyle) {
                existingStyle.textContent = css;

                return;
            }

            const style = iframeDocument.createElement('style');
            style.id = 'react-theme-customizer-preview-css';
            style.textContent = css;
            iframeDocument.head.appendChild(style);
        } catch {
            // Ignore cross-origin access failures.
        }
    }, []);

    const installPreviewInterceptors = useCallback(() => {
        const iframe = iframeRef.current;
        if (!iframe) {
            return;
        }

        try {
            const iframeDocument =
                iframe.contentDocument ?? iframe.contentWindow?.document;
            const iframeWindow = iframe.contentWindow;

            if (!iframeDocument || !iframeWindow) {
                return;
            }

            const appendPreviewParam = (urlValue: string): string => {
                const parsedUrl = new URL(urlValue, window.location.origin);

                if (parsedUrl.hostname === window.location.hostname) {
                    parsedUrl.searchParams.set('customizer_preview', '1');
                }

                return parsedUrl.toString();
            };

            if (!(iframeDocument as Document & { __themeCustomizerPreviewBound?: boolean }).__themeCustomizerPreviewBound) {
                iframeDocument.addEventListener('click', (event) => {
                    const target = (event.target as HTMLElement | null)?.closest('a');

                    if (target?.href) {
                        target.href = appendPreviewParam(target.href);
                    }
                });

                iframeDocument.addEventListener('submit', (event) => {
                    const form = event.target as HTMLFormElement | null;

                    if (form?.action) {
                        form.action = appendPreviewParam(form.action);
                    }
                });

                (iframeDocument as Document & { __themeCustomizerPreviewBound?: boolean }).__themeCustomizerPreviewBound = true;
            }

            setIframeBaseUrl(decodeCurrentPreviewLocation(iframeWindow.location.href));
        } catch {
            // Ignore cross-origin access failures.
        }
    }, []);

    useEffect(() => {
        latestPreviewCssRef.current = previewCss;
        injectPreviewCss(previewCss);
    }, [injectPreviewCss, previewCss]);

    useEffect(() => {
        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            try {
                const response = await fetch(
                    route('cms.appearance.themes.customizer.preview-css'),
                    {
                        method: 'POST',
                        body: toFormData(values),
                        headers: {
                            Accept: 'text/css,*/*;q=0.1',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        signal: controller.signal,
                    },
                );

                if (!response.ok) {
                    throw new Error('Preview update failed.');
                }

                const css = await response.text();
                setPreviewCss(css);
            } catch (error) {
                if ((error as DOMException).name === 'AbortError') {
                    return;
                }
            }
        }, 450);

        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [values]);

    const handlePreviewLoad = useCallback(() => {
        injectPreviewCss(latestPreviewCssRef.current);
        installPreviewInterceptors();
        setIsRefreshingPreview(false);
    }, [injectPreviewCss, installPreviewInterceptors]);

    const refreshPreview = useCallback(() => {
        setIsRefreshingPreview(true);
        setIframeReloadToken(String(Date.now()));
    }, []);

    const handleSave = useCallback(async () => {
        setIsSaving(true);

        try {
            saveRequest.transform(() => ({
                ...values,
            }));

            const payload = await saveRequest.post(route('cms.appearance.themes.customizer.update'), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (payload.success === false) {
                throw new Error(payload.error || payload.message || 'Save failed.');
            }

            initialSerialized.current = JSON.stringify(values);
            showAppToast({
                variant: 'success',
                title: 'Customizer saved',
                description:
                    payload.message ||
                    'Theme settings were saved and the preview was refreshed.',
            });
            refreshPreview();
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Save failed',
                description:
                    error instanceof Error
                        ? error.message
                        : 'Unable to save theme settings.',
            });
        } finally {
            setIsSaving(false);
        }
    }, [refreshPreview, saveRequest, values]);

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                event.preventDefault();
                void handleSave();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => {
            window.removeEventListener('keydown', onKeyDown);
        };
    }, [handleSave]);

    const handleReset = useCallback(async () => {
        try {
            const payload = await resetRequest.post(route('cms.appearance.themes.customizer.reset'), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (payload.success === false) {
                throw new Error(payload.error || payload.message || 'Reset failed.');
            }

            showAppToast({
                variant: 'success',
                title: 'Customizer reset',
                description: payload.message || 'Theme settings were reset to defaults.',
            });
            applySnapshot(defaultValues);
            refreshPreview();
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Reset failed',
                description:
                    error instanceof Error
                        ? error.message
                        : 'Unable to reset theme settings.',
            });
        }
    }, [applySnapshot, defaultValues, refreshPreview, resetRequest]);

    const handleExport = useCallback(async () => {
        try {
            const payload = await exportRequest.get(route('cms.appearance.themes.customizer.export'), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (payload.success === false || !payload.filename || payload.data === undefined) {
                throw new Error(payload.error || payload.message || 'Export failed.');
            }

            const blob = new Blob([payload.data], { type: 'application/json' });
            const downloadUrl = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = downloadUrl;
            anchor.download = payload.filename;
            document.body.appendChild(anchor);
            anchor.click();
            document.body.removeChild(anchor);
            URL.revokeObjectURL(downloadUrl);

            showAppToast({
                variant: 'success',
                title: 'Customizer exported',
                description: 'Theme settings were downloaded as JSON.',
            });
        } catch (error) {
            showAppToast({
                variant: 'error',
                title: 'Export failed',
                description:
                    error instanceof Error
                        ? error.message
                        : 'Unable to export theme settings.',
            });
        }
    }, [exportRequest]);

    const handleImportSubmit = useCallback(
        async (event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();

            if (!importFile) {
                showAppToast({
                    variant: 'error',
                    title: 'Import failed',
                    description: 'Choose a JSON file before importing.',
                });

                return;
            }

            const formData = new FormData();
            formData.append('settings_file', importFile);

            try {
                const importedText = await importFile.text();
                const importedJson = JSON.parse(importedText) as Record<
                    string,
                    string | number | boolean | null
                >;
                const importedSnapshot = Object.fromEntries(
                    Object.entries(importedJson).map(([key, value]) => {
                        const field = fieldDefinitions.get(key);

                        if (field?.type === 'code_editor' && typeof value === 'string') {
                            try {
                                return [key, atob(value)];
                            } catch {
                                return [key, value];
                            }
                        }

                        return [key, normalizeFieldValue(value)];
                    }),
                ) as ThemeCustomizerSnapshot;

                importRequest.transform(() => ({
                    settings_file: importFile,
                }));

                const payload = await importRequest.post(route('cms.appearance.themes.customizer.import'), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                if (payload.success === false) {
                    throw new Error(payload.error || payload.message || 'Import failed.');
                }

                setImportDialogOpen(false);
                setImportFile(null);
                applySnapshot({
                    ...defaultValues,
                    ...importedSnapshot,
                });
                showAppToast({
                    variant: 'success',
                    title: 'Customizer imported',
                    description: payload.message || 'Theme settings were imported successfully.',
                });
                refreshPreview();
            } catch (error) {
                showAppToast({
                    variant: 'error',
                    title: 'Import failed',
                    description:
                        error instanceof Error
                            ? error.message
                            : 'Unable to import theme settings.',
                });
            }
        },
        [
            applySnapshot,
            defaultValues,
            fieldDefinitions,
            importFile,
            importRequest,
            refreshPreview,
        ],
    );

    const openCodeEditor = useCallback(
        (fieldId: string, field: { label: string; language?: string }) => {
            setActiveCodeEditor({
                fieldId,
                label: field.label,
                language: field.language ?? 'plaintext',
                value: String(values[fieldId] ?? ''),
            });
        },
        [values],
    );

    return (
        <ThemeCustomizerLayout
            title="Theme Customizer"
            description="Adjust theme settings with a live frontend preview."
        >
            {dirtyGuard.dialog}

            <div className="flex min-h-0 flex-1 flex-col">
                <ThemeCustomizerHeader
                    activeThemeName={activeTheme.name}
                    deviceMode={deviceMode}
                    sidebarCollapsed={sidebarCollapsed}
                    isRefreshingPreview={isRefreshingPreview}
                    isSaving={isSaving}
                    onBackHref={route('cms.appearance.themes.index')}
                    onToggleSidebar={() => setSidebarCollapsed((value) => !value)}
                    onDeviceModeChange={setDeviceMode}
                    onRefreshPreview={refreshPreview}
                    onOpenMobileSidebar={() => setMobileSidebarOpen(true)}
                    onExport={handleExport}
                    onOpenImport={() => setImportDialogOpen(true)}
                    onOpenReset={() => setResetDialogOpen(true)}
                    onSave={() => void handleSave()}
                />

                <div className="grid min-h-0 flex-1 grid-cols-1 lg:grid-cols-[minmax(0,var(--customizer-sidebar-width))_minmax(0,1fr)]" style={{ ['--customizer-sidebar-width' as string]: sidebarCollapsed ? '0px' : '320px' }}>
                    <aside className={cn('hidden min-h-0 border-r border-border/70 bg-white/75 backdrop-blur lg:block', sidebarCollapsed && 'overflow-hidden border-r-0')}>
                        {!sidebarCollapsed ? (
                            <ThemeCustomizerSidebar
                                activeTheme={activeTheme}
                                sections={sections}
                                values={values}
                                defaultOpenSections={defaultOpenSections}
                                pickerMedia={pickerMedia}
                                pickerFilters={pickerFilters}
                                uploadSettings={uploadSettings}
                                pickerStatistics={pickerStatistics}
                                pickerAction={pickerAction}
                                onFieldChange={setFieldValue}
                                onOpenCodeEditor={openCodeEditor}
                            />
                        ) : null}
                    </aside>

                    <div
                        className={cn(
                            'min-h-0 overflow-hidden bg-[#f3f5f8]',
                            deviceMode === 'desktop' ? 'p-0' : 'p-2 sm:p-3 lg:p-4',
                        )}
                    >
                        <ThemeCustomizerPreviewPanel
                            deviceMode={deviceMode}
                            iframeRef={iframeRef}
                            iframeSource={iframeSource}
                            onLoad={handlePreviewLoad}
                        />
                    </div>
                </div>
            </div>

            <Sheet open={mobileSidebarOpen} onOpenChange={setMobileSidebarOpen}>
                <SheetContent side="left" className="w-[92vw] max-w-[380px] p-0 sm:max-w-[380px]">
                    <SheetHeader className="border-b border-border/70 pb-4">
                        <SheetTitle>Theme settings</SheetTitle>
                        <SheetDescription>Compact controls for the active theme.</SheetDescription>
                    </SheetHeader>
                    <div className="min-h-0 flex-1">
                        <ThemeCustomizerSidebar
                            activeTheme={activeTheme}
                            sections={sections}
                            values={values}
                            defaultOpenSections={defaultOpenSections}
                            pickerMedia={pickerMedia}
                            pickerFilters={pickerFilters}
                            uploadSettings={uploadSettings}
                            pickerStatistics={pickerStatistics}
                            pickerAction={pickerAction}
                            onFieldChange={setFieldValue}
                            onOpenCodeEditor={openCodeEditor}
                        />
                    </div>
                </SheetContent>
            </Sheet>

            <ThemeCustomizerDialogs
                activeCodeEditor={activeCodeEditor}
                onCodeEditorChange={setActiveCodeEditor}
                onApplyCodeEditor={() => {
                    if (!activeCodeEditor) {
                        return;
                    }

                    setFieldValue(activeCodeEditor.fieldId, activeCodeEditor.value);
                    setActiveCodeEditor(null);
                }}
                importDialogOpen={importDialogOpen}
                onImportDialogOpenChange={setImportDialogOpen}
                onImportSubmit={handleImportSubmit}
                onImportFileChange={setImportFile}
                resetDialogOpen={resetDialogOpen}
                onResetDialogOpenChange={setResetDialogOpen}
                onReset={() => void handleReset()}
            />
        </ThemeCustomizerLayout>
    );
}
