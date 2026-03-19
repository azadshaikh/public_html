import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ServerFormValues } from '../../types/platform';

export const INSTALL_LANGUAGE_OPTIONS = [
    { value: 'en', label: 'English' },
    { value: 'de', label: 'German' },
    { value: 'es', label: 'Spanish' },
    { value: 'fr', label: 'French' },
    { value: 'ru', label: 'Russian' },
    { value: 'pt-br', label: 'Portuguese (Brazil)' },
    { value: 'zh-cn', label: 'Chinese (Simplified)' },
];

export const BOOLEAN_INSTALL_OPTIONS: Array<{
    field: keyof ServerFormValues;
    label: string;
    description: string;
}> = [
    {
        field: 'install_apache',
        label: 'Apache',
        description: 'Add Apache alongside NGINX for broader compatibility.',
    },
    {
        field: 'install_phpfpm',
        label: 'PHP-FPM',
        description: 'Enable the default PHP runtime for Hestia workloads.',
    },
    {
        field: 'install_vsftpd',
        label: 'VSFTPD',
        description: 'Install the VSFTPD FTP server.',
    },
    {
        field: 'install_proftpd',
        label: 'ProFTPD',
        description: 'Install ProFTPD instead of the lighter FTP stack.',
    },
    {
        field: 'install_named',
        label: 'BIND',
        description: 'Provision a DNS service on the server.',
    },
    {
        field: 'install_mysql',
        label: 'MariaDB',
        description: 'Install MariaDB for database-backed workloads.',
    },
    {
        field: 'install_mysql8',
        label: 'MySQL 8',
        description: 'Install MySQL 8 instead of MariaDB.',
    },
    {
        field: 'install_postgresql',
        label: 'PostgreSQL',
        description: 'Install PostgreSQL as an additional database option.',
    },
    {
        field: 'install_exim',
        label: 'Exim',
        description: 'Enable mail sending on the provisioned host.',
    },
    {
        field: 'install_dovecot',
        label: 'Dovecot',
        description: 'Enable IMAP and POP3 services for mailboxes.',
    },
    {
        field: 'install_sieve',
        label: 'Sieve',
        description: 'Enable mail filtering support.',
    },
    {
        field: 'install_clamav',
        label: 'ClamAV',
        description: 'Install antivirus scanning for mail and uploads.',
    },
    {
        field: 'install_spamassassin',
        label: 'SpamAssassin',
        description: 'Add spam filtering to the mail stack.',
    },
    {
        field: 'install_iptables',
        label: 'iptables',
        description: 'Enable the baseline firewall configuration.',
    },
    {
        field: 'install_fail2ban',
        label: 'Fail2Ban',
        description: 'Block repeated intrusion attempts automatically.',
    },
    {
        field: 'install_quota',
        label: 'Filesystem quota',
        description: 'Track and enforce disk quotas on hosted accounts.',
    },
    {
        field: 'install_resourcelimit',
        label: 'Resource limits',
        description: 'Enable additional resource limit controls.',
    },
    {
        field: 'install_webterminal',
        label: 'Web terminal',
        description: 'Expose the web terminal inside Hestia.',
    },
    {
        field: 'install_api',
        label: 'Hestia API',
        description: 'Enable API access immediately after install.',
    },
    {
        field: 'install_force',
        label: 'Force installation',
        description: 'Continue even if installer preflight checks are noisy.',
    },
];

type OptionToggleCardProps = {
    checked: boolean;
    label: string;
    description: string;
    onCheckedChange: (checked: boolean) => void;
    children?: React.ReactNode;
};

export function OptionToggleCard({
    checked,
    label,
    description,
    onCheckedChange,
    children,
}: OptionToggleCardProps) {
    return (
        <div
            className={[
                'rounded-2xl border p-4 transition-colors',
                checked ? 'border-primary/40 bg-primary/5' : 'border-border/70 bg-card',
            ].join(' ')}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="space-y-1">
                    <label className="flex items-center gap-3 text-sm font-medium text-foreground">
                        <Checkbox
                            checked={checked}
                            onCheckedChange={(value) => onCheckedChange(value === true)}
                        />
                        <span>{label}</span>
                    </label>
                    <p className="text-sm leading-5 text-muted-foreground">{description}</p>
                </div>
            </div>
            {checked && children ? <div className="mt-4">{children}</div> : null}
        </div>
    );
}

