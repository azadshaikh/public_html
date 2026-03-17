import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAppForm } from '@/hooks/use-app-form';
import type { PlatformOption, ServerFormValues } from '../../types/platform';

type ServerFormProps = {
    mode: 'create' | 'edit';
    server?: {
        id: number;
        name: string;
        provisioning_status?: string | null;
    };
    initialValues: ServerFormValues;
    typeOptions: PlatformOption[];
    providerOptions: PlatformOption[];
    statusOptions: PlatformOption[];
    sshCommand?: string | null;
};

const INSTALL_TOGGLES: Array<keyof ServerFormValues> = [
    'install_apache',
    'install_phpfpm',
    'install_multiphp',
    'install_vsftpd',
    'install_proftpd',
    'install_named',
    'install_mysql',
    'install_mysql8',
    'install_postgresql',
    'install_exim',
    'install_dovecot',
    'install_sieve',
    'install_clamav',
    'install_spamassassin',
    'install_iptables',
    'install_fail2ban',
    'install_quota',
    'install_resourcelimit',
    'install_webterminal',
    'install_api',
    'install_force',
];

const INSTALL_LABELS: Record<keyof ServerFormValues, string> = {
    creation_mode: 'Creation mode',
    name: 'Name',
    ip: 'IP',
    fqdn: 'FQDN',
    type: 'Type',
    provider_id: 'Provider',
    monitor: 'Monitoring',
    status: 'Status',
    port: 'Port',
    access_key_id: 'Access key ID',
    access_key_secret: 'Access key secret',
    release_api_key: 'Release API key',
    max_domains: 'Max domains',
    ssh_port: 'SSH port',
    ssh_user: 'SSH user',
    ssh_public_key: 'SSH public key',
    ssh_private_key: 'SSH private key',
    release_zip_url: 'Release zip URL',
    install_port: 'Install port',
    install_lang: 'Install language',
    install_apache: 'Apache',
    install_phpfpm: 'PHP-FPM',
    install_multiphp: 'Multi PHP',
    install_multiphp_versions: 'Multi PHP versions',
    install_vsftpd: 'VSFTPD',
    install_proftpd: 'ProFTPD',
    install_named: 'Named',
    install_mysql: 'MariaDB',
    install_mysql8: 'MySQL 8',
    install_postgresql: 'PostgreSQL',
    install_exim: 'Exim',
    install_dovecot: 'Dovecot',
    install_sieve: 'Sieve',
    install_clamav: 'ClamAV',
    install_spamassassin: 'SpamAssassin',
    install_iptables: 'iptables',
    install_fail2ban: 'Fail2Ban',
    install_quota: 'Quota',
    install_resourcelimit: 'Resource limit',
    install_webterminal: 'Web terminal',
    install_api: 'API',
    install_force: 'Force install',
};

