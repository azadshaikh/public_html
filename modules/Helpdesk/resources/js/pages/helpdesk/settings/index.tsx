import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, SettingsIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { useAppForm } from '@/hooks/use-app-form';
import AppLayout from '@/layouts/app-layout';
import { formValidators } from '@/lib/forms';
import type { BreadcrumbItem } from '@/types';
import type {
    HelpdeskSettingsPageProps,
    HelpdeskSettingsValues,
} from '../../../types/helpdesk';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Helpdesk Settings', href: route('helpdesk.settings.index') },
];

export default function HelpdeskSettings({
    initialValues,
    ticket_length_options,
}: HelpdeskSettingsPageProps) {
    const form = useAppForm<HelpdeskSettingsValues>({
        rememberKey: 'helpdesk-settings',
        defaults: initialValues,
        dirtyGuard: { enabled: true },
        rules: {
            ticket_prefix: [formValidators.required('Ticket Prefix')],
            ticket_serial_number: [
                formValidators.required('Serial Number'),
            ],
            ticket_digit_length: [
                formValidators.required('Digit Length'),
            ],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('put', route('helpdesk.settings.update'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Settings updated',
                description:
                    'Helpdesk settings have been saved successfully.',
            },
        });
    };

    const previewNumber = `${form.data.ticket_prefix}${String(
        form.data.ticket_serial_number || '1',
    ).padStart(Number(form.data.ticket_digit_length) || 4, '0')}`;

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Helpdesk Settings"
            description="Configure ticket numbering and other settings"
            headerActions={
                <Button variant="outline" asChild>
                    <Link href={route('helpdesk.tickets.index')}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to Tickets
                    </Link>
                </Button>
            }
        >
            <form
                className="flex flex-col gap-6"
                onSubmit={handleSubmit}
                noValidate
            >
                {form.dirtyGuardDialog}
                <FormErrorSummary errors={form.errors} minMessages={2} />

                <div className="mx-auto w-full max-w-3xl space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <SettingsIcon data-icon="inline-start" />
                                Ticket Numbering
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-6">
                            <FieldGroup className="md:grid-cols-2">
                                <Field
                                    data-invalid={
                                        form.invalid('ticket_prefix') ||
                                        undefined
                                    }
                                >
                                    <FieldLabel htmlFor="ticket_prefix">
                                        Ticket Prefix{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <Input
                                        id="ticket_prefix"
                                        value={form.data.ticket_prefix}
                                        onChange={(e) =>
                                            form.setField(
                                                'ticket_prefix',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            form.touch('ticket_prefix')
                                        }
                                        aria-invalid={
                                            form.invalid('ticket_prefix') ||
                                            undefined
                                        }
                                        placeholder="e.g. TKT-"
                                    />
                                    <FieldError>
                                        {form.error('ticket_prefix')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid(
                                            'ticket_serial_number',
                                        ) || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="ticket_serial_number">
                                        Next Serial Number{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <Input
                                        id="ticket_serial_number"
                                        type="number"
                                        min="1"
                                        value={
                                            form.data.ticket_serial_number
                                        }
                                        onChange={(e) =>
                                            form.setField(
                                                'ticket_serial_number',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            form.touch(
                                                'ticket_serial_number',
                                            )
                                        }
                                        aria-invalid={
                                            form.invalid(
                                                'ticket_serial_number',
                                            ) || undefined
                                        }
                                    />
                                    <FieldError>
                                        {form.error('ticket_serial_number')}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={
                                        form.invalid(
                                            'ticket_digit_length',
                                        ) || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="ticket_digit_length">
                                        Digit Length{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <NativeSelect
                                        id="ticket_digit_length"
                                        value={
                                            form.data.ticket_digit_length
                                        }
                                        onChange={(e) =>
                                            form.setField(
                                                'ticket_digit_length',
                                                e.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            form.invalid(
                                                'ticket_digit_length',
                                            ) || undefined
                                        }
                                    >
                                        {ticket_length_options.map(
                                            (option) => (
                                                <NativeSelectOption
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </NativeSelectOption>
                                            ),
                                        )}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('ticket_digit_length')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>

                            <div className="rounded-lg border bg-muted/30 p-4 sm:p-5">
                                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Preview
                                </span>
                                <div className="mt-2 font-mono text-lg font-bold text-foreground sm:text-2xl">
                                    {previewNumber}
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Button
                        type="submit"
                        className="w-full"
                        disabled={form.processing}
                    >
                        {form.processing ? (
                            <Spinner className="mr-2" />
                        ) : null}
                        Save Settings
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}
