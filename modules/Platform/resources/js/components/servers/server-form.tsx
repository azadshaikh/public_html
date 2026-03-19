import { Link } from '@inertiajs/react';
import {
    CheckCircle2Icon,
    ClipboardCopyIcon,
    KeyRoundIcon,
    LoaderCircleIcon,
    RefreshCcwIcon,
    SaveIcon,
    ShieldCheckIcon,
    XCircleIcon,
} from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';

import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { CitySelect } from '@/components/geo/city-select';
import { CountrySelect } from '@/components/geo/country-select';
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
import { Input } from '@/components/ui/input';
import { NativeSelect, NativeSelectOption } from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useAppForm } from '@/hooks/use-app-form';

import type { PlatformOption, ServerFormValues } from '../../types/platform';
import { ServerTypeProviderFields } from './server-type-provider-fields';

type ServerFormProps = {
    server: {
        id: number;
        name: string;
        provisioning_status?: string | null;
        has_ssh_credentials?: boolean;
        has_ssh_private_key?: boolean;
    };
    initialValues: ServerFormValues;
    typeOptions: PlatformOption[];
    providerOptions: PlatformOption[];
    statusOptions: PlatformOption[];
    sshCommand?: string | null;
};

type SshConnectionState = {
    status: 'idle' | 'loading' | 'success' | 'error';
    message: string;
    osInfo?: string | null;
};

const INITIAL_SSH_CONNECTION_STATE: SshConnectionState = {
    status: 'idle',
    message: 'Not tested yet.',
};

async function parseJson<T>(response: Response, fallbackMessage: string): Promise<T> {
    const payload = (await response.json()) as Record<string, unknown>;

    if (!response.ok) {
        const validationErrors = payload.errors as Record<string, string[]> | undefined;
        const firstValidationError = validationErrors
            ? Object.values(validationErrors)[0]?.[0]
            : null;

        throw new Error(
            typeof payload.message === 'string'
                ? payload.message
                : firstValidationError || fallbackMessage,
        );
    }

    return payload as T;
}

