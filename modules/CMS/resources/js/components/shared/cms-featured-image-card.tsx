import { MediaPickerField } from '@/components/media/media-picker-field';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { FieldError } from '@/components/ui/field';
import type { MediaPickerPageProps } from '../../types/cms';

type CmsFeaturedImageCardProps = {
    value: number | '';
    previewUrl?: string | null;
    pickerAction: string;
    onChange: (value: number | '') => void;
    onTouch?: () => void;
    invalid?: boolean;
    error?: string;
    title?: string;
    description?: string;
} & MediaPickerPageProps;

export function CmsFeaturedImageCard({
    value,
    previewUrl = null,
    pickerAction,
    onChange,
    onTouch,
    invalid = false,
    error,
    title = 'Featured image',
    description = 'Choose an image or upload a new one.',
    pickerMedia,
    pickerFilters,
    uploadSettings,
    pickerStatistics,
}: CmsFeaturedImageCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <MediaPickerField
                    value={value || null}
                    previewUrl={previewUrl}
                    onChange={(item) => {
                        onChange(item ? item.id : '');
                        onTouch?.();
                    }}
                    dialogTitle="Select featured image"
                    selectLabel="Select featured image"
                    aria-invalid={invalid || undefined}
                    pickerMedia={pickerMedia}
                    pickerFilters={pickerFilters}
                    uploadSettings={uploadSettings}
                    pickerStatistics={pickerStatistics}
                    pickerAction={pickerAction}
                />
                <FieldError>{error}</FieldError>
            </CardContent>
        </Card>
    );
}