export default function ServerForm({
    mode,
    server,
    initialValues,
    typeOptions,
    providerOptions,
    statusOptions,
    sshCommand,
}: ServerFormProps) {
    const form = useAppForm<ServerFormValues>({
        defaults: initialValues,
        rememberKey:
            mode === 'create'
                ? 'platform.servers.create'
                : `platform.servers.edit.${server?.id ?? 'new'}`,
        dirtyGuard: true,
    });

    const submitUrl =
        mode === 'create'
            ? route('platform.servers.store')
            : route('platform.servers.update', server!.id);

    const showProvisioning = mode === 'create' && form.data.creation_mode === 'provision';

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(mode === 'create' ? 'post' : 'put', submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast:
                mode === 'create'
                    ? 'Server created successfully.'
                    : 'Server updated successfully.',
        });
    };

    return (
        <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Connection</CardTitle>
                            <CardDescription>
                                Configure the Hestia endpoint and access credentials for the server.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                {mode === 'create' ? (
                                    <Field>
                                        <FieldLabel>Creation mode</FieldLabel>
                                        <ToggleGroup
                                            type="single"
                                            value={form.data.creation_mode}
                                            onValueChange={(value) => {
                                                if (!value) {
                                                    return;
                                                }

                                                form.setField(
                                                    'creation_mode',
                                                    value as ServerFormValues['creation_mode'],
                                                );
                                            }}
                                            className="w-full justify-start"
                                        >
                                            <ToggleGroupItem value="manual" className="min-w-32">
                                                Manual
                                            </ToggleGroupItem>
                                            <ToggleGroupItem value="provision" className="min-w-32">
                                                Provision
                                            </ToggleGroupItem>
                                        </ToggleGroup>
                                        <FieldDescription>
                                            Use manual mode for existing Hestia servers, or provision mode for fresh VPS setup.
                                        </FieldDescription>
                                    </Field>
                                ) : null}

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('name') || undefined}>
                                        <FieldLabel htmlFor="name">Server name</FieldLabel>
                                        <Input
                                            id="name"
                                            value={form.data.name}
                                            onChange={(event) => form.setField('name', event.target.value)}
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
                                            onChange={(event) => form.setField('ip', event.target.value)}
                                            onBlur={() => form.touch('ip')}
                                            aria-invalid={form.invalid('ip') || undefined}
                                        />
                                        <FieldError>{form.error('ip')}</FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('fqdn') || undefined}>
                                        <FieldLabel htmlFor="fqdn">FQDN</FieldLabel>
                                        <Input
                                            id="fqdn"
                                            value={form.data.fqdn}
                                            onChange={(event) => form.setField('fqdn', event.target.value)}
                                            onBlur={() => form.touch('fqdn')}
                                            aria-invalid={form.invalid('fqdn') || undefined}
                                            placeholder="server.example.com"
                                        />
                                        <FieldError>{form.error('fqdn')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('max_domains') || undefined}>
                                        <FieldLabel htmlFor="max_domains">Max domains</FieldLabel>
                                        <Input
                                            id="max_domains"
                                            type="number"
                                            min={0}
                                            value={form.data.max_domains}
                                            onChange={(event) => form.setField('max_domains', event.target.value)}
                                            onBlur={() => form.touch('max_domains')}
                                            aria-invalid={form.invalid('max_domains') || undefined}
                                        />
                                        <FieldError>{form.error('max_domains')}</FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('type') || undefined}>
                                        <FieldLabel>Server type</FieldLabel>
                                        <Select value={form.data.type || undefined} onValueChange={(value) => form.setField('type', value)}>
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('type') || undefined}>
                                                <SelectValue placeholder="Select server type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {typeOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('type')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('provider_id') || undefined}>
                                        <FieldLabel>Provider</FieldLabel>
                                        <Select
                                            value={form.data.provider_id || undefined}
                                            onValueChange={(value) => form.setField('provider_id', value)}
                                        >
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('provider_id') || undefined}>
                                                <SelectValue placeholder="Select provider" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {providerOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('provider_id')}</FieldError>
                                    </Field>
                                </div>

                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('status') || undefined}>
                                        <FieldLabel>Status</FieldLabel>
                                        <Select value={form.data.status || undefined} onValueChange={(value) => form.setField('status', value)}>
                                            <SelectTrigger className="w-full" aria-invalid={form.invalid('status') || undefined}>
                                                <SelectValue placeholder="Select status" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    {statusOptions.map((option) => (
                                                        <SelectItem key={String(option.value)} value={String(option.value)}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                        <FieldError>{form.error('status')}</FieldError>
                                    </Field>

                                    <Field orientation="horizontal">
                                        <FieldLabel htmlFor="monitor">Monitoring</FieldLabel>
                                        <FieldDescription>Enable health and uptime monitoring.</FieldDescription>
                                        <Switch
                                            id="monitor"
                                            checked={form.data.monitor}
                                            onCheckedChange={(checked) => form.setField('monitor', checked)}
                                        />
                                    </Field>
                                </div>

                                {!showProvisioning ? (
                                    <>
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <Field data-invalid={form.invalid('port') || undefined}>
                                                <FieldLabel htmlFor="port">Hestia port</FieldLabel>
                                                <Input
                                                    id="port"
                                                    type="number"
                                                    min={1}
                                                    max={65535}
                                                    value={form.data.port}
                                                    onChange={(event) => form.setField('port', event.target.value)}
                                                    onBlur={() => form.touch('port')}
                                                    aria-invalid={form.invalid('port') || undefined}
                                                />
                                                <FieldError>{form.error('port')}</FieldError>
                                            </Field>

                                            <Field data-invalid={form.invalid('access_key_id') || undefined}>
                                                <FieldLabel htmlFor="access_key_id">Access key ID</FieldLabel>
                                                <Input
                                                    id="access_key_id"
                                                    value={form.data.access_key_id}
                                                    onChange={(event) => form.setField('access_key_id', event.target.value)}
                                                    onBlur={() => form.touch('access_key_id')}
                                                    aria-invalid={form.invalid('access_key_id') || undefined}
                                                />
                                                <FieldError>{form.error('access_key_id')}</FieldError>
                                            </Field>
                                        </div>

                                        <Field data-invalid={form.invalid('access_key_secret') || undefined}>
                                            <FieldLabel htmlFor="access_key_secret">
                                                {mode === 'create' ? 'Access key secret' : 'Access key secret (optional)'}
                                            </FieldLabel>
                                            <Input
                                                id="access_key_secret"
                                                type="password"
                                                value={form.data.access_key_secret}
                                                onChange={(event) => form.setField('access_key_secret', event.target.value)}
                                                onBlur={() => form.touch('access_key_secret')}
                                                aria-invalid={form.invalid('access_key_secret') || undefined}
                                            />
                                            <FieldError>{form.error('access_key_secret')}</FieldError>
                                        </Field>
                                    </>
                                ) : null}
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>SSH credentials</CardTitle>
                            <CardDescription>
                                Used for provisioning, release synchronization, and diagnostics.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
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
                                            onChange={(event) => form.setField('ssh_port', event.target.value)}
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
                                            onChange={(event) => form.setField('ssh_user', event.target.value)}
                                            onBlur={() => form.touch('ssh_user')}
                                            aria-invalid={form.invalid('ssh_user') || undefined}
                                        />
                                        <FieldError>{form.error('ssh_user')}</FieldError>
                                    </Field>
                                </div>

                                <Field data-invalid={form.invalid('ssh_public_key') || undefined}>
                                    <FieldLabel htmlFor="ssh_public_key">SSH public key</FieldLabel>
                                    <Input
                                        id="ssh_public_key"
                                        value={form.data.ssh_public_key}
                                        onChange={(event) => form.setField('ssh_public_key', event.target.value)}
                                        onBlur={() => form.touch('ssh_public_key')}
                                        aria-invalid={form.invalid('ssh_public_key') || undefined}
                                    />
                                    {sshCommand ? (
                                        <FieldDescription>
                                            Authorize this key on the target server with: {sshCommand}
                                        </FieldDescription>
                                    ) : null}
                                    <FieldError>{form.error('ssh_public_key')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('ssh_private_key') || undefined}>
                                    <FieldLabel htmlFor="ssh_private_key">SSH private key</FieldLabel>
                                    <Input
                                        id="ssh_private_key"
                                        value={form.data.ssh_private_key}
                                        onChange={(event) => form.setField('ssh_private_key', event.target.value)}
                                        onBlur={() => form.touch('ssh_private_key')}
                                        aria-invalid={form.invalid('ssh_private_key') || undefined}
                                    />
                                    <FieldError>{form.error('ssh_private_key')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('release_api_key') || undefined}>
                                    <FieldLabel htmlFor="release_api_key">Release API key</FieldLabel>
                                    <Input
                                        id="release_api_key"
                                        value={form.data.release_api_key}
                                        onChange={(event) => form.setField('release_api_key', event.target.value)}
                                        onBlur={() => form.touch('release_api_key')}
                                        aria-invalid={form.invalid('release_api_key') || undefined}
                                    />
                                    <FieldError>{form.error('release_api_key')}</FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    {showProvisioning ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>Provisioning defaults</CardTitle>
                                <CardDescription>
                                    Configure how Hestia and related packages will be installed on a fresh server.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <FieldGroup>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <Field data-invalid={form.invalid('install_port') || undefined}>
                                            <FieldLabel htmlFor="install_port">Install port</FieldLabel>
                                            <Input
                                                id="install_port"
                                                type="number"
                                                min={1}
                                                max={65535}
                                                value={form.data.install_port}
                                                onChange={(event) => form.setField('install_port', event.target.value)}
                                                onBlur={() => form.touch('install_port')}
                                                aria-invalid={form.invalid('install_port') || undefined}
                                            />
                                            <FieldError>{form.error('install_port')}</FieldError>
                                        </Field>

                                        <Field data-invalid={form.invalid('install_lang') || undefined}>
                                            <FieldLabel htmlFor="install_lang">Install language</FieldLabel>
                                            <Input
                                                id="install_lang"
                                                value={form.data.install_lang}
                                                onChange={(event) => form.setField('install_lang', event.target.value)}
                                                onBlur={() => form.touch('install_lang')}
                                                aria-invalid={form.invalid('install_lang') || undefined}
                                            />
                                            <FieldError>{form.error('install_lang')}</FieldError>
                                        </Field>
                                    </div>

                                    <Field data-invalid={form.invalid('install_multiphp_versions') || undefined}>
                                        <FieldLabel htmlFor="install_multiphp_versions">Multi PHP versions</FieldLabel>
                                        <Input
                                            id="install_multiphp_versions"
                                            value={form.data.install_multiphp_versions}
                                            onChange={(event) =>
                                                form.setField('install_multiphp_versions', event.target.value)
                                            }
                                            onBlur={() => form.touch('install_multiphp_versions')}
                                            aria-invalid={form.invalid('install_multiphp_versions') || undefined}
                                            placeholder="8.4,8.3"
                                        />
                                        <FieldError>{form.error('install_multiphp_versions')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('release_zip_url') || undefined}>
                                        <FieldLabel htmlFor="release_zip_url">Release ZIP URL</FieldLabel>
                                        <Input
                                            id="release_zip_url"
                                            value={form.data.release_zip_url}
                                            onChange={(event) => form.setField('release_zip_url', event.target.value)}
                                            onBlur={() => form.touch('release_zip_url')}
                                            aria-invalid={form.invalid('release_zip_url') || undefined}
                                        />
                                        <FieldError>{form.error('release_zip_url')}</FieldError>
                                    </Field>

                                    <div className="grid gap-4 md:grid-cols-2">
                                        {INSTALL_TOGGLES.map((field) => (
                                            <Field key={field} orientation="horizontal">
                                                <FieldLabel htmlFor={field}>{INSTALL_LABELS[field]}</FieldLabel>
                                                <Switch
                                                    id={field}
                                                    checked={Boolean(form.data[field])}
                                                    onCheckedChange={(checked) =>
                                                        form.setField(field, checked as never)
                                                    }
                                                />
                                            </Field>
                                        ))}
                                    </div>
                                </FieldGroup>
                            </CardContent>
                        </Card>
                    ) : (
                        <Card>
                            <CardHeader>
                                <CardTitle>Existing Hestia endpoint</CardTitle>
                                <CardDescription>
                                    Manual mode expects Hestia to be installed and reachable over the configured API port.
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">
                                    Use manual mode for servers that already have Hestia and API credentials configured.
                                    SSH details remain optional for later reprovisioning or release operations.
                                </p>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button variant="outline" asChild>
                    <Link href={route('platform.servers.index', { status: 'all' })}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to servers
                    </Link>
                </Button>

                <Button type="submit" disabled={form.processing}>
                    {form.processing ? <Spinner data-icon="inline-start" /> : <SaveIcon data-icon="inline-start" />}
                    {mode === 'create' ? 'Create server' : 'Save changes'}
                </Button>
            </div>
        </form>
    );
}
