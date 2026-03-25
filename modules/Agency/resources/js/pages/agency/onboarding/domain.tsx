import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { cn } from '@/lib/utils';
import AgencyOnboardingMinimalLayout from '../../../components/agency-onboarding-minimal-layout';

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

    return (
        <AgencyOnboardingMinimalLayout
            title="What's your website address?"
            description="Pick a free subdomain to get started, or connect a domain you already own."
            backHref={route('agency.websites.index')}
        >
            <form className="space-y-6" onSubmit={handleSubmit}>
                                <RadioGroup
                                    value={form.data.domain_type}
                                    onValueChange={(value: 'subdomain' | 'custom') =>
                                        form.setData('domain_type', value)
                                    }
                                    className="gap-5"
                                >
                                    <label
                                        className={cn(
                                            'block rounded-[1.6rem] border bg-card p-6 transition-colors',
                                            form.data.domain_type === 'subdomain'
                                                ? 'border-foreground shadow-[inset_0_0_0_1px_theme(colors.foreground)]'
                                                : 'border-border',
                                        )}
                                    >
                                        <div className="flex items-start gap-4">
                                            <RadioGroupItem value="subdomain" className="mt-1" />
                                            <div className="min-w-0 flex-1 space-y-4">
                                                <div className="space-y-1.5">
                                                    <p className="text-lg font-medium tracking-[-0.02em]">
                                                        Use a free subdomain
                                                    </p>
                                                    <p className="text-sm font-normal text-muted-foreground sm:text-base">
                                                        Get started instantly with a free
                                                        {' '}
                                                        <span className="font-semibold text-foreground">
                                                            .{freeSubdomain}
                                                        </span>
                                                        {' '}
                                                        subdomain.
                                                    </p>
                                                </div>

                                                {form.data.domain_type === 'subdomain' ? (
                                                    <div className="space-y-2">
                                                        <div className="flex overflow-hidden rounded-xl border border-input bg-background">
                                                            <Input
                                                                id="subdomain"
                                                                size="xl"
                                                                className="border-0 shadow-none"
                                                                value={form.data.subdomain}
                                                                onChange={(event) =>
                                                                    form.setData('subdomain', event.target.value)
                                                                }
                                                                placeholder="your-site-name"
                                                            />
                                                            <div className="flex h-11 shrink-0 items-center border-l border-input bg-muted px-4 text-sm font-normal text-muted-foreground">
                                                                .{freeSubdomain}
                                                            </div>
                                                        </div>
                                                        <FieldError>{form.errors.subdomain}</FieldError>
                                                    </div>
                                                ) : null}
                                            </div>
                                        </div>
                                    </label>

                                    <label
                                        className={cn(
                                            'block rounded-[1.6rem] border bg-card p-6 transition-colors',
                                            form.data.domain_type === 'custom'
                                                ? 'border-foreground shadow-[inset_0_0_0_1px_theme(colors.foreground)]'
                                                : 'border-border',
                                        )}
                                    >
                                        <div className="flex items-start gap-4">
                                            <RadioGroupItem value="custom" className="mt-1" />
                                            <div className="min-w-0 flex-1 space-y-4">
                                                <div className="space-y-1.5">
                                                    <p className="text-lg font-medium tracking-[-0.02em]">
                                                        I already have a domain
                                                    </p>
                                                    <p className="text-sm font-normal text-muted-foreground sm:text-base">
                                                        Use a domain you already own and registered elsewhere.
                                                    </p>
                                                </div>

                                                {form.data.domain_type === 'custom' ? (
                                                    <div className="space-y-5">
                                                        <div className="space-y-2">
                                                            <Input
                                                                id="custom_domain"
                                                                size="xl"
                                                                value={form.data.custom_domain}
                                                                onChange={(event) =>
                                                                    form.setData('custom_domain', event.target.value)
                                                                }
                                                                placeholder="example.com"
                                                            />
                                                            <p className="text-sm font-normal text-muted-foreground">
                                                                Enter your domain without http:// or www.
                                                            </p>
                                                            <FieldError>{form.errors.custom_domain}</FieldError>
                                                        </div>

                                                        <div className="space-y-3">
                                                            <p className="text-sm font-medium tracking-[-0.01em] sm:text-base">
                                                                How should we handle DNS?
                                                            </p>

                                                            <RadioGroup
                                                                value={form.data.dns_mode}
                                                                onValueChange={(value: 'managed' | 'external') =>
                                                                    form.setData('dns_mode', value)
                                                                }
                                                                className="gap-3"
                                                            >
                                                                <label
                                                                    className={cn(
                                                                        'block rounded-xl border bg-background p-5 transition-colors',
                                                                        form.data.dns_mode === 'managed'
                                                                            ? 'border-foreground shadow-[inset_0_0_0_1px_theme(colors.foreground)]'
                                                                            : 'border-border',
                                                                    )}
                                                                >
                                                                    <div className="flex items-start gap-3">
                                                                        <RadioGroupItem value="managed" className="mt-1" />
                                                                        <div className="space-y-1.5">
                                                                                <p className="text-sm font-medium sm:text-base">
                                                                                We'll manage your DNS
                                                                            </p>
                                                                                <p className="text-sm font-normal text-muted-foreground">
                                                                                Update your nameservers to ours. We handle DNS, SSL, and CDN automatically.
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                </label>

                                                                <label
                                                                    className={cn(
                                                                        'block rounded-xl border bg-background p-5 transition-colors',
                                                                        form.data.dns_mode === 'external'
                                                                            ? 'border-foreground shadow-[inset_0_0_0_1px_theme(colors.foreground)]'
                                                                            : 'border-border',
                                                                    )}
                                                                >
                                                                    <div className="flex items-start gap-3">
                                                                        <RadioGroupItem value="external" className="mt-1" />
                                                                        <div className="space-y-1.5">
                                                                                <p className="text-sm font-medium sm:text-base">
                                                                                I'll manage DNS myself
                                                                            </p>
                                                                                <p className="text-sm font-normal text-muted-foreground">
                                                                                Keep your current DNS provider. We'll give you records to add.
                                                                            </p>
                                                                        </div>
                                                                    </div>
                                                                </label>
                                                            </RadioGroup>

                                                            <FieldError>{form.errors.dns_mode}</FieldError>
                                                        </div>
                                                    </div>
                                                ) : null}
                                            </div>
                                        </div>
                                    </label>
                                </RadioGroup>

                                <FieldError>{form.errors.domain_type}</FieldError>

                                <div className="space-y-4 pt-2">
                                    <Button type="submit" className="h-12 w-full text-base font-medium" disabled={form.processing}>
                                        Continue
                                    </Button>

                                    <p className="text-center text-sm text-muted-foreground">
                                        You can change your domain later from settings.
                                    </p>
                                </div>
            </form>
        </AgencyOnboardingMinimalLayout>
    );
}
