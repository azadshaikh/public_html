import { Link } from '@inertiajs/react';
import { CheckCircle2Icon, LoaderCircleIcon, ShieldCheckIcon, XCircleIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';

import { ServerTypeProviderFields } from '../../../../components/servers/server-type-provider-fields';
import type { PlatformOption, ServerFormValues } from '../../../../types/platform';

type ServerWizardManualStepFormHandle = {
    data: ServerFormValues;
    setField: <K extends keyof ServerFormValues>(key: K, value: ServerFormValues[K]) => void;
    invalid: (key: keyof ServerFormValues) => boolean;
    error: (key: keyof ServerFormValues) => string | undefined;
    processing: boolean;
};

type ServerWizardManualStepProps = {
    form: ServerWizardManualStepFormHandle;
    typeOptions: PlatformOption[];
    providerOptions: PlatformOption[];
};

export function ServerWizardManualStep({
    form,
    typeOptions,
    providerOptions,
}: ServerWizardManualStepProps) {
    const connectionRequirements = [
        {
            key: 'name',
            label: 'Server name',
            ready: form.data.name.trim() !== '',
        },
        {
            key: 'ip',
            label: 'Server IP address',
            ready: form.data.ip.trim() !== '',
        },
        {
            key: 'port',
            label: 'HestiaCP port',
            ready: form.data.port.trim() !== '',
        },
        {
            key: 'access_key_id',
            label: 'Access key ID',
            ready: form.data.access_key_id.trim() !== '',
        },
        {
            key: 'access_key_secret',
            label: 'Secret key',
            ready: form.data.access_key_secret.trim() !== '',
        },
    ];
    const isReadyToConnect = connectionRequirements.every((requirement) => requirement.ready);

    return (
        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <div className="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Connect Existing Server</CardTitle>
                        <CardDescription>
                            Enter the endpoint details for your existing HestiaCP server.
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
                                        aria-invalid={form.invalid('name') || undefined}
                                        placeholder="Production Server 1"
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
                                        aria-invalid={form.invalid('ip') || undefined}
                                        placeholder="203.0.113.10"
                                    />
                                    <FieldError>{form.error('ip')}</FieldError>
                                </Field>
                            </div>

                            <div className="grid gap-4 md:grid-cols-2">
                                <Field data-invalid={form.invalid('port') || undefined}>
                                    <FieldLabel htmlFor="port">HestiaCP port</FieldLabel>
                                    <Input
                                        id="port"
                                        type="number"
                                        min={1}
                                        max={65535}
                                        value={form.data.port}
                                        onChange={(event) =>
                                            form.setField('port', event.target.value)
                                        }
                                        aria-invalid={form.invalid('port') || undefined}
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
                        <CardTitle>HestiaCP API Credentials</CardTitle>
                        <CardDescription>
                            Generate these in Hestia Control Panel under Access Keys.
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
                                        aria-invalid={form.invalid('access_key_id') || undefined}
                                        placeholder="20-character key"
                                    />
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
                                        aria-invalid={
                                            form.invalid('access_key_secret') || undefined
                                        }
                                        placeholder="40-character secret"
                                    />
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
                                    aria-invalid={form.invalid('release_api_key') || undefined}
                                    placeholder="Optional release sync key"
                                />
                                <FieldDescription>
                                    Leave blank to use the default provisioning environment key.
                                </FieldDescription>
                                <FieldError>{form.error('release_api_key')}</FieldError>
                            </Field>
                        </FieldGroup>
                    </CardContent>
                </Card>
            </div>

            <div className="flex flex-col gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Organization</CardTitle>
                        <CardDescription>
                            Group the server under the right type and infrastructure provider.
                        </CardDescription>
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
                        <CardTitle>Connection Checklist</CardTitle>
                        <CardDescription>
                            Confirm the endpoint and API credentials before attaching the server.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="flex flex-col gap-3 text-sm text-muted-foreground">
                            {connectionRequirements.map((requirement) => (
                                <li key={requirement.key} className="flex items-center gap-3">
                                    {requirement.ready ? (
                                        <CheckCircle2Icon className="size-4 text-emerald-600" />
                                    ) : (
                                        <XCircleIcon className="size-4 text-muted-foreground" />
                                    )}
                                    <span>{requirement.label}</span>
                                </li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Connect Server</CardTitle>
                        <CardDescription>
                            Astero will attach this existing HestiaCP host immediately after you
                            submit the form.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="rounded-2xl border border-border/70 bg-muted/20 p-4">
                            <p className="text-sm font-medium text-foreground">
                                {isReadyToConnect ? 'Ready to connect' : 'Missing connection details'}
                            </p>
                            <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                {isReadyToConnect
                                    ? 'The server can be attached now using the Hestia API credentials above.'
                                    : 'Complete the checklist above so the existing server can be connected in one pass.'}
                            </p>
                        </div>
                        <Button type="submit" disabled={form.processing} className="w-full">
                            {form.processing ? (
                                <LoaderCircleIcon className="mr-2 size-4 animate-spin" />
                            ) : (
                                <ShieldCheckIcon className="mr-2 size-4" />
                            )}
                            {form.processing ? 'Connecting Server' : 'Connect Server'}
                        </Button>
                        <Button type="button" variant="outline" asChild className="w-full">
                            <Link href={route('platform.servers.index', { status: 'all' })}>
                                Cancel
                            </Link>
                        </Button>
                        <p className="text-sm leading-6 text-muted-foreground">
                            Use this path for an already provisioned HestiaCP server with valid API
                            access keys.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
