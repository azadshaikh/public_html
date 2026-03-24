import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { cn } from '@/lib/utils';
import AgencyOnboardingLayout from '../../../components/agency-onboarding-layout';

type AgencyOnboardingDomainPageProps = {
    savedDomain: string | null;
    savedDomainType: 'subdomain' | 'custom';
    savedDnsMode: 'managed' | 'external';
    freeSubdomain: string;
};

export default function AgencyOnboardingDomain({
    savedDomain,
    savedDomainType,
    savedDnsMode,
    freeSubdomain,
}: AgencyOnboardingDomainPageProps) {
    const form = useForm({
        domain_type: savedDomainType ?? 'subdomain',
        subdomain:
            savedDomainType === 'subdomain' && savedDomain
                ? savedDomain.replace(`.${freeSubdomain}`, '')
                : '',
        custom_domain:
            savedDomainType === 'custom' && savedDomain ? savedDomain : '',
        dns_mode: savedDnsMode ?? 'managed',
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(route('agency.onboarding.domain.store'));
    };

    const selectedDomainPreview =
        form.data.domain_type === 'subdomain'
            ? form.data.subdomain !== ''
                ? `${form.data.subdomain}.${freeSubdomain}`
                : `your-site-name.${freeSubdomain}`
            : form.data.custom_domain !== ''
                ? form.data.custom_domain
                : 'example.com';

    return (
        <AgencyOnboardingLayout
            title="Choose Your Domain"
            description="Pick a free subdomain or connect a domain you already own."
            currentStep="domain"
            backHref={route('agency.websites.index')}
        >
            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_18rem]">
                <Card className="w-full rounded-[2rem] border-black/6 bg-white/92 shadow-[0_20px_80px_rgba(33,30,22,0.08)] dark:border-white/10 dark:bg-white/5 dark:shadow-none">
                    <CardHeader className="space-y-3 border-b border-black/6 pb-6 dark:border-white/10">
                        <Badge variant="info" className="w-fit rounded-full px-3 py-1">
                            Domain Setup
                        </Badge>
                        <div className="space-y-2">
                            <CardTitle className="text-2xl tracking-[-0.03em]">
                                Website address
                            </CardTitle>
                            <CardDescription className="text-sm leading-6">
                                Choose how the website should be reached now. You can change the
                                connected domain later from website settings.
                            </CardDescription>
                        </div>
                    </CardHeader>

                    <CardContent className="pt-6">
                        <form className="space-y-6" onSubmit={handleSubmit}>
                            <FieldGroup>
                                <Field data-invalid={form.errors.domain_type || undefined}>
                                    <FieldLabel>Domain Type</FieldLabel>
                                    <RadioGroup
                                        value={form.data.domain_type}
                                        onValueChange={(value: 'subdomain' | 'custom') =>
                                            form.setData('domain_type', value)
                                        }
                                        className="gap-4"
                                    >
                                        <label
                                            className={cn(
                                                'flex items-start gap-4 rounded-[1.5rem] border p-5 transition-colors',
                                                form.data.domain_type === 'subdomain'
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-border bg-background',
                                            )}
                                        >
                                            <RadioGroupItem value="subdomain" className="mt-1" />
                                            <div className="space-y-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-semibold">
                                                        Use a free subdomain
                                                    </p>
                                                    <Badge variant="success">
                                                        Instant launch
                                                    </Badge>
                                                </div>
                                                <p className="text-sm leading-6 text-muted-foreground">
                                                    Provision immediately on
                                                    {' '}
                                                    <span className="font-medium">
                                                        .{freeSubdomain}
                                                    </span>
                                                    {' '}
                                                    without touching any DNS settings.
                                                </p>
                                            </div>
                                        </label>

                                        <label
                                            className={cn(
                                                'flex items-start gap-4 rounded-[1.5rem] border p-5 transition-colors',
                                                form.data.domain_type === 'custom'
                                                    ? 'border-primary bg-primary/5'
                                                    : 'border-border bg-background',
                                            )}
                                        >
                                            <RadioGroupItem value="custom" className="mt-1" />
                                            <div className="space-y-1">
                                                <p className="font-semibold">Use my own domain</p>
                                                <p className="text-sm leading-6 text-muted-foreground">
                                                    Connect a domain you already manage and choose whether
                                                    we handle DNS for you or you keep your current provider.
                                                </p>
                                            </div>
                                        </label>
                                    </RadioGroup>
                                    <FieldError>{form.errors.domain_type}</FieldError>
                                </Field>

                                {form.data.domain_type === 'subdomain' ? (
                                    <Field data-invalid={form.errors.subdomain || undefined}>
                                        <FieldLabel htmlFor="subdomain">Subdomain</FieldLabel>
                                        <div className="flex items-center rounded-2xl border bg-background">
                                            <Input
                                                id="subdomain"
                                                className="border-0 shadow-none"
                                                value={form.data.subdomain}
                                                onChange={(event) =>
                                                    form.setData('subdomain', event.target.value)
                                                }
                                                placeholder="your-site-name"
                                            />
                                            <span className="px-4 text-sm text-muted-foreground">
                                                .{freeSubdomain}
                                            </span>
                                        </div>
                                        <FieldError>{form.errors.subdomain}</FieldError>
                                    </Field>
                                ) : (
                                    <>
                                        <Field data-invalid={form.errors.custom_domain || undefined}>
                                            <FieldLabel htmlFor="custom_domain">
                                                Custom Domain
                                            </FieldLabel>
                                            <Input
                                                id="custom_domain"
                                                value={form.data.custom_domain}
                                                onChange={(event) =>
                                                    form.setData('custom_domain', event.target.value)
                                                }
                                                placeholder="example.com"
                                            />
                                            <FieldError>{form.errors.custom_domain}</FieldError>
                                        </Field>

                                        <Field data-invalid={form.errors.dns_mode || undefined}>
                                            <FieldLabel>DNS Handling</FieldLabel>
                                            <RadioGroup
                                                value={form.data.dns_mode}
                                                onValueChange={(value: 'managed' | 'external') =>
                                                    form.setData('dns_mode', value)
                                                }
                                                className="gap-4"
                                            >
                                                <label
                                                    className={cn(
                                                        'flex items-start gap-4 rounded-[1.5rem] border p-5 transition-colors',
                                                        form.data.dns_mode === 'managed'
                                                            ? 'border-primary bg-primary/5'
                                                            : 'border-border bg-background',
                                                    )}
                                                >
                                                    <RadioGroupItem
                                                        value="managed"
                                                        className="mt-1"
                                                    />
                                                    <div className="space-y-1">
                                                        <p className="font-semibold">Managed DNS</p>
                                                        <p className="text-sm leading-6 text-muted-foreground">
                                                            Update nameservers once and let the platform
                                                            manage DNS, SSL, and edge delivery for you.
                                                        </p>
                                                    </div>
                                                </label>

                                                <label
                                                    className={cn(
                                                        'flex items-start gap-4 rounded-[1.5rem] border p-5 transition-colors',
                                                        form.data.dns_mode === 'external'
                                                            ? 'border-primary bg-primary/5'
                                                            : 'border-border bg-background',
                                                    )}
                                                >
                                                    <RadioGroupItem
                                                        value="external"
                                                        className="mt-1"
                                                    />
                                                    <div className="space-y-1">
                                                        <p className="font-semibold">External DNS</p>
                                                        <p className="text-sm leading-6 text-muted-foreground">
                                                            Keep Cloudflare, GoDaddy, or another provider
                                                            and add the required records manually later.
                                                        </p>
                                                    </div>
                                                </label>
                                            </RadioGroup>
                                            <FieldError>{form.errors.dns_mode}</FieldError>
                                        </Field>
                                    </>
                                )}
                            </FieldGroup>

                            <div className="flex flex-col gap-3 border-t border-black/6 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-white/10">
                                <p className="text-sm leading-6 text-muted-foreground">
                                    You can revisit this choice later if the client’s domain plan
                                    changes.
                                </p>

                                <Button type="submit" disabled={form.processing}>
                                    Continue to Plans
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="space-y-4">
                    <Card className="rounded-[2rem] border-black/6 bg-white/88 dark:border-white/10 dark:bg-white/5">
                        <CardHeader className="space-y-2">
                            <CardTitle className="text-lg">Preview</CardTitle>
                            <CardDescription>
                                This is the address the new website will start with.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="rounded-[1.5rem] border border-dashed border-primary/30 bg-primary/5 p-4">
                                <p className="text-xs font-semibold tracking-[0.22em] text-muted-foreground uppercase">
                                    Website URL
                                </p>
                                <p className="mt-2 break-all text-base font-semibold">
                                    {selectedDomainPreview}
                                </p>
                            </div>

                            <div className="space-y-3 text-sm leading-6 text-muted-foreground">
                                <p>
                                    Free subdomains are best when you want the fastest possible
                                    launch.
                                </p>
                                <p>
                                    Custom domains are best when the client already has an
                                    established brand domain.
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="rounded-[2rem] border-black/6 bg-white/88 dark:border-white/10 dark:bg-white/5">
                        <CardHeader className="space-y-2">
                            <CardTitle className="text-lg">Included either way</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm leading-6 text-muted-foreground">
                            <p>Provisioning, SSL, and launch progress are handled inside the flow.</p>
                            <p>
                                You do not need to decide hosting details here. That comes from the
                                plan and platform setup automatically.
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AgencyOnboardingLayout>
    );
}
