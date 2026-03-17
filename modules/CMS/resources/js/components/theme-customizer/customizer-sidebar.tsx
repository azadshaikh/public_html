import { Code2Icon, EyeIcon } from 'lucide-react';
import { useCallback } from 'react';
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
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type {
    ThemeCustomizerField,
    ThemeCustomizerPageProps,
    ThemeCustomizerSection,
    ThemeCustomizerSnapshot,
    ThemeCustomizerTheme,
} from '../../pages/cms/themes/customizer/types';
import { CustomizerImageField } from './customizer-image-field';
import {
    getFieldDescription,
    normalizeFieldValue,
} from './customizer-utils';

type ThemeCustomizerSidebarProps = {
    activeTheme: ThemeCustomizerTheme;
    sections: Record<string, ThemeCustomizerSection>;
    values: ThemeCustomizerSnapshot;
    defaultOpenSections: string[];
    pickerMedia: ThemeCustomizerPageProps['pickerMedia'];
    pickerFilters: ThemeCustomizerPageProps['pickerFilters'];
    uploadSettings: ThemeCustomizerPageProps['uploadSettings'];
    pickerStatistics: ThemeCustomizerPageProps['pickerStatistics'];
    pickerAction: string;
    onFieldChange: (fieldId: string, value: string | number | boolean) => void;
    onOpenCodeEditor: (fieldId: string, field: ThemeCustomizerField) => void;
};

export function ThemeCustomizerSidebar({
    activeTheme,
    sections,
    values,
    defaultOpenSections,
    pickerMedia,
    pickerFilters,
    uploadSettings,
    pickerStatistics,
    pickerAction,
    onFieldChange,
    onOpenCodeEditor,
}: ThemeCustomizerSidebarProps) {
    const renderField = useCallback(
        (fieldId: string, field: ThemeCustomizerField) => {
            const description = getFieldDescription(field);
            const rawValue = values[fieldId] ?? normalizeFieldValue(field.default);

            if (field.type === 'color') {
                return (
                    <Field key={fieldId}>
                        <FieldLabel htmlFor={fieldId}>{field.label}</FieldLabel>
                        <div className="flex items-center gap-2 rounded-xl border bg-background px-2.5 py-2">
                            <input
                                id={fieldId}
                                type="color"
                                value={String(rawValue || '#000000')}
                                onChange={(event) => onFieldChange(fieldId, event.target.value)}
                                className="size-8 cursor-pointer rounded-lg border-0 bg-transparent p-0"
                            />
                            <Input
                                value={String(rawValue)}
                                onChange={(event) => onFieldChange(fieldId, event.target.value)}
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
                            onChange={(event) => onFieldChange(fieldId, event.target.value)}
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
                            onChange={(event) => onFieldChange(fieldId, event.target.value)}
                        />
                        {description ? <FieldDescription>{description}</FieldDescription> : null}
                    </Field>
                );
            }

            if (field.type === 'checkbox') {
                return (
                    <Field
                        key={fieldId}
                        orientation="horizontal"
                        className="items-center justify-between rounded-xl border bg-background px-3 py-2.5"
                    >
                        <div className="flex flex-col gap-0.5">
                            <FieldLabel htmlFor={fieldId}>{field.label}</FieldLabel>
                            {description ? <FieldDescription>{description}</FieldDescription> : null}
                        </div>
                        <Switch
                            id={fieldId}
                            checked={Boolean(rawValue)}
                            onCheckedChange={(checked) => onFieldChange(fieldId, checked)}
                        />
                    </Field>
                );
            }

            if (field.type === 'image') {
                return (
                    <CustomizerImageField
                        key={fieldId}
                        label={field.label}
                        value={String(rawValue)}
                        helperText={description}
                        pickerMedia={pickerMedia}
                        pickerFilters={pickerFilters}
                        uploadSettings={uploadSettings}
                        pickerStatistics={pickerStatistics}
                        pickerAction={pickerAction}
                        onChange={(value) => onFieldChange(fieldId, value)}
                    />
                );
            }

            if (field.type === 'code_editor') {
                return (
                    <Field key={fieldId}>
                        <FieldLabel>{field.label}</FieldLabel>
                        <button
                            type="button"
                            onClick={() => onOpenCodeEditor(fieldId, field)}
                            className="flex w-full items-center justify-between rounded-xl border bg-background px-3 py-2.5 text-left transition hover:border-primary/40 hover:bg-muted/40"
                        >
                            <div className="flex items-center gap-2.5">
                                <Code2Icon className="size-4 text-muted-foreground" />
                                <div>
                                    <div className="text-sm font-medium text-foreground">
                                        {field.label}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
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
                        onChange={(event) => onFieldChange(fieldId, event.target.value)}
                    />
                    {description ? <FieldDescription>{description}</FieldDescription> : null}
                    <FieldError />
                </Field>
            );
        },
        [
            onFieldChange,
            onOpenCodeEditor,
            pickerAction,
            pickerFilters,
            pickerMedia,
            pickerStatistics,
            uploadSettings,
            values,
        ],
    );

    return (
        <div className="flex h-full flex-col">
            <div className="border-b border-border/70 px-3 py-3 sm:px-4">
                <div className="flex items-start justify-between gap-2.5">
                    <div>
                        <p className="text-[11px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                            Theme Settings
                        </p>
                        <h2 className="mt-1.5 text-base font-semibold text-foreground">
                            {activeTheme.name}
                        </h2>
                        <p className="mt-1 text-xs leading-5 text-muted-foreground">
                            Shared site identity controls plus theme-specific presentation settings.
                        </p>
                    </div>
                    {activeTheme.version ? (
                        <Badge variant="outline">v{activeTheme.version}</Badge>
                    ) : null}
                </div>
            </div>

            <ScrollArea className="min-h-0 flex-1">
                <div className="flex flex-col gap-3 px-3 py-3 sm:px-4">
                    <Alert className="border-primary/20 bg-primary/5 px-3 py-2 text-primary">
                        <EyeIcon className="size-4" />
                        <AlertTitle className="text-sm">Live preview</AlertTitle>
                        <AlertDescription className="text-xs leading-5">
                            Changes update the preview pane before you save. Save commits them to theme settings.
                        </AlertDescription>
                    </Alert>

                    <Accordion type="multiple" defaultValue={defaultOpenSections} className="gap-2.5">
                        {Object.entries(sections).map(([sectionId, section]) => (
                            <AccordionItem
                                key={sectionId}
                                value={sectionId}
                                className="rounded-xl border bg-background px-3 shadow-xs"
                            >
                                <AccordionTrigger className="py-3 text-sm hover:no-underline">
                                    <div className="pr-3 text-left">
                                        <div className="font-semibold text-foreground">
                                            {section.title}
                                        </div>
                                        {section.description || section.helper_text ? (
                                            <div className="mt-0.5 text-xs font-normal leading-5 text-muted-foreground">
                                                {section.helper_text ?? section.description}
                                            </div>
                                        ) : null}
                                    </div>
                                </AccordionTrigger>
                                <AccordionContent className="pb-3">
                                    <FieldGroup className="gap-4">
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
}