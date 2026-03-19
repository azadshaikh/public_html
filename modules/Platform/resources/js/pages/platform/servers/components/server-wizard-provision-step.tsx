import { Link } from '@inertiajs/react';
import {
    CheckCircle2Icon,
    ClipboardCopyIcon,
    KeyRoundIcon,
    LoaderCircleIcon,
    RefreshCcwIcon,
    ServerCogIcon,
    WrenchIcon,
    XCircleIcon,
} from 'lucide-react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

import { ServerInstallOptionsGrid } from '../../../../components/servers/server-install-options-grid';
import { ServerTypeProviderFields } from '../../../../components/servers/server-type-provider-fields';
import type { PlatformOption, ServerFormValues } from '../../../../types/platform';
import type { ConnectionState } from './server-wizard-utils';

type ServerWizardProvisionStepFormHandle = {
    data: ServerFormValues;
    setField: <K extends keyof ServerFormValues>(key: K, value: ServerFormValues[K]) => void;
    invalid: (key: keyof ServerFormValues) => boolean;
    error: (key: keyof ServerFormValues) => string | undefined;
    processing: boolean;
};

type ServerWizardProvisionStepProps = {
    form: ServerWizardProvisionStepFormHandle;
    typeOptions: PlatformOption[];
    providerOptions: PlatformOption[];
    currentSshCommand: string;
    copiedCommand: boolean;
    regeneratingKey: boolean;
    connectionState: ConnectionState;
    onCopyCommand: () => void;
    onRegenerateKey: () => void;
    onVerifyConnection: () => void;
};

