import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import type { CmsOption } from '../../types/cms';

type CmsSeoFieldsProps = {
    metaTitle: string;
    metaDescription: string;
    metaRobots: string;
    metaRobotsOptions: CmsOption[];
    onMetaTitleChange: (value: string) => void;
    onMetaDescriptionChange: (value: string) => void;
    onMetaRobotsChange: (value: string) => void;
    onMetaTitleBlur: () => void;
    onMetaDescriptionBlur: () => void;
    onMetaRobotsBlur: () => void;
    metaTitleInvalid?: boolean;
    metaDescriptionInvalid?: boolean;
    metaRobotsInvalid?: boolean;
    metaTitleError?: string;
    metaDescriptionError?: string;
    metaRobotsError?: string;
    surfaceClassName?: string;
};

export function CmsSeoFields({
    metaTitle,
    metaDescription,
    metaRobots,
    metaRobotsOptions,
    onMetaTitleChange,
    onMetaDescriptionChange,
    onMetaRobotsChange,
    onMetaTitleBlur,
    onMetaDescriptionBlur,
    onMetaRobotsBlur,
    metaTitleInvalid = false,
    metaDescriptionInvalid = false,
    metaRobotsInvalid = false,
    metaTitleError,
    metaDescriptionError,
    metaRobotsError,
    surfaceClassName,
}: CmsSeoFieldsProps) {
    return (
        <>
            <Field data-invalid={metaTitleInvalid || undefined}>
                <FieldLabel htmlFor="meta_title">Meta title</FieldLabel>
                <Input
                    id="meta_title"
                    className={surfaceClassName}
                    value={metaTitle}
                    onChange={(event) => onMetaTitleChange(event.target.value)}
                    onBlur={onMetaTitleBlur}
                    aria-invalid={metaTitleInvalid || undefined}
                    placeholder="Enter meta title"
                />
                <FieldDescription>
                    Recommended length: 50-60 characters.
                </FieldDescription>
                <FieldError>{metaTitleError}</FieldError>
            </Field>

            <Field data-invalid={metaDescriptionInvalid || undefined}>
                <FieldLabel htmlFor="meta_description">
                    Meta description
                </FieldLabel>
                <Textarea
                    id="meta_description"
                    className={surfaceClassName}
                    rows={4}
                    value={metaDescription}
                    onChange={(event) =>
                        onMetaDescriptionChange(event.target.value)
                    }
                    onBlur={onMetaDescriptionBlur}
                    aria-invalid={metaDescriptionInvalid || undefined}
                    placeholder="Enter meta description"
                />
                <FieldError>{metaDescriptionError}</FieldError>
            </Field>

            <Field data-invalid={metaRobotsInvalid || undefined}>
                <FieldLabel htmlFor="meta_robots">Meta robots</FieldLabel>
                <NativeSelect
                    id="meta_robots"
                    className={surfaceClassName ? `w-full ${surfaceClassName}` : 'w-full'}
                    value={metaRobots}
                    onChange={(event) => onMetaRobotsChange(event.target.value)}
                    onBlur={onMetaRobotsBlur}
                    aria-invalid={metaRobotsInvalid || undefined}
                >
                    {metaRobotsOptions.map((option) => (
                        <NativeSelectOption
                            key={String(option.value)}
                            value={String(option.value)}
                            disabled={option.disabled}
                        >
                            {option.label}
                        </NativeSelectOption>
                    ))}
                </NativeSelect>
                <FieldError>{metaRobotsError}</FieldError>
            </Field>
        </>
    );
}