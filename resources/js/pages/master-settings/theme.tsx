import { PaletteIcon, SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import type { BreadcrumbItem, SettingsNavItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Settings', href: route('app.masters.settings.index') },
    { title: 'Theme', href: route('app.masters.settings.theme') },
];

type ThemeOption = {
    value: string;
    label: string;
    description: string;
};

type ThemePageProps = {
    settings: {
        admin_theme: string;
    };
    options: {
        themes: ThemeOption[];
    };
    settingsNav: SettingsNavItem[];
};

type ThemeFormData = {
    admin_theme: string;
};

export default function Theme({
    settings,
    options,
    settingsNav,
}: ThemePageProps) {
    const form = useAppForm<ThemeFormData>({
        defaults: {
            admin_theme: settings.admin_theme,
        },
        rememberKey: 'master-settings.theme',
        dirtyGuard: { enabled: true },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('put', route('app.masters.settings.update', 'theme'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Theme updated',
                description:
                    'Your backend color scheme has been applied successfully.',
            },
        });
    };

    return (
        <SettingsLayout
            settingsNav={settingsNav}
            breadcrumbs={breadcrumbs}
            title="Master Settings"
            description="Manage platform-level configuration."
        >
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6">
                <form
                    noValidate
                    className="flex flex-col gap-6"
                    onSubmit={handleSubmit}
                >
                    {form.dirtyGuardDialog}
                    <FormErrorSummary errors={form.errors} minMessages={2} />

                    <Card>
                        <CardHeader>
                            <CardTitle>Backend Theme</CardTitle>
                            <CardDescription>
                                Switch the admin color scheme without changing
                                layout density, radius, or component structure.
                            </CardDescription>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('admin_theme') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="admin_theme">
                                        Color Scheme
                                    </FieldLabel>
                                    <FieldDescription>
                                        Default keeps the current palette.
                                        Other options only override the shared
                                        color tokens used across the backend.
                                    </FieldDescription>

                                    <RadioGroup
                                        id="admin_theme"
                                        value={form.data.admin_theme}
                                        onValueChange={(value) =>
                                            form.setField('admin_theme', value)
                                        }
                                        className="grid gap-3 md:grid-cols-2"
                                        aria-invalid={
                                            form.invalid('admin_theme') ||
                                            undefined
                                        }
                                    >
                                        {options.themes.map((theme) => {
                                            const selected =
                                                form.data.admin_theme ===
                                                theme.value;

                                            return (
                                                <label
                                                    key={theme.value}
                                                    className="flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-card/70 p-4 transition-colors hover:border-primary/35 hover:bg-accent/40"
                                                >
                                                    <RadioGroupItem
                                                        value={theme.value}
                                                        className="mt-1"
                                                    />
                                                    <div className="min-w-0 flex-1 space-y-2">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium text-foreground">
                                                                {theme.label}
                                                            </span>
                                                            {selected ? (
                                                                <span className="inline-flex items-center rounded-full bg-primary/12 px-2 py-0.5 text-xs font-medium text-primary">
                                                                    Active
                                                                </span>
                                                            ) : null}
                                                        </div>
                                                        <p className="text-sm leading-6 text-muted-foreground">
                                                            {
                                                                theme.description
                                                            }
                                                        </p>
                                                        <div className="flex items-center gap-2 pt-1">
                                                            <span className="inline-flex size-8 items-center justify-center rounded-full border border-border bg-background text-muted-foreground">
                                                                <PaletteIcon className="size-4" />
                                                            </span>
                                                            <span className="text-xs uppercase tracking-[0.16em] text-muted-foreground">
                                                                {theme.value}
                                                            </span>
                                                        </div>
                                                    </div>
                                                </label>
                                            );
                                        })}
                                    </RadioGroup>

                                    <FieldError>
                                        {form.error('admin_theme')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? (
                            <Spinner />
                        ) : (
                            <SaveIcon data-icon="inline-start" />
                        )}
                        {form.processing ? 'Saving...' : 'Save Settings'}
                    </Button>
                </form>
            </div>
        </SettingsLayout>
    );
}