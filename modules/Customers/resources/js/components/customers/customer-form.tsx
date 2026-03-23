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
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';
import { formValidators } from '@/lib/forms';
import type {
    CustomerFormValues,
    CustomerOption,
    CustomerShowDetail,
} from '../../types/customers';

type CustomerFormProps = {
    mode: 'create' | 'edit';
    customer?: CustomerShowDetail | { id: number; name: string };
    initialValues: CustomerFormValues;
    typeOptions: CustomerOption[];
    statusOptions: CustomerOption[];
    sourceOptions: CustomerOption[];
    tierOptions: CustomerOption[];
    groupOptions: CustomerOption[];
    industryOptions: CustomerOption[];
    accountManagerOptions: CustomerOption[];
    languageOptions: CustomerOption[];
    orgSizeOptions: CustomerOption[];
    annualRevenueOptions: CustomerOption[];
    userOptions: CustomerOption[];
};

export default function CustomerForm({
    mode,
    customer,
    initialValues,
    typeOptions,
    statusOptions,
    sourceOptions,
    tierOptions,
    groupOptions,
    industryOptions,
    accountManagerOptions,
    languageOptions,
    orgSizeOptions,
    annualRevenueOptions,
    userOptions,
}: CustomerFormProps) {
    const form = useAppForm<CustomerFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? 'customers.create'
                : `customers.edit.${customer?.id}`,
        dontRemember: ['user_password', 'user_password_confirmation'],
        dirtyGuard: { enabled: true },
        rules: {
            email: [formValidators.required('Email')],
            phone: [formValidators.required('Phone')],
            status: [formValidators.required('Status')],
        },
    });

    const isCompany = form.data.type === 'company';
    const hasLinkedUser = initialValues.user_id !== '';
    const selectedUserOption = userOptions.find(
        (option) => option.value === form.data.user_id,
    );
    const showAssociateUser = form.data.user_action === 'associate';
    const showCreateUser = form.data.user_action === 'create';
    const submitMethod = mode === 'create' ? 'post' : 'put';
    const submitUrl =
        mode === 'create'
            ? route('app.customers.store')
            : route('app.customers.update', customer!.id);
    const submitLabel =
        mode === 'create' ? 'Create Customer' : 'Update Customer';

    const handleUserActionChange = (value: string) => {
        form.setField('user_action', value);

        if (value === 'none' || value === 'create') {
            form.setField('user_id', '');
        }

        if (value !== 'create') {
            form.setField('user_password', '');
            form.setField('user_password_confirmation', '');
        }
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(submitMethod, submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast: {
                title:
                    mode === 'create'
                        ? 'Customer created'
                        : 'Customer updated',
                description:
                    mode === 'create'
                        ? 'The customer has been created successfully.'
                        : 'The customer has been updated successfully.',
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
                {/* Main content */}
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Customer Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('type') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="type">
                                        Type{' '}
                                        <span className="text-destructive">
                                            *
                                        </span>
                                    </FieldLabel>
                                    <NativeSelect
                                        id="type"
                                        value={form.data.type}
                                        onChange={(e) =>
                                            form.setField(
                                                'type',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        {typeOptions.map((o) => (
                                            <NativeSelectOption
                                                key={o.value}
                                                value={o.value}
                                            >
                                                {o.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('type')}
                                    </FieldError>
                                </Field>

                                {isCompany && (
                                    <Field
                                        data-invalid={
                                            form.invalid('company_name') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="company_name">
                                            Company Name{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </FieldLabel>
                                        <Input
                                            id="company_name"
                                            value={form.data.company_name}
                                            onChange={(e) =>
                                                form.setField(
                                                    'company_name',
                                                    e.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('company_name')
                                            }
                                            placeholder="Enter company name..."
                                        />
                                        <FieldError>
                                            {form.error('company_name')}
                                        </FieldError>
                                    </Field>
                                )}

                                {!isCompany && (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            data-invalid={
                                                form.invalid(
                                                    'contact_first_name',
                                                ) || undefined
                                            }
                                        >
                                            <FieldLabel htmlFor="contact_first_name">
                                                First Name{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </FieldLabel>
                                            <Input
                                                id="contact_first_name"
                                                value={
                                                    form.data
                                                        .contact_first_name
                                                }
                                                onChange={(e) =>
                                                    form.setField(
                                                        'contact_first_name',
                                                        e.target.value,
                                                    )
                                                }
                                                onBlur={() =>
                                                    form.touch(
                                                        'contact_first_name',
                                                    )
                                                }
                                                placeholder="First name..."
                                            />
                                            <FieldError>
                                                {form.error(
                                                    'contact_first_name',
                                                )}
                                            </FieldError>
                                        </Field>
                                        <Field>
                                            <FieldLabel htmlFor="contact_last_name">
                                                Last Name
                                            </FieldLabel>
                                            <Input
                                                id="contact_last_name"
                                                value={
                                                    form.data.contact_last_name
                                                }
                                                onChange={(e) =>
                                                    form.setField(
                                                        'contact_last_name',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Last name..."
                                            />
                                        </Field>
                                    </div>
                                )}

                                <div className="grid gap-4 sm:grid-cols-2">
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
                                    <Field
                                        data-invalid={
                                            form.invalid('phone') || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="phone">
                                            Phone{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
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
                                            onBlur={() => form.touch('phone')}
                                            placeholder="+1 555 000 0000"
                                        />
                                        <FieldError>
                                            {form.error('phone')}
                                        </FieldError>
                                    </Field>
                                </div>

                                <Field>
                                    <FieldLabel htmlFor="description">
                                        Description
                                    </FieldLabel>
                                    <Textarea
                                        id="description"
                                        rows={3}
                                        value={form.data.description}
                                        onChange={(e) =>
                                            form.setField(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Notes about this customer..."
                                    />
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Business Details</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field>
                                        <FieldLabel htmlFor="industry">
                                            Industry
                                        </FieldLabel>
                                        <NativeSelect
                                            id="industry"
                                            value={form.data.industry}
                                            onChange={(e) =>
                                                form.setField(
                                                    'industry',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <NativeSelectOption value="">
                                                Select industry...
                                            </NativeSelectOption>
                                            {industryOptions.map((o) => (
                                                <NativeSelectOption
                                                    key={o.value}
                                                    value={o.value}
                                                >
                                                    {o.label}
                                                </NativeSelectOption>
                                            ))}
                                        </NativeSelect>
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="customer_group">
                                            Group
                                        </FieldLabel>
                                        <NativeSelect
                                            id="customer_group"
                                            value={form.data.customer_group}
                                            onChange={(e) =>
                                                form.setField(
                                                    'customer_group',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <NativeSelectOption value="">
                                                Select group...
                                            </NativeSelectOption>
                                            {groupOptions.map((o) => (
                                                <NativeSelectOption
                                                    key={o.value}
                                                    value={o.value}
                                                >
                                                    {o.label}
                                                </NativeSelectOption>
                                            ))}
                                        </NativeSelect>
                                    </Field>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field>
                                        <FieldLabel htmlFor="org_size">
                                            Organization Size
                                        </FieldLabel>
                                        <NativeSelect
                                            id="org_size"
                                            value={form.data.org_size}
                                            onChange={(e) =>
                                                form.setField(
                                                    'org_size',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <NativeSelectOption value="">
                                                Select size...
                                            </NativeSelectOption>
                                            {orgSizeOptions.map((o) => (
                                                <NativeSelectOption
                                                    key={o.value}
                                                    value={o.value}
                                                >
                                                    {o.label}
                                                </NativeSelectOption>
                                            ))}
                                        </NativeSelect>
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="revenue">
                                            Annual Revenue
                                        </FieldLabel>
                                        <NativeSelect
                                            id="revenue"
                                            value={form.data.revenue}
                                            onChange={(e) =>
                                                form.setField(
                                                    'revenue',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <NativeSelectOption value="">
                                                Select revenue range...
                                            </NativeSelectOption>
                                            {annualRevenueOptions.map((o) => (
                                                <NativeSelectOption
                                                    key={o.value}
                                                    value={o.value}
                                                >
                                                    {o.label}
                                                </NativeSelectOption>
                                            ))}
                                        </NativeSelect>
                                    </Field>
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field>
                                        <FieldLabel htmlFor="website">
                                            Website
                                        </FieldLabel>
                                        <Input
                                            id="website"
                                            type="url"
                                            value={form.data.website}
                                            onChange={(e) =>
                                                form.setField(
                                                    'website',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="https://..."
                                        />
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="tax_id">
                                            Tax ID
                                        </FieldLabel>
                                        <Input
                                            id="tax_id"
                                            value={form.data.tax_id}
                                            onChange={(e) =>
                                                form.setField(
                                                    'tax_id',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="Tax identification number..."
                                        />
                                    </Field>
                                </div>

                                <Field>
                                    <FieldLabel htmlFor="tags">Tags</FieldLabel>
                                    <Input
                                        id="tags"
                                        value={form.data.tags}
                                        onChange={(e) =>
                                            form.setField(
                                                'tags',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Comma-separated tags..."
                                    />
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Billing Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field>
                                        <FieldLabel htmlFor="billing_email">
                                            Billing Email
                                        </FieldLabel>
                                        <Input
                                            id="billing_email"
                                            type="email"
                                            value={form.data.billing_email}
                                            onChange={(e) =>
                                                form.setField(
                                                    'billing_email',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="billing@example.com"
                                        />
                                    </Field>
                                    <Field>
                                        <FieldLabel htmlFor="billing_phone">
                                            Billing Phone
                                        </FieldLabel>
                                        <Input
                                            id="billing_phone"
                                            value={form.data.billing_phone}
                                            onChange={(e) =>
                                                form.setField(
                                                    'billing_phone',
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="+1 555 000 0000"
                                        />
                                    </Field>
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Preferences</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="flex flex-col gap-4">
                                    <div className="flex items-center justify-between">
                                        <FieldLabel htmlFor="opt_in_marketing">
                                            Marketing opt-in
                                        </FieldLabel>
                                        <Switch
                                            id="opt_in_marketing"
                                            checked={
                                                form.data.opt_in_marketing
                                            }
                                            onCheckedChange={(v) =>
                                                form.setField(
                                                    'opt_in_marketing',
                                                    v,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <FieldLabel htmlFor="do_not_call">
                                            Do not call
                                        </FieldLabel>
                                        <Switch
                                            id="do_not_call"
                                            checked={form.data.do_not_call}
                                            onCheckedChange={(v) =>
                                                form.setField(
                                                    'do_not_call',
                                                    v,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <FieldLabel htmlFor="do_not_email">
                                            Do not email
                                        </FieldLabel>
                                        <Switch
                                            id="do_not_email"
                                            checked={form.data.do_not_email}
                                            onCheckedChange={(v) =>
                                                form.setField(
                                                    'do_not_email',
                                                    v,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                                <Field>
                                    <FieldLabel htmlFor="next_action_date">
                                        Next Action Date
                                    </FieldLabel>
                                    <Input
                                        id="next_action_date"
                                        type="date"
                                        value={form.data.next_action_date}
                                        onChange={(e) =>
                                            form.setField(
                                                'next_action_date',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                {/* Sidebar */}
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Classification</CardTitle>
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

                                <Field>
                                    <FieldLabel htmlFor="tier">Tier</FieldLabel>
                                    <NativeSelect
                                        id="tier"
                                        value={form.data.tier}
                                        onChange={(e) =>
                                            form.setField(
                                                'tier',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <NativeSelectOption value="">
                                            Select tier...
                                        </NativeSelectOption>
                                        {tierOptions.map((o) => (
                                            <NativeSelectOption
                                                key={o.value}
                                                value={o.value}
                                            >
                                                {o.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                </Field>

                                <Field>
                                    <FieldLabel htmlFor="source">
                                        Source
                                    </FieldLabel>
                                    <NativeSelect
                                        id="source"
                                        value={form.data.source}
                                        onChange={(e) =>
                                            form.setField(
                                                'source',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <NativeSelectOption value="">
                                            Select source...
                                        </NativeSelectOption>
                                        {sourceOptions.map((o) => (
                                            <NativeSelectOption
                                                key={o.value}
                                                value={o.value}
                                            >
                                                {o.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                </Field>

                                <Field>
                                    <FieldLabel htmlFor="account_manager_id">
                                        Account Manager
                                    </FieldLabel>
                                    <NativeSelect
                                        id="account_manager_id"
                                        value={form.data.account_manager_id}
                                        onChange={(e) =>
                                            form.setField(
                                                'account_manager_id',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <NativeSelectOption value="">
                                            Select manager...
                                        </NativeSelectOption>
                                        {accountManagerOptions.map((o) => (
                                            <NativeSelectOption
                                                key={o.value}
                                                value={o.value}
                                            >
                                                {o.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                </Field>

                                <Field>
                                    <FieldLabel htmlFor="language">
                                        Language
                                    </FieldLabel>
                                    <NativeSelect
                                        id="language"
                                        value={form.data.language}
                                        onChange={(e) =>
                                            form.setField(
                                                'language',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <NativeSelectOption value="">
                                            Select language...
                                        </NativeSelectOption>
                                        {languageOptions.map((o) => (
                                            <NativeSelectOption
                                                key={o.value}
                                                value={o.value}
                                            >
                                                {o.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>User Account</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('user_action') || undefined
                                    }
                                >
                                    <FieldLabel htmlFor="user_action">
                                        Account Action
                                    </FieldLabel>
                                    <NativeSelect
                                        id="user_action"
                                        value={form.data.user_action}
                                        onChange={(e) =>
                                            handleUserActionChange(
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <NativeSelectOption value="none">
                                            No linked user account
                                        </NativeSelectOption>
                                        {hasLinkedUser && (
                                            <NativeSelectOption value="keep">
                                                Keep linked user account
                                            </NativeSelectOption>
                                        )}
                                        <NativeSelectOption value="associate">
                                            Link an existing user
                                        </NativeSelectOption>
                                        <NativeSelectOption value="create">
                                            Create a new user account
                                        </NativeSelectOption>
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('user_action')}
                                    </FieldError>
                                </Field>

                                {form.data.user_action === 'keep' &&
                                    hasLinkedUser && (
                                        <div className="rounded-lg border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                                            Linked to{' '}
                                            <span className="font-medium text-foreground">
                                                {selectedUserOption?.label ??
                                                    `User #${form.data.user_id}`}
                                            </span>
                                        </div>
                                    )}

                                {showAssociateUser && (
                                    <Field
                                        data-invalid={
                                            form.invalid('user_id') || undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="user_id">
                                            Existing User
                                        </FieldLabel>
                                        <NativeSelect
                                            id="user_id"
                                            value={form.data.user_id}
                                            onChange={(e) =>
                                                form.setField(
                                                    'user_id',
                                                    e.target.value,
                                                )
                                            }
                                            onBlur={() => form.touch('user_id')}
                                        >
                                            <NativeSelectOption value="">
                                                Select a user...
                                            </NativeSelectOption>
                                            {userOptions.map((option) => (
                                                <NativeSelectOption
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </NativeSelectOption>
                                            ))}
                                        </NativeSelect>
                                        <FieldError>
                                            {form.error('user_id')}
                                        </FieldError>
                                    </Field>
                                )}

                                {showCreateUser && (
                                    <>
                                        <Field
                                            data-invalid={
                                                form.invalid('user_password') ||
                                                undefined
                                            }
                                        >
                                            <FieldLabel htmlFor="user_password">
                                                Password
                                            </FieldLabel>
                                            <Input
                                                id="user_password"
                                                type="password"
                                                value={form.data.user_password}
                                                onChange={(e) =>
                                                    form.setField(
                                                        'user_password',
                                                        e.target.value,
                                                    )
                                                }
                                                onBlur={() =>
                                                    form.touch(
                                                        'user_password',
                                                    )
                                                }
                                                placeholder="Leave blank to auto-generate"
                                            />
                                            <FieldError>
                                                {form.error('user_password')}
                                            </FieldError>
                                        </Field>

                                        <Field
                                            data-invalid={
                                                form.invalid(
                                                    'user_password_confirmation',
                                                ) || undefined
                                            }
                                        >
                                            <FieldLabel htmlFor="user_password_confirmation">
                                                Confirm Password
                                            </FieldLabel>
                                            <Input
                                                id="user_password_confirmation"
                                                type="password"
                                                value={
                                                    form.data
                                                        .user_password_confirmation
                                                }
                                                onChange={(e) =>
                                                    form.setField(
                                                        'user_password_confirmation',
                                                        e.target.value,
                                                    )
                                                }
                                                onBlur={() =>
                                                    form.touch(
                                                        'user_password_confirmation',
                                                    )
                                                }
                                                placeholder="Repeat the password"
                                            />
                                            <FieldError>
                                                {form.error(
                                                    'user_password_confirmation',
                                                )}
                                            </FieldError>
                                        </Field>
                                    </>
                                )}
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