export function ServerWizardProvisionStep({
    form,
    typeOptions,
    providerOptions,
    currentSshCommand,
    copiedCommand,
    regeneratingKey,
    connectionState,
    onCopyCommand,
    onRegenerateKey,
    onVerifyConnection,
}: ServerWizardProvisionStepProps) {
    const missingRequirements = [
        {
            key: 'ip',
            label: 'Server IP address',
            ready: form.data.ip.trim() !== '',
        },
        {
            key: 'ssh_private_key',
            label: 'SSH private key',
            ready: form.data.ssh_private_key.trim() !== '',
        },
    ];
    const isReadyToVerify = missingRequirements.every((requirement) => requirement.ready);
    const isSuccess = connectionState.status === 'success';
    const isError = connectionState.status === 'error';
    const isLoading = connectionState.status === 'loading';
    const isIdle = connectionState.status === 'idle';

    const statusTitle = isSuccess
        ? 'Connection verified'
        : isError
          ? 'Connection failed'
          : isLoading
            ? 'Checking connection'
            : 'Ready to verify';

        const verifyButtonLabel = isLoading
                ? 'Verifying connection'
                : isError
                    ? 'Retry Connection'
                    : isSuccess
                        ? 'Verify Again'
                        : 'Verify Connection';

    return (
        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <div className="flex flex-col gap-6">
                <Card className="border-amber-500/40 bg-amber-500/5">
                    <CardHeader className="flex flex-row items-start justify-between gap-4 space-y-0">
                        <div className="space-y-1">
                            <CardTitle>Step 1: Authorize SSH Access</CardTitle>
                            <CardDescription>
                                Run this command on a fresh Ubuntu or Debian server as the root
                                user.
                            </CardDescription>
                        </div>
                        <Badge variant="secondary">SSH bootstrap</Badge>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <p className="text-sm leading-6 text-muted-foreground">
                            The host should be a fresh installation of Ubuntu 22.04 or 24.04, or
                            Debian 11 or 12. This command adds the generated SSH key so Astero can
                            continue provisioning.
                        </p>
                        <Textarea
                            value={currentSshCommand}
                            readOnly
                            rows={5}
                            className="font-mono text-xs"
                        />
                        <div className="flex flex-wrap gap-3">
                            <Button type="button" variant="outline" onClick={onCopyCommand}>
                                <ClipboardCopyIcon className="mr-2 size-4" />
                                {copiedCommand ? 'Copied' : 'Copy command'}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={onRegenerateKey}
                                disabled={regeneratingKey}
                            >
                                {regeneratingKey ? (
                                    <LoaderCircleIcon className="mr-2 size-4 animate-spin" />
                                ) : (
                                    <RefreshCcwIcon className="mr-2 size-4" />
                                )}
                                Regenerate key pair
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Step 2: Server Details</CardTitle>
                        <CardDescription>
                            Define the target host and release bootstrap details.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <FieldGroup>
                            <div className="grid gap-4 md:grid-cols-2">
                                <Field data-invalid={form.invalid('name') || undefined}>
                                    <FieldLabel htmlFor="provision-name">Server name</FieldLabel>
                                    <Input
                                        id="provision-name"
                                        value={form.data.name}
                                        onChange={(event) =>
                                            form.setField('name', event.target.value)
                                        }
                                        aria-invalid={form.invalid('name') || undefined}
                                        placeholder="Production Server 1"
                                    />
                                    <FieldError>{form.error('name')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('ip') || undefined}>
                                    <FieldLabel htmlFor="provision-ip">IP address</FieldLabel>
                                    <Input
                                        id="provision-ip"
                                        value={form.data.ip}
                                        onChange={(event) =>
                                            form.setField('ip', event.target.value)
                                        }
                                        aria-invalid={form.invalid('ip') || undefined}
                                        placeholder="203.0.113.10"
                                    />
                                    <FieldError>{form.error('ip')}</FieldError>
                                </Field>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <Field data-invalid={form.invalid('ssh_port') || undefined}>
                                    <FieldLabel htmlFor="provision-ssh-port">SSH port</FieldLabel>
                                    <Input
                                        id="provision-ssh-port"
                                        type="number"
                                        min={1}
                                        max={65535}
                                        value={form.data.ssh_port}
                                        onChange={(event) =>
                                            form.setField('ssh_port', event.target.value)
                                        }
                                        aria-invalid={form.invalid('ssh_port') || undefined}
                                    />
                                    <FieldError>{form.error('ssh_port')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('fqdn') || undefined}>
                                    <FieldLabel htmlFor="provision-fqdn">Hostname (FQDN)</FieldLabel>
                                    <Input
                                        id="provision-fqdn"
                                        value={form.data.fqdn}
                                        onChange={(event) =>
                                            form.setField('fqdn', event.target.value)
                                        }
                                        aria-invalid={form.invalid('fqdn') || undefined}
                                        placeholder="server1.example.com"
                                    />
                                    <FieldDescription>
                                        This becomes the server hostname during provisioning.
                                    </FieldDescription>
                                    <FieldError>{form.error('fqdn')}</FieldError>
                                </Field>
                            </div>

                            <Field data-invalid={form.invalid('release_zip_url') || undefined}>
                                <FieldLabel htmlFor="release_zip_url">Release ZIP URL</FieldLabel>
                                <Input
                                    id="release_zip_url"
                                    type="url"
                                    value={form.data.release_zip_url}
                                    onChange={(event) =>
                                        form.setField('release_zip_url', event.target.value)
                                    }
                                    aria-invalid={form.invalid('release_zip_url') || undefined}
                                    placeholder="https://example.com/releases/latest.zip"
                                />
                                <FieldDescription>
                                    Optional. If set, provisioning can pull releases directly over
                                    SSH.
                                </FieldDescription>
                                <FieldError>{form.error('release_zip_url')}</FieldError>
                            </Field>

                            <Field data-invalid={form.invalid('release_api_key') || undefined}>
                                <FieldLabel htmlFor="provision-release-api-key">
                                    Release API key
                                </FieldLabel>
                                <Input
                                    id="provision-release-api-key"
                                    type="password"
                                    value={form.data.release_api_key}
                                    onChange={(event) =>
                                        form.setField('release_api_key', event.target.value)
                                    }
                                    aria-invalid={form.invalid('release_api_key') || undefined}
                                    placeholder="Optional release sync key"
                                />
                                <FieldDescription>
                                    Leave blank to use the environment default from the provisioning
                                    service.
                                </FieldDescription>
                                <FieldError>{form.error('release_api_key')}</FieldError>
                            </Field>
                        </FieldGroup>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Step 3: HestiaCP Options</CardTitle>
                        <CardDescription>
                            Configure the install profile that will be applied after SSH is verified.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ServerInstallOptionsGrid form={form} />
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
                        <CardTitle>Connection Status</CardTitle>
                        <CardDescription>
                            Verify SSH access before starting the provisioning run.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div
                            className="rounded-2xl border border-border/70 bg-muted/20 p-4"
                            role={isError ? 'alert' : 'status'}
                        >
                            <div className="flex items-center gap-3 text-sm font-medium">
                                {isSuccess ? (
                                    <CheckCircle2Icon className="size-4 text-emerald-600" />
                                ) : null}
                                {isError ? (
                                    <XCircleIcon className="size-4 text-destructive" />
                                ) : null}
                                {isLoading ? (
                                    <LoaderCircleIcon className="size-4 animate-spin text-primary" />
                                ) : null}
                                {isIdle ? (
                                    <KeyRoundIcon className="size-4 text-muted-foreground" />
                                ) : null}
                                <span>{statusTitle}</span>
                            </div>

                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                {connectionState.message}
                            </p>

                            {connectionState.osInfo ? (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    {connectionState.osInfo}
                                </p>
                            ) : null}
                        </div>

                        {!isReadyToVerify ? (
                            <div className="rounded-2xl border border-dashed border-border/80 bg-background/70 p-4">
                                <p className="text-sm font-medium text-foreground">
                                    Complete these before verifying
                                </p>
                                <ul className="mt-3 flex flex-col gap-2 text-sm text-muted-foreground">
                                    {missingRequirements.map((requirement) => (
                                        <li key={requirement.key} className="flex items-center gap-2">
                                            {requirement.ready ? (
                                                <CheckCircle2Icon className="size-4 text-emerald-600" />
                                            ) : (
                                                <XCircleIcon className="size-4 text-muted-foreground" />
                                            )}
                                            <span>{requirement.label}</span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ) : null}

                        <Button
                            type="button"
                            variant="outline"
                            onClick={onVerifyConnection}
                            disabled={isLoading}
                            className="w-full"
                        >
                            {isLoading ? (
                                <LoaderCircleIcon className="mr-2 size-4 animate-spin" />
                            ) : (
                                <WrenchIcon className="mr-2 size-4" />
                            )}
                            {verifyButtonLabel}
                        </Button>
                        <p className="text-sm leading-6 text-muted-foreground">
                            {isReadyToVerify
                                ? 'Run this after authorizing the SSH key to confirm Astero can reach the server.'
                                : 'Generate the SSH key and fill in the server details first, then verify the connection.'}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="space-y-4 py-6">
                        <div className="space-y-2">
                            <h3 className="font-semibold tracking-tight">Start provisioning</h3>
                            <p className="text-sm leading-6 text-muted-foreground">
                                Provisioning usually takes 15 to 30 minutes. Progress will continue
                                on the server detail page.
                            </p>
                        </div>
                        <Button type="submit" disabled={form.processing} className="w-full">
                            {form.processing ? (
                                <LoaderCircleIcon className="mr-2 size-4 animate-spin" />
                            ) : (
                                <ServerCogIcon className="mr-2 size-4" />
                            )}
                            Start Provisioning
                        </Button>
                        <Button type="button" variant="outline" asChild className="w-full">
                            <Link href={route('platform.servers.index', { status: 'all' })}>
                                Cancel
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
