import { useHttp } from '@inertiajs/react';
import { SaveIcon, WifiIcon } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { showAppToast } from '@/components/forms/form-success-toast';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useAppForm } from '@/hooks/use-app-form';
import SettingsLayout from '@/layouts/settings-layout';
import type { BreadcrumbItem, SelectOption, SettingsNavItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Master Settings', href: route('app.masters.settings.index') },
    { title: 'Storage', href: route('app.masters.settings.storage') },
];

type StoragePageProps = {
    settings: {
        storage_driver: string;
        root_folder: string;
        max_storage_size: string;
        storage_cdn_url: string;
        ftp_host: string;
        ftp_username: string;
        ftp_password: string;
        ftp_root: string;
        ftp_port: string;
        ftp_passive: boolean;
        ftp_timeout: string;
        ftp_ssl: boolean;
        ftp_ssl_mode: string;
        access_key: string;
        secret_key: string;
        bucket: string;
        region: string;
        endpoint: string;
        use_path_style_endpoint: boolean;
    };
    secretState: {
        hasFtpPassword: boolean;
        hasAccessKey: boolean;
        hasSecretKey: boolean;
    };
    options: {
        storageDrivers: SelectOption[];
    };
    settingsNav: SettingsNavItem[];
};

type StorageFormData = {
    storage_driver: string;
    root_folder: string;
    max_storage_size: string;
    storage_cdn_url: string;
    ftp_host: string;
    ftp_username: string;
    ftp_password: string;
    clear_ftp_password: boolean;
    ftp_root: string;
    ftp_port: string;
    ftp_passive: boolean;
    ftp_timeout: string;
    ftp_ssl: boolean;
    ftp_ssl_mode: string;
    access_key: string;
    clear_access_key: boolean;
    secret_key: string;
    clear_secret_key: boolean;
    bucket: string;
    region: string;
    endpoint: string;
    use_path_style_endpoint: boolean;
};