export function StaticOptionCard({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <div className="rounded-2xl border border-dashed border-border/80 bg-muted/30 p-4">
            <p className="text-sm font-medium text-muted-foreground">{title}</p>
            <p className="mt-2 text-sm leading-5 text-foreground">{description}</p>
        </div>
    );
}

export function OptionSelect({
    value,
    onValueChange,
    options,
    placeholder,
    invalid,
}: {
    value: string;
    onValueChange: (value: string) => void;
    options: Array<{ value: string | number; label: string }>;
    placeholder: string;
    invalid?: boolean;
}) {
    return (
        <Select value={value || undefined} onValueChange={onValueChange}>
            <SelectTrigger className="w-full" aria-invalid={invalid || undefined}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectGroup>
                    {options.map((option) => (
                        <SelectItem key={String(option.value)} value={String(option.value)}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectGroup>
            </SelectContent>
        </Select>
    );
}

type ServerInstallOptionsGridFormHandle = {
    data: ServerFormValues;
    setField: <K extends keyof ServerFormValues>(key: K, value: ServerFormValues[K]) => void;
};

type ServerInstallOptionsGridProps = {
    form: ServerInstallOptionsGridFormHandle;
};

export function ServerInstallOptionsGrid({ form }: ServerInstallOptionsGridProps) {
    return (
        <div className="space-y-6">
            <div className="grid gap-4 xl:grid-cols-3">
                <OptionToggleCard
                    checked={Boolean(form.data.install_port)}
                    label="Port"
                    description="Set the panel port used by HestiaCP."
                    onCheckedChange={(checked) =>
                        form.setField('install_port', checked ? form.data.install_port || '8443' : '')
                    }
                >
                    <Input
                        type="number"
                        min={1}
                        max={65535}
                        value={form.data.install_port}
                        onChange={(event) => form.setField('install_port', event.target.value)}
                    />
                </OptionToggleCard>

                <OptionToggleCard
                    checked={Boolean(form.data.install_lang)}
                    label="Language"
                    description="Choose the default HestiaCP interface language."
                    onCheckedChange={(checked) =>
                        form.setField('install_lang', checked ? form.data.install_lang || 'en' : '')
                    }
                >
                    <OptionSelect
                        value={form.data.install_lang}
                        onValueChange={(value) => form.setField('install_lang', value)}
                        options={INSTALL_LANGUAGE_OPTIONS}
                        placeholder="Select language"
                    />
                </OptionToggleCard>

                <OptionToggleCard
                    checked={form.data.install_multiphp}
                    label="MultiPHP"
                    description="Install additional PHP runtimes alongside the default version."
                    onCheckedChange={(checked) => form.setField('install_multiphp', checked)}
                >
                    <Input
                        value={form.data.install_multiphp_versions}
                        onChange={(event) =>
                            form.setField('install_multiphp_versions', event.target.value)
                        }
                        placeholder="8.3,8.4"
                    />
                </OptionToggleCard>
            </div>

            <div className="grid gap-4 xl:grid-cols-4">
                <StaticOptionCard
                    title="Hostname"
                    description={form.data.fqdn || 'Uses the FQDN from Server Details.'}
                />
                <StaticOptionCard title="Username" description="adminxastero" />
                <StaticOptionCard title="Email" description="hestia@astero.net.in" />
                <StaticOptionCard title="Password" description="Auto-generated secure password" />
            </div>

            <div className="grid gap-4 xl:grid-cols-3">
                {BOOLEAN_INSTALL_OPTIONS.map((option) => (
                    <OptionToggleCard
                        key={option.field}
                        checked={Boolean(form.data[option.field])}
                        label={option.label}
                        description={option.description}
                        onCheckedChange={(checked) => form.setField(option.field, checked)}
                    />
                ))}
            </div>
        </div>
    );
}