export default function ServerForm({
    server,
    initialValues,
    typeOptions,
    providerOptions,
    statusOptions,
    sshCommand,
}: ServerFormProps) {
    const form = useAppForm<ServerFormValues>({
        defaults: initialValues,
        rememberKey: `platform.servers.edit.v2.${server.id}`,
        dirtyGuard: true,
    });
    const [currentSshCommand, setCurrentSshCommand] = useState(sshCommand ?? '');
    const [copiedCommand, setCopiedCommand] = useState(false);
    const [generatingKeys, setGeneratingKeys] = useState(false);
    const [testingConnection, setTestingConnection] = useState(false);
    const [sshNotice, setSshNotice] = useState<{ tone: 'success' | 'error'; text: string } | null>(null);
    const [sshConnectionState, setSshConnectionState] = useState<SshConnectionState>(INITIAL_SSH_CONNECTION_STATE);

    const hasDraftSshChanges = useMemo(
        () =>
            form.data.ip !== initialValues.ip
            || form.data.ssh_port !== initialValues.ssh_port
            || form.data.ssh_user !== initialValues.ssh_user
            || form.data.ssh_private_key.trim() !== '',
        [
            form.data.ip,
            form.data.ssh_port,
            form.data.ssh_user,
            form.data.ssh_private_key,
            initialValues.ip,
            initialValues.ssh_port,
            initialValues.ssh_user,
        ],
    );

    useEffect(() => {
        setSshConnectionState(INITIAL_SSH_CONNECTION_STATE);
    }, [form.data.ip, form.data.ssh_port, form.data.ssh_user, form.data.ssh_private_key]);

    useEffect(() => {
        if (!server.has_ssh_private_key || form.data.ssh_private_key.trim() !== '') {
            return;
        }

        form.clearErrors('ssh_private_key', 'ssh_public_key');
    }, [form, server.has_ssh_private_key, form.data.ssh_private_key]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('put', route('platform.servers.update', server.id), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: 'Server updated successfully.',
        });
    };

    const handleCopyAuthorizeCommand = async () => {
        if (!currentSshCommand.trim()) {
            return;
        }

        await navigator.clipboard.writeText(currentSshCommand);
        setCopiedCommand(true);

        window.setTimeout(() => {
            setCopiedCommand(false);
        }, 1200);
    };

    const handleGenerateKeyPair = async () => {
        setGeneratingKeys(true);
        setSshNotice(null);

        try {
            const response = await fetch(route('platform.servers.generate-ssh-key'), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({}),
            });
            const payload = await parseJson<{
                public_key: string;
                private_key: string;
                command: string;
            }>(response, 'Failed to generate SSH key pair.');

            form.setField('ssh_public_key', payload.public_key);
            form.setField('ssh_private_key', payload.private_key);
            setCurrentSshCommand(payload.command);
            setSshNotice({
                tone: 'success',
                text: 'New SSH key pair generated. Save the server to persist it.',
            });
        } catch (error) {
            setSshNotice({
                tone: 'error',
                text: error instanceof Error ? error.message : 'Failed to generate SSH key pair.',
            });
        } finally {
            setGeneratingKeys(false);
        }
    };

    const handleTestConnection = async () => {
        setTestingConnection(true);

        try {
            const payload = hasDraftSshChanges
                ? {
                    ip: form.data.ip,
                    ssh_port: form.data.ssh_port || '22',
                    ssh_user: form.data.ssh_user || 'root',
                    ssh_private_key: form.data.ssh_private_key,
                }
                : {};

            const response = await fetch(route('platform.servers.test-connection', { server: server.id }), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(payload),
            });

            const result = await parseJson<{
                status: string;
                message: string;
                data?: { os_info?: string | null };
            }>(response, 'Unable to test SSH connection.');

            if (result.status !== 'success') {
                throw new Error(result.message || 'Unable to test SSH connection.');
            }

            setSshConnectionState({
                status: 'success',
                message: result.message,
                osInfo: result.data?.os_info ?? null,
            });
        } catch (error) {
            setSshConnectionState({
                status: 'error',
                message: error instanceof Error ? error.message : 'Unable to test SSH connection.',
            });
        } finally {
            setTestingConnection(false);
        }
    };

    return (
        <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Server Information</CardTitle>
                            <CardDescription>
                                Update the primary endpoint details for this HestiaCP host.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('name') || undefined}>
                                        <FieldLabel htmlFor="name">Server name</FieldLabel>
                                        <Input
                                            id="name"
                                            value={form.data.name}
                                            onChange={(event) =>
                                                form.setField('name', event.target.value)
                                            }
                                            onBlur={() => form.touch('name')}
                                            aria-invalid={form.invalid('name') || undefined}
                                        />
                                        <FieldError>{form.error('name')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('ip') || undefined}>
                                        <FieldLabel htmlFor="ip">IP address</FieldLabel>
                                        <Input
                                            id="ip"
                                            value={form.data.ip}
                                            onChange={(event) =>
                                                form.setField('ip', event.target.value)
                                            }
                                            onBlur={() => form.touch('ip')}
                                            aria-invalid={form.invalid('ip') || undefined}
                                        />
                                        <FieldError>{form.error('ip')}</FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('port') || undefined}>
                                        <FieldLabel htmlFor="port">Port</FieldLabel>
                                        <Input
                                            id="port"
                                            type="number"
                                            min={1}
                                            max={65535}
                                            value={form.data.port}
                                            onChange={(event) =>
                                                form.setField('port', event.target.value)
                                            }
                                            onBlur={() => form.touch('port')}
                                            aria-invalid={form.invalid('port') || undefined}
                                            placeholder="8443"
                                        />
                                        <FieldError>{form.error('port')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('fqdn') || undefined}>
                                        <FieldLabel htmlFor="fqdn">Server FQDN</FieldLabel>
                                        <Input
                                            id="fqdn"
                                            value={form.data.fqdn}
                                            onChange={(event) =>
                                                form.setField('fqdn', event.target.value)
                                            }
                                            onBlur={() => form.touch('fqdn')}
                                            aria-invalid={form.invalid('fqdn') || undefined}
                                            placeholder="server.example.com"
                                        />
                                        <FieldError>{form.error('fqdn')}</FieldError>
                                    </Field>
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Authentication</CardTitle>
                            <CardDescription>
                                Use the Hestia access keys that authorize Astero to manage this server.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('access_key_id') || undefined}>
                                        <FieldLabel htmlFor="access_key_id">Access key ID</FieldLabel>
                                        <Input
                                            id="access_key_id"
                                            value={form.data.access_key_id}
                                            onChange={(event) =>
                                                form.setField('access_key_id', event.target.value)
                                            }
                                            onBlur={() => form.touch('access_key_id')}
                                            aria-invalid={form.invalid('access_key_id') || undefined}
                                            placeholder="20-character Access Key ID"
                                        />
                                        <FieldDescription>
                                            Generated in Hestia Control Panel under Access Keys.
                                        </FieldDescription>
                                        <FieldError>{form.error('access_key_id')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('access_key_secret') || undefined}>
                                        <FieldLabel htmlFor="access_key_secret">Secret key</FieldLabel>
                                        <Input
                                            id="access_key_secret"
                                            type="password"
                                            value={form.data.access_key_secret}
                                            onChange={(event) =>
                                                form.setField('access_key_secret', event.target.value)
                                            }
                                            onBlur={() => form.touch('access_key_secret')}
                                            aria-invalid={form.invalid('access_key_secret') || undefined}
                                            placeholder="40-character Secret Key"
                                        />
                                        <FieldDescription>
                                            Leave blank to keep the current secret key.
                                        </FieldDescription>
                                        <FieldError>{form.error('access_key_secret')}</FieldError>
                                    </Field>
                                </div>

                                <Field data-invalid={form.invalid('release_api_key') || undefined}>
                                    <FieldLabel htmlFor="release_api_key">Release API key</FieldLabel>
                                    <Input
                                        id="release_api_key"
                                        type="password"
                                        value={form.data.release_api_key}
                                        onChange={(event) =>
                                            form.setField('release_api_key', event.target.value)
                                        }
                                        onBlur={() => form.touch('release_api_key')}
                                        aria-invalid={form.invalid('release_api_key') || undefined}
                                        placeholder="X-Release-Key used by a-sync-releases"
                                    />
                                    <FieldDescription>
                                        Leave blank to keep the current release key.
                                    </FieldDescription>
                                    <FieldError>{form.error('release_api_key')}</FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>SSH credentials</CardTitle>
                            <CardDescription>
                                Used for provisioning, release synchronization, and diagnostics. The SSH key must have root access to the server.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="flex flex-col gap-4">
                                <div className="rounded-2xl border border-sky-500/20 bg-sky-500/5 p-4 text-sm leading-6 text-muted-foreground">
                                    SSH credentials enable automatic server provisioning and script updates.
                                </div>

                                <div className="flex flex-wrap items-center gap-3">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={handleGenerateKeyPair}
                                        disabled={generatingKeys}
                                    >
                                        {generatingKeys ? (
                                            <LoaderCircleIcon data-icon="inline-start" className="animate-spin" />
                                        ) : (
                                            <RefreshCcwIcon data-icon="inline-start" />
                                        )}
                                        Generate New Key Pair
                                    </Button>

                                    {currentSshCommand ? (
                                        <Button type="button" variant="outline" onClick={handleCopyAuthorizeCommand}>
                                            <ClipboardCopyIcon data-icon="inline-start" />
                                            {copiedCommand ? 'Copied' : 'Copy authorize command'}
                                        </Button>
                                    ) : null}
                                </div>

                                {sshNotice ? (
                                    <div className={sshNotice.tone === 'success'
                                        ? 'rounded-2xl border border-emerald-500/30 bg-emerald-500/5 p-4 text-sm text-emerald-700'
                                        : 'rounded-2xl border border-destructive/30 bg-destructive/5 p-4 text-sm text-destructive'}>
                                        {sshNotice.text}
                                    </div>
                                ) : null}

                                {currentSshCommand ? (
                                    <FieldGroup>
                                        <Field>
                                            <FieldLabel htmlFor="ssh_authorize_command">
                                                Authorize Command (run on server)
                                            </FieldLabel>
                                            <Textarea
                                                id="ssh_authorize_command"
                                                value={currentSshCommand}
                                                readOnly
                                                rows={3}
                                                className="font-mono text-xs"
                                            />
                                            <FieldDescription>
                                                Run this once on your server as root to authorize the generated public key.
                                            </FieldDescription>
                                        </Field>
                                    </FieldGroup>
                                ) : null}

                                <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('ssh_port') || undefined}>
                                        <FieldLabel htmlFor="ssh_port">SSH port</FieldLabel>
                                        <Input
                                            id="ssh_port"
                                            type="number"
                                            min={1}
                                            max={65535}
                                            value={form.data.ssh_port}
                                            onChange={(event) =>
                                                form.setField('ssh_port', event.target.value)
                                            }
                                            onBlur={() => form.touch('ssh_port')}
                                            aria-invalid={form.invalid('ssh_port') || undefined}
                                        />
                                        <FieldError>{form.error('ssh_port')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('ssh_user') || undefined}>
                                        <FieldLabel htmlFor="ssh_user">SSH user</FieldLabel>
                                        <Input
                                            id="ssh_user"
                                            value={form.data.ssh_user}
                                            onChange={(event) =>
                                                form.setField('ssh_user', event.target.value)
                                            }
                                            onBlur={() => form.touch('ssh_user')}
                                            aria-invalid={form.invalid('ssh_user') || undefined}
                                        />
                                        <FieldError>{form.error('ssh_user')}</FieldError>
                                    </Field>
                                </div>

                                    <Field data-invalid={form.invalid('ssh_public_key') || undefined}>
                                        <FieldLabel htmlFor="ssh_public_key">SSH public key</FieldLabel>
                                        <Textarea
                                        id="ssh_public_key"
                                        value={form.data.ssh_public_key}
                                        onChange={(event) =>
                                            form.setField('ssh_public_key', event.target.value)
                                        }
                                        onBlur={() => form.touch('ssh_public_key')}
                                            aria-invalid={form.invalid('ssh_public_key') || undefined}
                                            rows={3}
                                            className="font-mono text-xs"
                                            placeholder="ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAA..."
                                        />
                                        <FieldDescription>
                                            Auto-filled when you generate a key pair, or paste an existing public key.
                                        </FieldDescription>
                                        <FieldError>{form.error('ssh_public_key')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('ssh_private_key') || undefined}>
                                        <FieldLabel htmlFor="ssh_private_key">SSH private key</FieldLabel>
                                        <Textarea
                                        id="ssh_private_key"
                                        value={form.data.ssh_private_key}
                                        onChange={(event) =>
                                            form.setField('ssh_private_key', event.target.value)
                                        }
                                        onBlur={() => form.touch('ssh_private_key')}
                                            aria-invalid={form.invalid('ssh_private_key') || undefined}
                                            rows={6}
                                            className="font-mono text-xs"
                                            placeholder={'-----BEGIN OPENSSH PRIVATE KEY-----\n...\n-----END OPENSSH PRIVATE KEY-----'}
                                        />
                                        <FieldDescription>
                                            Paste your private key in OpenSSH or PEM format. Leave blank to keep the current key.
                                        </FieldDescription>
                                        <FieldError>{form.error('ssh_private_key')}</FieldError>
                                    </Field>
                                </FieldGroup>

                                <div className="flex flex-col gap-4 rounded-2xl border border-border/70 bg-muted/20 p-4">
                                    <div className="flex items-center gap-3 text-sm font-medium">
                                        {sshConnectionState.status === 'success' ? (
                                            <CheckCircle2Icon className="size-4 text-emerald-600" />
                                        ) : null}
                                        {sshConnectionState.status === 'error' ? (
                                            <XCircleIcon className="size-4 text-destructive" />
                                        ) : null}
                                        {sshConnectionState.status === 'loading' ? (
                                            <LoaderCircleIcon className="size-4 animate-spin text-primary" />
                                        ) : null}
                                        {sshConnectionState.status === 'idle' ? (
                                            <KeyRoundIcon className="size-4 text-muted-foreground" />
                                        ) : null}
                                        <span>
                                            {sshConnectionState.status === 'success'
                                                ? 'SSH connection verified'
                                                : sshConnectionState.status === 'error'
                                                  ? 'SSH connection failed'
                                                  : sshConnectionState.status === 'loading'
                                                    ? 'Testing SSH connection'
                                                    : 'SSH connection not tested'}
                                        </span>
                                    </div>
                                    <p className="text-sm leading-6 text-muted-foreground">
                                        {sshConnectionState.message}
                                    </p>
                                    {sshConnectionState.osInfo ? (
                                        <p className="text-sm text-muted-foreground">
                                            {sshConnectionState.osInfo}
                                        </p>
                                    ) : null}

                                    <div className="flex flex-wrap items-center gap-3">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={handleTestConnection}
                                            disabled={testingConnection}
                                        >
                                            {testingConnection ? (
                                                <LoaderCircleIcon data-icon="inline-start" className="animate-spin" />
                                            ) : (
                                                <ShieldCheckIcon data-icon="inline-start" />
                                            )}
                                            Test Connection
                                        </Button>
                                        <p className="text-sm text-muted-foreground">
                                            {hasDraftSshChanges
                                                ? 'Testing the unsaved draft SSH credentials currently in the form.'
                                                : 'Testing the saved SSH credentials currently attached to this server.'}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Server Resources</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('server_cpu') || undefined}>
                                        <FieldLabel htmlFor="server_cpu">CPU</FieldLabel>
                                        <Input
                                            id="server_cpu"
                                            value={form.data.server_cpu}
                                            onChange={(event) => form.setField('server_cpu', event.target.value)}
                                            onBlur={() => form.touch('server_cpu')}
                                            aria-invalid={form.invalid('server_cpu') || undefined}
                                            placeholder="Intel Xeon"
                                        />
                                        <FieldError>{form.error('server_cpu')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('server_ccore') || undefined}>
                                        <FieldLabel htmlFor="server_ccore">CPU Cores</FieldLabel>
                                        <Input
                                            id="server_ccore"
                                            type="number"
                                            min={1}
                                            value={form.data.server_ccore}
                                            onChange={(event) => form.setField('server_ccore', event.target.value)}
                                            onBlur={() => form.touch('server_ccore')}
                                            aria-invalid={form.invalid('server_ccore') || undefined}
                                        />
                                        <FieldError>{form.error('server_ccore')}</FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('server_ram') || undefined}>
                                        <FieldLabel htmlFor="server_ram">RAM (MB)</FieldLabel>
                                        <Input
                                            id="server_ram"
                                            type="number"
                                            min={0}
                                            value={form.data.server_ram}
                                            onChange={(event) => form.setField('server_ram', event.target.value)}
                                            onBlur={() => form.touch('server_ram')}
                                            aria-invalid={form.invalid('server_ram') || undefined}
                                        />
                                        <FieldError>{form.error('server_ram')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('server_storage') || undefined}>
                                        <FieldLabel htmlFor="server_storage">Storage (GB)</FieldLabel>
                                        <Input
                                            id="server_storage"
                                            type="number"
                                            min={0}
                                            value={form.data.server_storage}
                                            onChange={(event) => form.setField('server_storage', event.target.value)}
                                            onBlur={() => form.touch('server_storage')}
                                            aria-invalid={form.invalid('server_storage') || undefined}
                                        />
                                        <FieldError>{form.error('server_storage')}</FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('server_os') || undefined}>
                                        <FieldLabel htmlFor="server_os">Operating System</FieldLabel>
                                        <Input
                                            id="server_os"
                                            value={form.data.server_os}
                                            onChange={(event) => form.setField('server_os', event.target.value)}
                                            onBlur={() => form.touch('server_os')}
                                            aria-invalid={form.invalid('server_os') || undefined}
                                            placeholder="Ubuntu 24.04"
                                        />
                                        <FieldError>{form.error('server_os')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('max_domains') || undefined}>
                                        <FieldLabel htmlFor="max_domains">Maximum Domains (Soft Limit)</FieldLabel>
                                        <Input
                                            id="max_domains"
                                            type="number"
                                            min={0}
                                            value={form.data.max_domains}
                                            onChange={(event) => form.setField('max_domains', event.target.value)}
                                            onBlur={() => form.touch('max_domains')}
                                            aria-invalid={form.invalid('max_domains') || undefined}
                                        />
                                        <FieldDescription>
                                            Optional capacity planning limit. Leave blank for unlimited.
                                        </FieldDescription>
                                        <FieldError>{form.error('max_domains')}</FieldError>
                                    </Field>
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Version Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('astero_version') || undefined}>
                                        <FieldLabel htmlFor="astero_version">Astero Version</FieldLabel>
                                        <Input
                                            id="astero_version"
                                            value={form.data.astero_version}
                                            onChange={(event) => form.setField('astero_version', event.target.value)}
                                            onBlur={() => form.touch('astero_version')}
                                            aria-invalid={form.invalid('astero_version') || undefined}
                                            placeholder="1.0.46"
                                        />
                                        <FieldError>{form.error('astero_version')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('hestia_version') || undefined}>
                                        <FieldLabel htmlFor="hestia_version">Hestia Version</FieldLabel>
                                        <Input
                                            id="hestia_version"
                                            value={form.data.hestia_version}
                                            onChange={(event) => form.setField('hestia_version', event.target.value)}
                                            onBlur={() => form.touch('hestia_version')}
                                            aria-invalid={form.invalid('hestia_version') || undefined}
                                            placeholder="1.9.4"
                                        />
                                        <FieldError>{form.error('hestia_version')}</FieldError>
                                    </Field>
                                </div>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Organization</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <ServerTypeProviderFields
                                    form={form}
                                    typeOptions={typeOptions}
                                    providerOptions={providerOptions}
                                />
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Location</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field data-invalid={form.invalid('location_country_code') || undefined}>
                                    <FieldLabel htmlFor="location_country_code">Country</FieldLabel>
                                    <CountrySelect
                                        value={form.data.location_country_code}
                                        onChange={(code, name) => {
                                            const countryChanged = code !== form.data.location_country_code;

                                            form.setField('location_country_code', code);
                                            form.setField('location_country', name);

                                            if (countryChanged) {
                                                form.setField('location_city_code', '');
                                                form.setField('location_city', '');
                                            }
                                        }}
                                        className="w-full"
                                        aria-invalid={form.invalid('location_country_code') || undefined}
                                    />
                                    <FieldError>{form.error('location_country_code')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('location_city') || undefined}>
                                    <FieldLabel htmlFor="location_city">City</FieldLabel>
                                    <CitySelect
                                        countryCode={form.data.location_country_code}
                                        stateCode=""
                                        value={form.data.location_city}
                                        onChange={(code, name) => {
                                            form.setField('location_city_code', code);
                                            form.setField('location_city', name);
                                        }}
                                        className="w-full"
                                        aria-invalid={form.invalid('location_city') || undefined}
                                    />
                                    <FieldError>{form.error('location_city')}</FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Status & Monitoring</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <Field data-invalid={form.invalid('status') || undefined}>
                                    <FieldLabel htmlFor="status">Status</FieldLabel>
                                    <NativeSelect
                                        id="status"
                                        name="status"
                                        size="comfortable"
                                        value={form.data.status || ''}
                                        onChange={(event) => form.setField('status', event.target.value)}
                                        aria-invalid={form.invalid('status') || undefined}
                                        className="w-full"
                                    >
                                        {statusOptions.map((option) => (
                                            <NativeSelectOption key={String(option.value)} value={String(option.value)}>
                                                {option.label}
                                            </NativeSelectOption>
                                        ))}
                                    </NativeSelect>
                                    <FieldError>{form.error('status')}</FieldError>
                                </Field>

                                <Field orientation="horizontal">
                                    <FieldLabel htmlFor="monitor">Enable Monitoring</FieldLabel>
                                    <FieldDescription>
                                        Track server health and performance metrics.
                                    </FieldDescription>
                                    <Switch
                                        id="monitor"
                                        checked={form.data.monitor}
                                        onCheckedChange={(checked) => form.setField('monitor', checked)}
                                    />
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="flex flex-col gap-4 py-6">
                            <Button type="submit" disabled={form.processing} className="w-full">
                                {form.processing ? (
                                    <Spinner data-icon="inline-start" />
                                ) : (
                                    <SaveIcon data-icon="inline-start" />
                                )}
                                Save Server
                            </Button>

                            <Button variant="outline" asChild className="w-full">
                                <Link href={route('platform.servers.show', server.id)}>Cancel</Link>
                            </Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </form>
    );
}