export default function Storage({
    settings,
    secretState,
    options,
    settingsNav,
}: StoragePageProps) {
    const [isTesting, setIsTesting] = useState(false);
    const testConnectionRequest = useHttp<
        StorageFormData,
        { success?: boolean; message?: string }
    >({
        clear_ftp_password: false,
        clear_access_key: false,
        clear_secret_key: false,
        ...settings,
    });

    const form = useAppForm<StorageFormData>({
        defaults: {
            storage_driver: settings.storage_driver,
            root_folder: settings.root_folder,
            max_storage_size: settings.max_storage_size,
            storage_cdn_url: settings.storage_cdn_url,
            ftp_host: settings.ftp_host,
            ftp_username: settings.ftp_username,
            ftp_password: settings.ftp_password,
            clear_ftp_password: false,
            ftp_root: settings.ftp_root,
            ftp_port: settings.ftp_port,
            ftp_passive: settings.ftp_passive,
            ftp_timeout: settings.ftp_timeout,
            ftp_ssl: settings.ftp_ssl,
            ftp_ssl_mode: settings.ftp_ssl_mode,
            access_key: settings.access_key,
            clear_access_key: false,
            secret_key: settings.secret_key,
            clear_secret_key: false,
            bucket: settings.bucket,
            region: settings.region,
            endpoint: settings.endpoint,
            use_path_style_endpoint: settings.use_path_style_endpoint,
        },
        dontRemember: ['ftp_password', 'access_key', 'secret_key'],
        rememberKey: 'master-settings.storage',
        dirtyGuard: { enabled: true },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('put', route('app.masters.settings.update', 'storage'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Storage settings updated',
                description:
                    'Your storage settings have been saved successfully.',
            },
        });
    };

    const handleTestConnection = async () => {
        setIsTesting(true);

        try {
            testConnectionRequest.transform(() => ({
                ...form.data,
            }));

            const data = await testConnectionRequest.post(
                route('app.masters.settings.test-storage-connection'),
                {
                    headers: {
                        Accept: 'application/json',
                    },
                },
            );

            showAppToast({
                variant: data.success ? 'success' : 'error',
                title: data.success
                    ? 'Connection successful'
                    : 'Connection failed',
                description: data.message,
            });
        } catch {
            showAppToast({
                variant: 'error',
                title: 'Connection test failed',
                description:
                    'An unexpected error occurred while testing the connection.',
            });
        } finally {
            setIsTesting(false);
        }
    };

    const isFtp = form.data.storage_driver === 'ftp';
    const isS3 = form.data.storage_driver === 's3';

    return (
        <SettingsLayout
            settingsNav={settingsNav}
            breadcrumbs={breadcrumbs}
            title="Master Settings"
            description="Manage platform-level configuration."
        >
            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <form
                    noValidate
                    className="flex flex-col gap-6"
                    onSubmit={handleSubmit}
                >
                    {form.dirtyGuardDialog}
                    <FormErrorSummary errors={form.errors} minMessages={2} />

                    <Card>
                        <CardHeader>
                            <CardTitle>Storage Configuration</CardTitle>
                        </CardHeader>

                        <CardContent>
                            <FieldGroup>
                                <Field
                                    data-invalid={
                                        form.invalid('storage_driver') ||
                                        undefined
                                    }
                                >
                                    <FieldLabel htmlFor="storage_driver">
                                        Storage Driver
                                    </FieldLabel>
                                    <NativeSelect
                                        id="storage_driver"
                                        className="w-full"
                                        size="comfortable"
                                        value={form.data.storage_driver}
                                        onChange={(e) =>
                                            form.setField(
                                                'storage_driver',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            form.touch('storage_driver')
                                        }
                                        aria-invalid={
                                            form.invalid('storage_driver') ||
                                            undefined
                                        }
                                    >
                                        {options.storageDrivers.map((opt) => (
                                            <option
                                                key={opt.value}
                                                value={opt.value}
                                            >
                                                {opt.label}
                                            </option>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.error('storage_driver')}
                                    </FieldError>
                                </Field>

                                <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                    <Field
                                        data-invalid={
                                            form.invalid('root_folder') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="root_folder">
                                            Root Folder
                                        </FieldLabel>
                                        <Input
                                            id="root_folder"
                                            value={form.data.root_folder}
                                            onChange={(e) =>
                                                form.setField(
                                                    'root_folder',
                                                    e.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('root_folder')
                                            }
                                            aria-invalid={
                                                form.invalid('root_folder') ||
                                                undefined
                                            }
                                            placeholder="Enter root folder path"
                                            size="comfortable"
                                        />
                                        <FieldError>
                                            {form.error('root_folder')}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid('max_storage_size') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="max_storage_size">
                                            Max Storage Size (MB)
                                        </FieldLabel>
                                        <Input
                                            id="max_storage_size"
                                            type="number"
                                            value={form.data.max_storage_size}
                                            onChange={(e) =>
                                                form.setField(
                                                    'max_storage_size',
                                                    e.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('max_storage_size')
                                            }
                                            aria-invalid={
                                                form.invalid(
                                                    'max_storage_size',
                                                ) || undefined
                                            }
                                            placeholder="Leave empty for unlimited"
                                            size="comfortable"
                                        />
                                        <FieldError>
                                            {form.error('max_storage_size')}
                                        </FieldError>
                                    </Field>
                                </FieldGroup>

                                <Field
                                    data-invalid={
                                        form.invalid('storage_cdn_url') ||
                                        undefined
                                    }
                                >
                                    <FieldLabel htmlFor="storage_cdn_url">
                                        CDN URL
                                    </FieldLabel>
                                    <Input
                                        id="storage_cdn_url"
                                        value={form.data.storage_cdn_url}
                                        onChange={(e) =>
                                            form.setField(
                                                'storage_cdn_url',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            form.touch('storage_cdn_url')
                                        }
                                        aria-invalid={
                                            form.invalid('storage_cdn_url') ||
                                            undefined
                                        }
                                        placeholder="https://cdn.example.com"
                                        size="comfortable"
                                    />
                                    <FieldError>
                                        {form.error('storage_cdn_url')}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    {isFtp ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>FTP Configuration</CardTitle>
                            </CardHeader>

                            <CardContent>
                                <FieldGroup>
                                    <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                        <Field
                                            data-invalid={
                                                form.invalid('ftp_host') ||
                                                undefined
                                            }
                                        >
                                            <FieldLabel htmlFor="ftp_host">
                                                FTP Host
                                            </FieldLabel>
                                            <Input
                                                id="ftp_host"
                                                value={form.data.ftp_host}
                                                onChange={(e) =>
                                                    form.setField(
                                                        'ftp_host',
                                                        e.target.value,
                                                    )
                                                }
                                                onBlur={() =>
                                                    form.touch('ftp_host')
                                                }
                                                aria-invalid={
                                                    form.invalid('ftp_host') ||
                                                    undefined
                                                }
                                                placeholder="ftp.example.com"
                                                size="comfortable"
                                            />
                                            <FieldError>
                                                {form.error('ftp_host')}
                                            </FieldError>
                                        </Field>

                                        <Field
                                            data-invalid={
                                                form.invalid('ftp_port') ||
                                                undefined
                                            }
                                        >
                                            <FieldLabel htmlFor="ftp_port">
                                                FTP Port
                                            </FieldLabel>
                                            <Input
                                                id="ftp_port"
                                                value={form.data.ftp_port}
                                                onChange={(e) =>
                                                    form.setField(
                                                        'ftp_port',
                                                        e.target.value,
                                                    )
                                                }
                                                onBlur={() =>
                                                    form.touch('ftp_port')
                                                }
                                                aria-invalid={
                                                    form.invalid('ftp_port') ||
                                                    undefined
                                                }
                                                placeholder="21"
                                                size="comfortable"
                                            />
                                            <FieldError>
                                                {form.error('ftp_port')}
                                            </FieldError>
                                        </Field>
                                    </FieldGroup>

                                    <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                        <Field
                                            data-invalid={
                                                form.invalid('ftp_username') ||
                                                undefined
                                            }
                                        >
                                            <FieldLabel htmlFor="ftp_username">
                                                FTP Username
                                            </FieldLabel>
                                            <Input
                                                id="ftp_username"
                                                value={form.data.ftp_username}
                                                onChange={(e) =>
                                                    form.setField(
                                                        'ftp_username',
                                                        e.target.value,
                                                    )
                                                }
                                                onBlur={() =>
                                                    form.touch('ftp_username')
                                                }
                                                aria-invalid={
                                                    form.invalid(
                                                        'ftp_username',
                                                    ) || undefined
                                                }
                                                placeholder="Enter FTP username"
                                                size="comfortable"
                                            />
                                            <FieldError>
                                                {form.error('ftp_username')}
                                            </FieldError>
                                        </Field>

                                        <Field
                                            data-invalid={
                                                form.invalid('ftp_password') ||
                                                undefined
                                            }
                                        >
                                            <FieldLabel htmlFor="ftp_password">
                                                FTP Password
                                            </FieldLabel>
                                            <Input
                                                id="ftp_password"
                                                type="password"
                                                value={form.data.ftp_password}
                                                onChange={(e) => {
                                                    form.setField(
                                                        'ftp_password',
                                                        e.target.value,
                                                    );

                                                    if (
                                                        e.target.value !== ''
                                                    ) {
                                                        form.setField(
                                                            'clear_ftp_password',
                                                            false,
                                                        );
                                                    }
                                                }}
                                                onBlur={() =>
                                                    form.touch('ftp_password')
                                                }
                                                aria-invalid={
                                                    form.invalid(
                                                        'ftp_password',
                                                    ) || undefined
                                                }
                                                placeholder="Enter FTP password"
                                                size="comfortable"
                                            />
                                            {secretState.hasFtpPassword ? (
                                                <div className="mt-3 flex items-start gap-3 rounded-lg border border-dashed border-border px-3 py-3">
                                                    <Checkbox
                                                        id="clear_ftp_password"
                                                        checked={
                                                            form.data
                                                                .clear_ftp_password
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            form.setField(
                                                                'clear_ftp_password',
                                                                checked === true,
                                                            )
                                                        }
                                                    />
                                                    <div className="space-y-1">
                                                        <FieldLabel htmlFor="clear_ftp_password">
                                                            Clear saved FTP
                                                            password on save
                                                        </FieldLabel>
                                                        <FieldDescription>
                                                            Leave the password
                                                            field blank to keep
                                                            the current value,
                                                            or check this to
                                                            remove it.
                                                        </FieldDescription>
                                                    </div>
                                                </div>
                                            ) : null}
                                            <FieldError>
                                                {form.error('ftp_password')}
                                            </FieldError>
                                        </Field>
                                    </FieldGroup>

                                    <Field
                                        data-invalid={
                                            form.invalid('ftp_root') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="ftp_root">
                                            FTP Root Directory
                                        </FieldLabel>
                                        <Input
                                            id="ftp_root"
                                            value={form.data.ftp_root}
                                            onChange={(e) =>
                                                form.setField(
                                                    'ftp_root',
                                                    e.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('ftp_root')
                                            }
                                            aria-invalid={
                                                form.invalid('ftp_root') ||
                                                undefined
                                            }
                                            placeholder="/public_html"
                                            size="comfortable"
                                        />
                                        <FieldError>
                                            {form.error('ftp_root')}
                                        </FieldError>
                                    </Field>

                                    <Field
                                        data-invalid={
                                            form.invalid('ftp_timeout') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="ftp_timeout">
                                            Timeout (seconds)
                                        </FieldLabel>
                                        <Input
                                            id="ftp_timeout"
                                            type="number"
                                            value={form.data.ftp_timeout}
                                            onChange={(e) =>
                                                form.setField(
                                                    'ftp_timeout',
                                                    e.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('ftp_timeout')
                                            }
                                            aria-invalid={
                                                form.invalid('ftp_timeout') ||
                                                undefined
                                            }
                                            placeholder="30"
                                            size="comfortable"
                                        />
                                        <FieldError>
                                            {form.error('ftp_timeout')}
                                        </FieldError>
                                    </Field>

                                    <Field>
                                        <div className="flex items-center justify-between gap-4">
                                            <FieldLabel htmlFor="ftp_passive">
                                                Passive Mode
                                            </FieldLabel>
                                            <Switch
                                                id="ftp_passive"
                                                checked={form.data.ftp_passive}
                                                onCheckedChange={(checked) =>
                                                    form.setField(
                                                        'ftp_passive',
                                                        checked === true,
                                                    )
                                                }
                                                size="comfortable"
                                            />
                                        </div>
                                    </Field>

                                    <Field>
                                        <div className="flex items-center justify-between gap-4">
                                            <FieldLabel htmlFor="ftp_ssl">
                                                SSL
                                            </FieldLabel>
                                            <Switch
                                                id="ftp_ssl"
                                                checked={form.data.ftp_ssl}
                                                onCheckedChange={(checked) =>
                                                    form.setField(
                                                        'ftp_ssl',
                                                        checked === true,
                                                    )
                                                }
                                                size="comfortable"
                                            />
                                        </div>
                                    </Field>

                                    {form.data.ftp_ssl ? (
                                        <Field
                                            data-invalid={
                                                form.invalid('ftp_ssl_mode') ||
                                                undefined
                                            }
                                        >
                                            <FieldLabel htmlFor="ftp_ssl_mode">
                                                SSL Mode
                                            </FieldLabel>
                                            <NativeSelect
                                                id="ftp_ssl_mode"
                                                className="w-full"
                                                size="comfortable"
                                                value={form.data.ftp_ssl_mode}
                                                onChange={(e) =>
                                                    form.setField(
                                                        'ftp_ssl_mode',
                                                        e.target.value,
                                                    )
                                                }
                                                onBlur={() =>
                                                    form.touch('ftp_ssl_mode')
                                                }
                                                aria-invalid={
                                                    form.invalid(
                                                        'ftp_ssl_mode',
                                                    ) || undefined
                                                }
                                            >
                                                <option value="explicit">
                                                    Explicit
                                                </option>
                                                <option value="implicit">
                                                    Implicit
                                                </option>
                                            </NativeSelect>
                                            <FieldError>
                                                {form.error('ftp_ssl_mode')}
                                            </FieldError>
                                        </Field>
                                    ) : null}
                                </FieldGroup>
                            </CardContent>
                        </Card>
                    ) : null}

                    {isS3 ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>S3 Configuration</CardTitle>
                            </CardHeader>

                            <CardContent>
                                <FieldGroup>
                                    <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                        <Field
                                            data-invalid={
                                                form.invalid('access_key') ||
                                                undefined
                                            }
                                        >
                                            <FieldLabel htmlFor="access_key">
                                                Access Key
                                            </FieldLabel>
                                            <Input
                                                id="access_key"
                                                value={form.data.access_key}
                                                onChange={(e) => {
                                                    form.setField(
                                                        'access_key',
                                                        e.target.value,
                                                    );

                                                    if (
                                                        e.target.value !== ''
                                                    ) {
                                                        form.setField(
                                                            'clear_access_key',
                                                            false,
                                                        );
                                                    }
                                                }}
                                                onBlur={() =>
                                                    form.touch('access_key')
                                                }
                                                aria-invalid={
                                                    form.invalid(
                                                        'access_key',
                                                    ) || undefined
                                                }
                                                placeholder="Enter AWS access key"
                                                size="comfortable"
                                            />
                                            {secretState.hasAccessKey ? (
                                                <div className="mt-3 flex items-start gap-3 rounded-lg border border-dashed border-border px-3 py-3">
                                                    <Checkbox
                                                        id="clear_access_key"
                                                        checked={
                                                            form.data
                                                                .clear_access_key
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            form.setField(
                                                                'clear_access_key',
                                                                checked === true,
                                                            )
                                                        }
                                                    />
                                                    <div className="space-y-1">
                                                        <FieldLabel htmlFor="clear_access_key">
                                                            Clear saved access key
                                                            on save
                                                        </FieldLabel>
                                                        <FieldDescription>
                                                            Leave the field blank
                                                            to keep the current
                                                            value, or check this
                                                            to remove it.
                                                        </FieldDescription>
                                                    </div>
                                                </div>
                                            ) : null}
                                            <FieldError>
                                                {form.error('access_key')}
                                            </FieldError>
                                        </Field>

                                        <Field
                                            data-invalid={
                                                form.invalid('secret_key') ||
                                                undefined
                                            }
                                        >
                                            <FieldLabel htmlFor="secret_key">
                                                Secret Key
                                            </FieldLabel>
                                            <Input
                                                id="secret_key"
                                                type="password"
                                                value={form.data.secret_key}
                                                onChange={(e) => {
                                                    form.setField(
                                                        'secret_key',
                                                        e.target.value,
                                                    );

                                                    if (
                                                        e.target.value !== ''
                                                    ) {
                                                        form.setField(
                                                            'clear_secret_key',
                                                            false,
                                                        );
                                                    }
                                                }}
                                                onBlur={() =>
                                                    form.touch('secret_key')
                                                }
                                                aria-invalid={
                                                    form.invalid(
                                                        'secret_key',
                                                    ) || undefined
                                                }
                                                placeholder="Enter AWS secret key"
                                                size="comfortable"
                                            />
                                            {secretState.hasSecretKey ? (
                                                <div className="mt-3 flex items-start gap-3 rounded-lg border border-dashed border-border px-3 py-3">
                                                    <Checkbox
                                                        id="clear_secret_key"
                                                        checked={
                                                            form.data
                                                                .clear_secret_key
                                                        }
                                                        onCheckedChange={(
                                                            checked,
                                                        ) =>
                                                            form.setField(
                                                                'clear_secret_key',
                                                                checked === true,
                                                            )
                                                        }
                                                    />
                                                    <div className="space-y-1">
                                                        <FieldLabel htmlFor="clear_secret_key">
                                                            Clear saved secret key
                                                            on save
                                                        </FieldLabel>
                                                        <FieldDescription>
                                                            Leave the field blank
                                                            to keep the current
                                                            value, or check this
                                                            to remove it.
                                                        </FieldDescription>
                                                    </div>
                                                </div>
                                            ) : null}
                                            <FieldError>
                                                {form.error('secret_key')}
                                            </FieldError>
                                        </Field>
                                    </FieldGroup>

                                    <FieldGroup className="md:grid md:grid-cols-2 md:gap-6">
                                        <Field
                                            data-invalid={
                                                form.invalid('bucket') ||
                                                undefined
                                            }
                                        >
                                            <FieldLabel htmlFor="bucket">
                                                Bucket
                                            </FieldLabel>
                                            <Input
                                                id="bucket"
                                                value={form.data.bucket}
                                                onChange={(e) =>
                                                    form.setField(
                                                        'bucket',
                                                        e.target.value,
                                                    )
                                                }
                                                onBlur={() =>
                                                    form.touch('bucket')
                                                }
                                                aria-invalid={
                                                    form.invalid('bucket') ||
                                                    undefined
                                                }
                                                placeholder="my-bucket"
                                                size="comfortable"
                                            />
                                            <FieldError>
                                                {form.error('bucket')}
                                            </FieldError>
                                        </Field>

                                        <Field
                                            data-invalid={
                                                form.invalid('region') ||
                                                undefined
                                            }
                                        >
                                            <FieldLabel htmlFor="region">
                                                Region
                                            </FieldLabel>
                                            <Input
                                                id="region"
                                                value={form.data.region}
                                                onChange={(e) =>
                                                    form.setField(
                                                        'region',
                                                        e.target.value,
                                                    )
                                                }
                                                onBlur={() =>
                                                    form.touch('region')
                                                }
                                                aria-invalid={
                                                    form.invalid('region') ||
                                                    undefined
                                                }
                                                placeholder="us-east-1"
                                                size="comfortable"
                                            />
                                            <FieldError>
                                                {form.error('region')}
                                            </FieldError>
                                        </Field>
                                    </FieldGroup>

                                    <Field
                                        data-invalid={
                                            form.invalid('endpoint') ||
                                            undefined
                                        }
                                    >
                                        <FieldLabel htmlFor="endpoint">
                                            Custom Endpoint
                                        </FieldLabel>
                                        <FieldDescription>
                                            For S3-compatible services (e.g.,
                                            DigitalOcean Spaces, MinIO).
                                        </FieldDescription>
                                        <Input
                                            id="endpoint"
                                            value={form.data.endpoint}
                                            onChange={(e) =>
                                                form.setField(
                                                    'endpoint',
                                                    e.target.value,
                                                )
                                            }
                                            onBlur={() =>
                                                form.touch('endpoint')
                                            }
                                            aria-invalid={
                                                form.invalid('endpoint') ||
                                                undefined
                                            }
                                            placeholder="https://nyc3.digitaloceanspaces.com"
                                            size="comfortable"
                                        />
                                        <FieldError>
                                            {form.error('endpoint')}
                                        </FieldError>
                                    </Field>

                                    <Field>
                                        <div className="flex items-center justify-between gap-4">
                                            <div className="space-y-1">
                                                <FieldLabel htmlFor="use_path_style_endpoint">
                                                    Use Path Style Endpoint
                                                </FieldLabel>
                                                <FieldDescription>
                                                    Required for some
                                                    S3-compatible services.
                                                </FieldDescription>
                                            </div>
                                            <Switch
                                                id="use_path_style_endpoint"
                                                checked={
                                                    form.data
                                                        .use_path_style_endpoint
                                                }
                                                onCheckedChange={(checked) =>
                                                    form.setField(
                                                        'use_path_style_endpoint',
                                                        checked === true,
                                                    )
                                                }
                                                size="comfortable"
                                            />
                                        </div>
                                    </Field>
                                </FieldGroup>
                            </CardContent>
                        </Card>
                    ) : null}

                    <div className="flex gap-3">
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="flex-1"
                        >
                            {form.processing ? (
                                <Spinner />
                            ) : (
                                <SaveIcon data-icon="inline-start" />
                            )}
                            {form.processing ? 'Saving...' : 'Save Settings'}
                        </Button>

                        <Button
                            type="button"
                            variant="outline"
                            onClick={handleTestConnection}
                            disabled={isTesting}
                        >
                            {isTesting ? (
                                <Spinner />
                            ) : (
                                <WifiIcon data-icon="inline-start" />
                            )}
                            {isTesting ? 'Testing...' : 'Test Connection'}
                        </Button>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    );
}
