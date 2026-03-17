import { router } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    Code2Icon,
    DownloadIcon,
    EyeIcon,
    ImagePlusIcon,
    ImportIcon,
    LaptopIcon,
    PanelLeftCloseIcon,
    PanelLeftOpenIcon,
    RefreshCwIcon,
    RotateCcwIcon,
    SaveIcon,
    SmartphoneIcon,
    TabletIcon,
    Trash2Icon,
    UploadIcon,
} from 'lucide-react';
import {
    FormEvent,
    KeyboardEvent as ReactKeyboardEvent,
    useCallback,
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { showAppToast } from '@/components/forms/form-success-toast';
import { MediaPickerDialog } from '@/components/media/media-picker-dialog';
import type { MediaPickerItem } from '@/components/media/media-picker-utils';
import { MonacoEditor } from '@/components/code-editor/monaco-editor';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Accordion,
    AccordionContent,
    AccordionItem,
    AccordionTrigger,
} from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ConfirmationDialog } from '@/components/ui/confirmation-dialog';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useDirtyFormGuard } from '@/hooks/use-dirty-form-guard';
import { cn } from '@/lib/utils';
import ThemeCustomizerLayout from '../../../components/theme-customizer/theme-customizer-layout';
import type {
    ThemeCustomizerField,
    ThemeCustomizerPageProps,
    ThemeCustomizerSection,
} from './types';

type DeviceMode = 'desktop' | 'tablet' | 'mobile';

type CodeEditorState = {
    fieldId: string;
    label: string;
    language: string;
    value: string;
};

type ImageFieldProps = {
    fieldId: string;
    label: string;
    value: string;
    helperText?: string;
    pickerMedia: ThemeCustomizerPageProps['pickerMedia'];
    pickerFilters: ThemeCustomizerPageProps['pickerFilters'];
    uploadSettings: ThemeCustomizerPageProps['uploadSettings'];
    pickerStatistics: ThemeCustomizerPageProps['pickerStatistics'];
    pickerAction: string;
    onChange: (value: string) => void;
};

function normalizeFieldValue(
    value: string | number | boolean | null | undefined,
): string | number | boolean {
    if (typeof value === 'boolean' || typeof value === 'number') {
        return value;
    }

    return value ?? '';
}

function buildPreviewUrl(url: string, cacheBuster?: string): string {
    const resolved = new URL(url, window.location.origin);
    resolved.searchParams.set('customizer_preview', '1');

    if (cacheBuster) {
        resolved.searchParams.set('_preview', cacheBuster);
    }

    return resolved.toString();
}

function decodeCurrentPreviewLocation(currentUrl: string): string {
    const resolved = new URL(currentUrl, window.location.origin);
    resolved.searchParams.delete('_preview');

    return resolved.toString();
}

function toFormData(values: Record<string, string | number | boolean>): FormData {
    const formData = new FormData();

    Object.entries(values).forEach(([key, value]) => {
        if (typeof value === 'boolean') {
            formData.set(key, value ? 'true' : 'false');

            return;
        }

        formData.set(key, String(value ?? ''));
    });

    return formData;
}

function getFieldDescription(field: ThemeCustomizerField): string | undefined {
    return field.helper_text ?? field.description;
}

