import { ImagePlusIcon, Trash2Icon, UploadIcon } from 'lucide-react';
import { useCallback, useState } from 'react';
import { MediaPickerDialog } from '@/components/media/media-picker-dialog';
import type { MediaPickerItem } from '@/components/media/media-picker-utils';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription, FieldLabel } from '@/components/ui/field';
import { cn } from '@/lib/utils';
import type { ThemeCustomizerPageProps } from '../../pages/cms/themes/customizer/types';

type CustomizerImageFieldProps = {
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

export function CustomizerImageField({
    label,
    value,
    helperText,
    pickerMedia,
    pickerFilters,
    uploadSettings,
    pickerStatistics,
    pickerAction,
    onChange,
}: CustomizerImageFieldProps) {
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
            <div className="flex flex-col gap-2.5">
                <button
                    type="button"
                    onClick={() => setOpen(true)}
                    className={cn(
                        'group flex w-full items-center justify-center overflow-hidden rounded-xl border border-dashed bg-muted/30 transition hover:border-primary/40 hover:bg-muted/50',
                        value ? 'min-h-[172px]' : 'min-h-[132px]',
                    )}
                >
                    {value ? (
                        <img
                            src={value}
                            alt={`${label} preview`}
                            className="max-h-[190px] w-full object-contain"
                        />
                    ) : (
                        <div className="flex flex-col items-center gap-2 text-muted-foreground">
                            <ImagePlusIcon className="size-6 opacity-50" />
                            <span className="text-xs font-medium">Choose image</span>
                        </div>
                    )}
                </button>

                <div className="flex items-center gap-2">
                    <Button type="button" size="sm" variant="outline" onClick={() => setOpen(true)}>
                        <UploadIcon data-icon="inline-start" />
                        Choose
                    </Button>
                    <Button
                        type="button"
                        size="sm"
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