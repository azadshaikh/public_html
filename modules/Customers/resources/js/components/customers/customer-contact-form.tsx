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
import { Switch } from '@/components/ui/switch';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type {
    CustomerContactFormValues,
    CustomerOption,
} from '../../types/customers';

type CustomerContactFormProps = {
    mode: 'create' | 'edit';
    contact?: { id: number; name: string };
    initialValues: CustomerContactFormValues;
    statusOptions: CustomerOption[];
    customerOptions: CustomerOption[];
};

export default function CustomerContactForm({
    mode,
    contact,
    initialValues,
    statusOptions,
    customerOptions,
}: CustomerContactFormProps) {
    const form = useAppForm<CustomerContactFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? 'customer-contacts.create'
                : `customer-contacts.edit.${contact?.id}`,
        dirtyGuard: { enabled: true },
        rules: {
            first_name: [formValidators.required('First name')],
            email: [formValidators.required('Email')],
            status: [formValidators.required('Status')],
        },
    });

    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('app.customers.contacts.store')
            : route('app.customers.contacts.update', contact!.id);
    const submitLabel =
        mode === 'create' ? 'Create Contact' : 'Update Contact';

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast: {
                title:
                    mode === 'create'
                        ? 'Contact created'
                        : 'Contact updated',
                description:
                    mode === 'create'
                        ? 'The contact has been created successfully.'
                        : 'The contact has been updated successfully.',
            },
        });
    };

    return (
        <form
            className="flex flex-col gap-6"
            onSubmit={handleSubmit}
            noValidate
        >
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_300px]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Contact Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('customer_id') ||
                                        undefined
                                    }
                                >
                                    <FieldLabel htmlFor="customer_id">
                                        Customer{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <NativeSelect
                                        id="customer_id"
                                        value={form.data.customer_id}
                                        onChange={(e) =>
                                            form.setField(
                                                'customer_id',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <NativeSelectOption value="">
                                            Select customer...
                                        </NativeSelectOption>
                                        {customerOptions.map((o) => (
                                            <NativeSelectOption
                                                key={o.value}
                                                value={o.value}
                                            >
                                                {o.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('customer_id')}
                                    </FieldError>
                                </Field>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        data-invalid={
                                            form.invalid('first_name') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="first_name">
                                            First Name{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </FieldLabel>
                                        <Input
                                            id="first_name"
                                            value={form.data.first_name}
                                            onChange={(e) =>
                                                form.setField(
                                                    'first_name',
                                                    e.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('first_name')
                                            }
                                            placeholder="First name..."
                                        />
                                        <FieldError>
                                            {form.error('first_name')}
                                        </FieldError>
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="last_name">
                                            Last Name
                                        </FieldLabel>
                                        <Input
                                            id="last_name"
                                            value={form.data.last_name}
                                            onChange={(e) =>
                                                form.setField(
                                                    'last_name',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Last name..."
                                        />
                                    </Field>
                                </div>

                                <Field
                                    data-invalid={
                                        form.invalid('email') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="email">
                                        Email{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={form.data.email}
                                        onChange={(e) =>
                                            form.setField(
                                                'email',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() => form.touch('email')}
                                        placeholder="email@example.com"
                                    />
                                    <FieldError>
                                        {form.error('email')}
                                    </FieldError>
                                </Field>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field>
                                        <FieldLabel htmlFor="phone">
                                            Phone
                                        </FieldLabel>
                                        <Input
                                            id="phone"
                                            value={form.data.phone}
                                            onChange={(e) =>
                                                form.setField(
                                                    'phone',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="+1 555 000 0000"
                                        />
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="position">
                                            Position
                                        </FieldLabel>
                                        <Input
                                            id="position"
                                            value={form.data.position}
                                            onChange={(e) =>
                                                form.setField(
                                                    'position',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="e.g. CTO, Manager..."
                                        />
                                    </Field>
                                </div>

                                <div className="flex items-center justify-between">
                                    <FieldLabel htmlFor="is_primary">
                                        Primary contact
                                    </FieldLabel>
                                    <Switch
                                        id="is_primary"
                                        checked={form.data.is_primary}
                                        onCheckedChange={(v) =>
                                            form.setField('is_primary', v)
                                        }
                                    />
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Status</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('status') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="status">
                                        Status{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <NativeSelect
                                        id="status"
                                        value={form.data.status}
                                        onChange={(e) =>
                                            form.setField(
                                                'status',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        {statusOptions.map((o) => (
                                            <NativeSelectOption
                                                key={o.value}
                                                value={o.value}
                                            >
                                                {o.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('status')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="pt-6">
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={form.processing}
                            >
                                {form.processing && (
                                    <Spinner className="mr-2" />
                                )}
                                {submitLabel}
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </form>
    );
}