function CustomizerImageField({
    fieldId,
    label,
    value,
    helperText,
    pickerMedia,
    pickerFilters,
    uploadSettings,
    pickerStatistics,
    pickerAction,
    onChange,
}: ImageFieldProps) {
    const [open, setOpen] = useState(false);

    const handleSelect = useCallback(
        (items: MediaPickerItem[]) => {
            const item = items[0];

            if (!item) {
                return;
            }

            onChange(item.media_url || item.original_url || item.thumbnail_url || '');
            setOpen(false);
        },
        [onChange],
    );

    return (
        <Field>
            <FieldLabel>{label}</FieldLabel>
            <div className="flex flex-col gap-3">
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className={cn(
                        'group flex w-full items-center justify-center overflow-hidden rounded-2xl border border-dashed bg-muted/30 transition hover:border-primary/40 hover:bg-muted/50',
                        value ? 'min-h-[200px]' : 'min-h-[168px]',
                    )}
                >
                    {value ? (
                        <img
                            src={value}
                            alt={`${label} preview`}
                            className="max-h-[240px] w-full object-contain"
                        />
                    ) : (
                        <div className="flex flex-col items-center gap-3 text-muted-foreground">
                            <ImagePlusIcon className="size-8 opacity-50" />
                            <span className="text-sm font-medium">
                                Choose image
                            </span>
                        </div>
                    )}
                </button>

                <div className="flex items-center gap-2">
                    <Button type="button" variant="outline" onClick={() => setOpen(true)}>
                        <UploadIcon data-icon="inline-start" />
                        Choose image
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        onClick={() => onChange('')}
                        disabled={!value}
                    >
                        <Trash2Icon data-icon="inline-start" />
                        Remove
                    </Button>
                </div>
            </div>
            {helperText ? <FieldDescription>{helperText}</FieldDescription> : null}

            <MediaPickerDialog
                open={open}
                onOpenChange={setOpen}
                onSelect={handleSelect}
                selection="single"
                title={`Select ${label}`}
                pickerMedia={pickerMedia}
                pickerFilters={pickerFilters}
                uploadSettings={uploadSettings}
                pickerStatistics={pickerStatistics}
                pickerAction={pickerAction}
            />
        </Field>
    );
}

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
            ) as Record<string, string | number | boolean>,
        [initialValues],
    );
    const [values, setValues] = useState<Record<string, string | number | boolean>>(
        normalizedInitialValues,
    );
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

    const pickerAction = useMemo(() => {
        const action = route('cms.appearance.themes.customizer.index');

        return `${action}?preview_url=${encodeURIComponent(previewUrl)}`;
    }, [previewUrl]);

    const dirtyGuard = useDirtyFormGuard({
        enabled: JSON.stringify(values) !== initialSerialized.current,
    });

    const sectionsList = useMemo(
        () => Object.entries(sections) as Array<[string, ThemeCustomizerSection]>,
        [sections],
    );
    const defaultOpenSections = useMemo(
        () => sectionsList.slice(0, 2).map(([sectionId]) => sectionId),
        [sectionsList],
    );

    const iframeSource = useMemo(
        () => buildPreviewUrl(iframeBaseUrl, iframeReloadToken),
        [iframeBaseUrl, iframeReloadToken],
    );

    const previewFrameClassName = useMemo(() => {
        switch (deviceMode) {
            case 'tablet':
                return 'w-full max-w-[834px]';
            case 'mobile':
                return 'w-full max-w-[430px]';
            default:
                return 'w-full';
        }
    }, [deviceMode]);

    const setFieldValue = useCallback(
        (fieldId: string, value: string | number | boolean) => {
            setValues((current) => ({
                ...current,
                [fieldId]: value,
            }));
        },
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
    });

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
            const response = await fetch(route('cms.appearance.themes.customizer.update'), {
                method: 'POST',
                body: toFormData(values),
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = (await response.json()) as {
                success?: boolean;
                message?: string;
                error?: string;
            };

            if (!response.ok || payload.success === false) {
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
    }, [refreshPreview, values]);

    const handleReset = useCallback(async () => {
        try {
            const response = await fetch(route('cms.appearance.themes.customizer.reset'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = (await response.json()) as {
                success?: boolean;
                message?: string;
                error?: string;
            };

            if (!response.ok || payload.success === false) {
                throw new Error(payload.error || payload.message || 'Reset failed.');
            }

            showAppToast({
                variant: 'success',
                title: 'Customizer reset',
                description: payload.message || 'Theme settings were reset to defaults.',
            });
            initialSerialized.current = JSON.stringify(normalizedInitialValues);
            router.reload();
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
    }, [normalizedInitialValues]);

    const handleExport = useCallback(async () => {
        try {
            const response = await fetch(route('cms.appearance.themes.customizer.export'), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const payload = (await response.json()) as {
                success?: boolean;
                filename?: string;
                data?: string;
                message?: string;
                error?: string;
            };

            if (!response.ok || payload.success === false || !payload.filename || payload.data === undefined) {
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
    }, []);

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
                const response = await fetch(route('cms.appearance.themes.customizer.import'), {
                    method: 'POST',
                    body: formData,
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const payload = (await response.json()) as {
                    success?: boolean;
                    message?: string;
                    error?: string;
                };

                if (!response.ok || payload.success === false) {
                    throw new Error(payload.error || payload.message || 'Import failed.');
                }

                setImportDialogOpen(false);
                setImportFile(null);
                showAppToast({
                    variant: 'success',
                    title: 'Customizer imported',
                    description: payload.message || 'Theme settings were imported successfully.',
                });
                router.reload();
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
        [importFile],
    );

    const openCodeEditor = useCallback((fieldId: string, field: ThemeCustomizerField) => {
        setActiveCodeEditor({
            fieldId,
            label: field.label,
            language: field.language ?? 'plaintext',
            value: String(values[fieldId] ?? ''),
        });
    }, [values]);

    const renderField = useCallback(
        (fieldId: string, field: ThemeCustomizerField) => {
            const description = getFieldDescription(field);
            const rawValue = values[fieldId] ?? normalizeFieldValue(field.default);

            if (field.type === 'color') {
                return (
                    <Field key={fieldId}>
                        <FieldLabel htmlFor={fieldId}>{field.label}</FieldLabel>
                        <div className="flex items-center gap-3 rounded-2xl border bg-background px-3 py-3">
                            <input
                                id={fieldId}
                                type="color"
                                value={String(rawValue || '#000000')}
                                onChange={(event) => setFieldValue(fieldId, event.target.value)}
                                className="size-10 cursor-pointer rounded-xl border-0 bg-transparent p-0"
                            />
                            <Input
                                value={String(rawValue)}
                                onChange={(event) => setFieldValue(fieldId, event.target.value)}
                                placeholder="#000000"
                            />
                        </div>
                        {description ? <FieldDescription>{description}</FieldDescription> : null}
                    </Field>
                );
            }

            if (field.type === 'select') {
                const options = Object.entries(field.options ?? {});

                return (
                    <Field key={fieldId}>
                        <FieldLabel htmlFor={fieldId}>{field.label}</FieldLabel>
                        <NativeSelect
                            id={fieldId}
                            className="w-full"
                            value={String(rawValue)}
                            onChange={(event) => setFieldValue(fieldId, event.target.value)}
                        >
                            {options.map(([optionValue, optionLabel]) => (
                                <NativeSelectOption key={optionValue} value={optionValue}>
                                    {optionLabel}
                                </NativeSelectOption>
                            ))}
                        </NativeSelect>
                        {description ? <FieldDescription>{description}</FieldDescription> : null}
                    </Field>
                );
            }

            if (field.type === 'textarea') {
                return (
                    <Field key={fieldId}>
                        <FieldLabel htmlFor={fieldId}>{field.label}</FieldLabel>
                        <Textarea
                            id={fieldId}
                            rows={field.rows ?? 4}
                            value={String(rawValue)}
                            placeholder={field.placeholder ?? ''}
                            onChange={(event) => setFieldValue(fieldId, event.target.value)}
                        />
                        {description ? <FieldDescription>{description}</FieldDescription> : null}
                    </Field>
                );
            }

            if (field.type === 'checkbox') {
                return (
                    <Field key={fieldId} orientation="horizontal" className="items-center justify-between rounded-2xl border bg-background px-4 py-3">
                        <div className="flex flex-col gap-1">
                            <FieldLabel htmlFor={fieldId}>{field.label}</FieldLabel>
                            {description ? <FieldDescription>{description}</FieldDescription> : null}
                        </div>
                        <Switch
                            id={fieldId}
                            checked={Boolean(rawValue)}
                            onCheckedChange={(checked) => setFieldValue(fieldId, checked)}
                        />
                    </Field>
                );
            }

            if (field.type === 'image') {
                return (
                    <CustomizerImageField
                        key={fieldId}
                        fieldId={fieldId}
                        label={field.label}
                        value={String(rawValue)}
                        helperText={description}
                        pickerMedia={pickerMedia}
                        pickerFilters={pickerFilters}
                        uploadSettings={uploadSettings}
                        pickerStatistics={pickerStatistics}
                        pickerAction={pickerAction}
                        onChange={(value) => setFieldValue(fieldId, value)}
                    />
                );
            }

            if (field.type === 'code_editor') {
                return (
                    <Field key={fieldId}>
                        <FieldLabel>{field.label}</FieldLabel>
                        <button
                            type="button"
                            onClick={() => openCodeEditor(fieldId, field)}
                            className="flex w-full items-center justify-between rounded-2xl border bg-background px-4 py-3 text-left transition hover:border-primary/40 hover:bg-muted/40"
                        >
                            <div className="flex items-center gap-3">
                                <Code2Icon className="size-4 text-muted-foreground" />
                                <div>
                                    <div className="font-medium text-foreground">
                                        {field.label}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        {field.language === 'css'
                                            ? 'Edit theme CSS overrides'
                                            : field.language === 'javascript'
                                              ? 'Edit custom JavaScript'
                                              : 'Open code editor'}
                                    </div>
                                </div>
                            </div>
                            <Badge variant="secondary">
                                {String(rawValue).trim() === '' ? 'Empty' : 'Has code'}
                            </Badge>
                        </button>
                        {description ? <FieldDescription>{description}</FieldDescription> : null}
                    </Field>
                );
            }

            return (
                <Field key={fieldId}>
                    <FieldLabel htmlFor={fieldId}>{field.label}</FieldLabel>
                    <Input
                        id={fieldId}
                        type={field.type === 'number' ? 'number' : 'text'}
                        value={String(rawValue)}
                        placeholder={field.placeholder ?? ''}
                        onChange={(event) => setFieldValue(fieldId, event.target.value)}
                    />
                    {description ? <FieldDescription>{description}</FieldDescription> : null}
                    <FieldError />
                </Field>
            );
        },
        [openCodeEditor, pickerAction, pickerFilters, pickerMedia, pickerStatistics, setFieldValue, uploadSettings, values],
    );

    const sidebarContent = (
        <div className="flex h-full flex-col">
            <div className="border-b border-border/70 px-4 py-4 sm:px-5">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <p className="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                            Theme Settings
                        </p>
                        <h2 className="mt-2 text-lg font-semibold text-foreground">
                            {activeTheme.name}
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Shared site identity controls plus theme-specific presentation settings.
                        </p>
                    </div>
                    {activeTheme.version ? (
                        <Badge variant="outline">v{activeTheme.version}</Badge>
                    ) : null}
                </div>
            </div>

            <ScrollArea className="min-h-0 flex-1">
                <div className="flex flex-col gap-4 px-4 py-4 sm:px-5">
                    <Alert className="border-primary/20 bg-primary/5 text-primary">
                        <EyeIcon className="size-4" />
                        <AlertTitle>Live preview</AlertTitle>
                        <AlertDescription>
                            Changes update the preview pane before you save. Save commits them to theme settings.
                        </AlertDescription>
                    </Alert>

                    <Accordion type="multiple" defaultValue={defaultOpenSections} className="gap-3">
                        {sectionsList.map(([sectionId, section]) => (
                            <AccordionItem
                                key={sectionId}
                                value={sectionId}
                                className="rounded-2xl border bg-background px-4 shadow-xs"
                            >
                                <AccordionTrigger className="py-4 text-base hover:no-underline">
                                    <div className="pr-4">
                                        <div className="font-semibold text-foreground">
                                            {section.title}
                                        </div>
                                        {section.description || section.helper_text ? (
                                            <div className="mt-1 text-sm font-normal text-muted-foreground">
                                                {section.helper_text ?? section.description}
                                            </div>
                                        ) : null}
                                    </div>
                                </AccordionTrigger>
                                <AccordionContent className="pb-4">
                                    <FieldGroup>
                                        {Object.entries(section.settings ?? {}).map(([fieldId, field]) =>
                                            renderField(fieldId, field),
                                        )}
                                    </FieldGroup>
                                </AccordionContent>
                            </AccordionItem>
                        ))}
                    </Accordion>
                </div>
            </ScrollArea>
        </div>
    );

    return (
        <ThemeCustomizerLayout
            title="Theme Customizer"
            description="Adjust theme settings with a live frontend preview."
        >
            {dirtyGuard.dialog}

            <div className="flex min-h-0 flex-1 flex-col">
                <header className="border-b border-border/70 bg-white/80 px-4 py-3 backdrop-blur sm:px-5">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" asChild>
                            <a href={route('cms.appearance.themes.index')}>
                                <ArrowLeftIcon data-icon="inline-start" />
                                Back
                            </a>
                        </Button>

                        <div className="min-w-0 flex-1">
                            <div className="flex items-center gap-2">
                                <h1 className="text-base font-semibold text-foreground sm:text-lg">
                                    Theme Customizer
                                </h1>
                                <Badge variant="secondary" className="hidden sm:inline-flex">
                                    {activeTheme.name}
                                </Badge>
                            </div>
                            <p className="text-sm text-muted-foreground">
                                Refined controls for theme identity, style, and live preview workflows.
                            </p>
                        </div>

                        <div className="hidden items-center gap-2 lg:flex">
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={() => setSidebarCollapsed((value) => !value)}
                                title={sidebarCollapsed ? 'Show settings' : 'Hide settings'}
                            >
                                {sidebarCollapsed ? <PanelLeftOpenIcon /> : <PanelLeftCloseIcon />}
                            </Button>
                        </div>

                        <div className="hidden items-center gap-2 md:flex">
                            <ToggleGroup
                                type="single"
                                value={deviceMode}
                                onValueChange={(value) => {
                                    if (value) {
                                        setDeviceMode(value as DeviceMode);
                                    }
                                }}
                            >
                                <ToggleGroupItem value="desktop" aria-label="Desktop preview">
                                    <LaptopIcon />
                                </ToggleGroupItem>
                                <ToggleGroupItem value="tablet" aria-label="Tablet preview">
                                    <TabletIcon />
                                </ToggleGroupItem>
                                <ToggleGroupItem value="mobile" aria-label="Mobile preview">
                                    <SmartphoneIcon />
                                </ToggleGroupItem>
                            </ToggleGroup>
                        </div>

                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={refreshPreview}
                                disabled={isRefreshingPreview}
                                title="Refresh preview"
                            >
                                <RefreshCwIcon className={cn(isRefreshingPreview && 'animate-spin')} />
                            </Button>

                            <div className="lg:hidden">
                                <Button
                                    variant="outline"
                                    size="icon"
                                    onClick={() => setMobileSidebarOpen(true)}
                                    title="Open settings"
                                >
                                    <PanelLeftOpenIcon />
                                </Button>
                            </div>

                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline">More</Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem onClick={handleExport}>
                                        <DownloadIcon />
                                        Export settings
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => setImportDialogOpen(true)}>
                                        <ImportIcon />
                                        Import settings
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => setResetDialogOpen(true)}>
                                        <RotateCcwIcon />
                                        Reset to defaults
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <Button onClick={() => void handleSave()} disabled={isSaving}>
                                <SaveIcon data-icon="inline-start" className={cn(isSaving && 'animate-pulse')} />
                                Save
                            </Button>
                        </div>
                    </div>
                </header>

                <div className="grid min-h-0 flex-1 grid-cols-1 lg:grid-cols-[minmax(0,var(--customizer-sidebar-width))_minmax(0,1fr)]" style={{ ['--customizer-sidebar-width' as string]: sidebarCollapsed ? '0px' : '400px' }}>
                    <aside className={cn('hidden min-h-0 border-r border-border/70 bg-white/75 backdrop-blur lg:block', sidebarCollapsed && 'overflow-hidden border-r-0')}>
                        {!sidebarCollapsed ? sidebarContent : null}
                    </aside>

                    <div className="min-h-0 bg-[radial-gradient(circle_at_top_left,rgba(99,102,241,0.14),transparent_35%),radial-gradient(circle_at_top_right,rgba(236,72,153,0.12),transparent_32%),linear-gradient(180deg,#eef2ff_0%,#f8fafc_55%,#ffffff_100%)] p-4 sm:p-6">
                        <Card className="flex h-full min-h-0 flex-col overflow-hidden border-white/70 bg-white/80 shadow-[0_20px_60px_rgba(15,23,42,0.08)] backdrop-blur-sm">
                            <CardHeader className="border-b border-border/60 pb-4">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <CardTitle>Frontend preview</CardTitle>
                                        <CardDescription>
                                            Navigate inside the frame to inspect live theme changes before saving.
                                        </CardDescription>
                                    </div>
                                    <Badge variant="outline" className="capitalize">
                                        {deviceMode}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="min-h-0 flex-1 p-4 sm:p-6">
                                <div className="flex h-full items-start justify-center overflow-auto rounded-[28px] bg-[linear-gradient(180deg,#e2e8f0_0%,#f8fafc_16%,#eef2ff_100%)] p-4 sm:p-6">
                                    <div className={cn('overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-[0_24px_80px_rgba(15,23,42,0.16)] transition-all duration-300', previewFrameClassName)}>
                                        <iframe
                                            ref={iframeRef}
                                            key={iframeSource}
                                            src={iframeSource}
                                            onLoad={handlePreviewLoad}
                                            className={cn(
                                                'w-full border-0 bg-white',
                                                deviceMode === 'desktop' && 'min-h-[calc(100vh-18rem)]',
                                                deviceMode === 'tablet' && 'min-h-[900px]',
                                                deviceMode === 'mobile' && 'min-h-[820px]',
                                            )}
                                            title="Theme preview"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            <Sheet open={mobileSidebarOpen} onOpenChange={setMobileSidebarOpen}>
                <SheetContent side="left" className="w-[92vw] max-w-[420px] p-0 sm:max-w-[420px]">
                    <SheetHeader className="border-b border-border/70 pb-4">
                        <SheetTitle>Theme settings</SheetTitle>
                        <SheetDescription>
                            Adjust the active theme and watch the preview update in real time.
                        </SheetDescription>
                    </SheetHeader>
                    <div className="min-h-0 flex-1">{sidebarContent}</div>
                </SheetContent>
            </Sheet>

            <Dialog
                open={activeCodeEditor !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setActiveCodeEditor(null);
                    }
                }}
            >
                <DialogContent className="max-w-[min(96vw,1100px)] overflow-hidden p-0">
                    <DialogHeader className="border-b px-6 py-4">
                        <DialogTitle>{activeCodeEditor?.label}</DialogTitle>
                        <DialogDescription>
                            Edit and apply code changes to the live preview before saving the customizer.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="px-6 py-5">
                        <MonacoEditor
                            value={activeCodeEditor?.value ?? ''}
                            onChange={(value) =>
                                setActiveCodeEditor((current) =>
                                    current ? { ...current, value } : current,
                                )
                            }
                            language={activeCodeEditor?.language ?? 'plaintext'}
                            height="min(68vh,720px)"
                        />
                    </div>
                    <DialogFooter className="border-t px-6 py-4">
                        <Button variant="outline" onClick={() => setActiveCodeEditor(null)}>
                            Cancel
                        </Button>
                        <Button
                            onClick={() => {
                                if (!activeCodeEditor) {
                                    return;
                                }

                                setFieldValue(activeCodeEditor.fieldId, activeCodeEditor.value);
                                setActiveCodeEditor(null);
                            }}
                        >
                            Apply changes
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={importDialogOpen} onOpenChange={setImportDialogOpen}>
                <DialogContent className="max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Import theme settings</DialogTitle>
                        <DialogDescription>
                            Upload a JSON export to replace the current customizer state for this theme.
                        </DialogDescription>
                    </DialogHeader>
                    <form className="flex flex-col gap-4" onSubmit={handleImportSubmit}>
                        <Field>
                            <FieldLabel htmlFor="settings-file">Settings file</FieldLabel>
                            <Input
                                id="settings-file"
                                type="file"
                                accept=".json,application/json"
                                onChange={(event) =>
                                    setImportFile(event.target.files?.[0] ?? null)
                                }
                            />
                            <FieldDescription>
                                Use a JSON file exported from the theme customizer.
                            </FieldDescription>
                        </Field>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setImportDialogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit">Import</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmationDialog
                open={resetDialogOpen}
                onOpenChange={setResetDialogOpen}
                title="Reset theme settings?"
                description="This will restore the customizer fields to their default values for the active theme."
                confirmLabel="Reset settings"
                icon={<RotateCcwIcon className="size-4" />}
                confirmClassName="bg-destructive text-white hover:bg-destructive/90"
                onConfirm={() => void handleReset()}
            />
        </ThemeCustomizerLayout>
    );
}