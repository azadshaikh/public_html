import { Link } from '@inertiajs/react';
import { ArrowLeftIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useAppForm } from '@/hooks/use-app-form';
import AppLayout from '@/layouts/app-layout';
import { formValidators } from '@/lib/forms';
import type { BreadcrumbItem } from '@/types';
import type { MenuCreatePageProps } from '../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Menus', href: route('cms.appearance.menus.index') },
    { title: 'Create', href: route('cms.appearance.menus.create') },
];

type MenuCreateFormValues = {
    name: string;
    location: string;
    is_active: boolean;
    description: string;
};

const emptyValues: MenuCreateFormValues = {
    name: '',
    location: '',
    is_active: true,
    description: '',
};

export default function MenusCreate({
    locationOptions,
    assignedMenus,
}: MenuCreatePageProps) {
    const form = useAppForm<MenuCreateFormValues>({
        defaults: emptyValues,
        rememberKey: 'cms.menus.create',
        dirtyGuard: { enabled: true },
        rules: {
            name: [formValidators.required('Menu name')],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('post', route('cms.appearance.menus.store'), {
            preserveScroll: true,
        });
    };

    const selectedLocation = form.data.location;
    const locationConflict =
        selectedLocation && assignedMenus[selectedLocation]
            ? assignedMenus[selectedLocation]
            : null;

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create Menu"
            description="Add a new navigation menu and assign it to a theme location."
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('cms.appearance.menus.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to Menus
                    </Link>
                </Button>
            }
        >
            <form noValidate onSubmit={handleSubmit} className="max-w-2xl">
                <Card>
                    <CardHeader>
                        <CardTitle>Menu Settings</CardTitle>
                        <CardDescription>
                            Configure your menu. You can add items and set up
                            the structure after creating it.
                        </CardDescription>
                    </CardHeader>

                    <CardContent className="flex flex-col gap-6">
                        <FormErrorSummary
                            errors={form.errors}
                            minMessages={2}
                        />

                        <Field data-invalid={form.invalid('name') || undefined}>
                            <FieldLabel htmlFor="name">
                                Menu Name{' '}
                                <span className="text-destructive">*</span>
                            </FieldLabel>
                            <Input
                                id="name"
                                type="text"
                                placeholder="e.g. Main Navigation"
                                aria-invalid={form.invalid('name') || undefined}
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setField('name', e.target.value)
                                }
                                onBlur={() => form.touch('name')}
                            />
                            <FieldError>{form.error('name')}</FieldError>
                        </Field>

                        <Field
                            data-invalid={form.invalid('location') || undefined}
                        >
                            <FieldLabel htmlFor="location">
                                Theme Location
                            </FieldLabel>
                            <NativeSelect
                                id="location"
                                aria-invalid={
                                    form.invalid('location') || undefined
                                }
                                value={form.data.location}
                                onChange={(e) =>
                                    form.setField('location', e.target.value)
                                }
                                onBlur={() => form.touch('location')}
                            >
                                <NativeSelectOption value="">
                                    — No location —
                                </NativeSelectOption>
                                {locationOptions.map((option) => (
                                    <NativeSelectOption
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </NativeSelectOption>
                                ))}
                            </NativeSelect>
                            {locationConflict ? (
                                <FieldDescription className="text-amber-600 dark:text-amber-400">
                                    <strong>{locationConflict.name}</strong> is
                                    already assigned to this location. Assigning
                                    this menu will unassign it.
                                </FieldDescription>
                            ) : (
                                <FieldDescription>
                                    Assign this menu to a location defined by
                                    your active theme.
                                </FieldDescription>
                            )}
                            <FieldError>{form.error('location')}</FieldError>
                        </Field>

                        <Field orientation="horizontal">
                            <Switch
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setField('is_active', checked)
                                }
                            />
                            <div className="flex flex-col gap-1">
                                <FieldLabel>Active</FieldLabel>
                                <FieldDescription>
                                    Inactive menus are hidden from the front
                                    end.
                                </FieldDescription>
                            </div>
                        </Field>

                    </CardContent>

                    <CardFooter className="flex justify-end gap-3">
                        <Button variant="outline" type="button" asChild>
                            <Link href={route('cms.appearance.menus.index')}>
                                Cancel
                            </Link>
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing && <Spinner />}
                            Create Menu
                        </Button>
                    </CardFooter>
                </Card>
            </form>
        </AppLayout>
    );
}
