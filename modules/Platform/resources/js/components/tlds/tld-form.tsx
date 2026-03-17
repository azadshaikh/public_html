import { Link } from '@inertiajs/react';
import { ArrowLeftIcon, SaveIcon } from 'lucide-react';
import type { FormEvent } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldDescription, FieldError, FieldGroup, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useAppForm } from '@/hooks/use-app-form';
import type { TldFormValues } from '../../types/platform';

type TldFormProps = {
    mode: 'create' | 'edit';
    tldRecord?: {
        id: number;
        tld: string;
    };
    initialValues: TldFormValues;
};

export default function TldForm({ mode, tldRecord, initialValues }: TldFormProps) {
    const form = useAppForm<TldFormValues>({
        defaults: initialValues,
        rememberKey: mode === 'create' ? 'platform.tlds.create' : `platform.tlds.edit.${tldRecord?.id ?? 'new'}`,
        dirtyGuard: true,
    });

    const submitUrl = mode === 'create' ? route('platform.tlds.store') : route('platform.tlds.update', tldRecord!.id);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit(mode === 'create' ? 'post' : 'put', submitUrl, {
            preserveScroll: true,
            setDefaultsOnSuccess: mode === 'edit',
            successToast: mode === 'create' ? 'TLD created successfully.' : 'TLD updated successfully.',
        });
    };

    return (
        <form className="flex flex-col gap-6" onSubmit={handleSubmit} noValidate>
            {form.dirtyGuardDialog}
            <FormErrorSummary errors={form.errors} minMessages={2} />

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>TLD profile</CardTitle>
                            <CardDescription>
                                Configure the extension, WHOIS endpoint, and storefront display data.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('tld') || undefined}>
                                        <FieldLabel htmlFor="tld">Extension</FieldLabel>
                                        <Input
                                            id="tld"
                                            value={form.data.tld}
                                            onChange={(event) => form.setField('tld', event.target.value)}
                                            onBlur={() => form.touch('tld')}
                                            aria-invalid={form.invalid('tld') || undefined}
                                            placeholder=".com"
                                        />
                                        <FieldError>{form.error('tld')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('whois_server') || undefined}>
                                        <FieldLabel htmlFor="whois_server">WHOIS server</FieldLabel>
                                        <Input
                                            id="whois_server"
                                            value={form.data.whois_server}
                                            onChange={(event) => form.setField('whois_server', event.target.value)}
                                            onBlur={() => form.touch('whois_server')}
                                            aria-invalid={form.invalid('whois_server') || undefined}
                                            placeholder="whois.verisign-grs.com"
                                        />
                                        <FieldError>{form.error('whois_server')}</FieldError>
                                    </Field>
                                </div>

                                <Field data-invalid={form.invalid('pattern') || undefined}>
                                    <FieldLabel htmlFor="pattern">Pattern</FieldLabel>
                                    <Input
                                        id="pattern"
                                        value={form.data.pattern}
                                        onChange={(event) => form.setField('pattern', event.target.value)}
                                        onBlur={() => form.touch('pattern')}
                                        aria-invalid={form.invalid('pattern') || undefined}
                                        placeholder="/^[a-z0-9-]+\\.com$/"
                                    />
                                    <FieldDescription>
                                        Optional validation pattern or matcher used during domain searches.
                                    </FieldDescription>
                                    <FieldError>{form.error('pattern')}</FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Pricing and merchandising</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <Field data-invalid={form.invalid('price') || undefined}>
                                        <FieldLabel htmlFor="price">Price</FieldLabel>
                                        <Input
                                            id="price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={form.data.price}
                                            onChange={(event) => form.setField('price', event.target.value)}
                                            onBlur={() => form.touch('price')}
                                            aria-invalid={form.invalid('price') || undefined}
                                        />
                                        <FieldError>{form.error('price')}</FieldError>
                                    </Field>

                                    <Field data-invalid={form.invalid('sale_price') || undefined}>
                                        <FieldLabel htmlFor="sale_price">Sale price</FieldLabel>
                                        <Input
                                            id="sale_price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={form.data.sale_price}
                                            onChange={(event) => form.setField('sale_price', event.target.value)}
                                            onBlur={() => form.touch('sale_price')}
                                            aria-invalid={form.invalid('sale_price') || undefined}
                                        />
                                        <FieldError>{form.error('sale_price')}</FieldError>
                                    </Field>
                                </div>

                                <Field data-invalid={form.invalid('affiliate_link') || undefined}>
                                    <FieldLabel htmlFor="affiliate_link">Affiliate link</FieldLabel>
                                    <Input
                                        id="affiliate_link"
                                        value={form.data.affiliate_link}
                                        onChange={(event) => form.setField('affiliate_link', event.target.value)}
                                        onBlur={() => form.touch('affiliate_link')}
                                        aria-invalid={form.invalid('affiliate_link') || undefined}
                                        placeholder="https://example.com/buy-domain"
                                    />
                                    <FieldError>{form.error('affiliate_link')}</FieldError>
                                </Field>

                                <Field data-invalid={form.invalid('tld_order') || undefined}>
                                    <FieldLabel htmlFor="tld_order">Display order</FieldLabel>
                                    <Input
                                        id="tld_order"
                                        type="number"
                                        value={form.data.tld_order}
                                        onChange={(event) => form.setField('tld_order', event.target.value)}
                                        onBlur={() => form.touch('tld_order')}
                                        aria-invalid={form.invalid('tld_order') || undefined}
                                    />
                                    <FieldError>{form.error('tld_order')}</FieldError>
                                </Field>

                                <FieldGroup>
                                    <Field orientation="horizontal">
                                        <FieldLabel htmlFor="status">Active</FieldLabel>
                                        <FieldDescription>Allow this TLD to appear in active storefront selections.</FieldDescription>
                                        <Switch id="status" checked={form.data.status} onCheckedChange={(checked) => form.setField('status', checked)} />
                                    </Field>

                                    <Field orientation="horizontal">
                                        <FieldLabel htmlFor="is_main">Primary extension</FieldLabel>
                                        <FieldDescription>Mark this extension as part of the default search set.</FieldDescription>
                                        <Switch id="is_main" checked={form.data.is_main} onCheckedChange={(checked) => form.setField('is_main', checked)} />
                                    </Field>

                                    <Field orientation="horizontal">
                                        <FieldLabel htmlFor="is_suggested">Suggested</FieldLabel>
                                        <FieldDescription>Show this TLD as a recommended alternative.</FieldDescription>
                                        <Switch id="is_suggested" checked={form.data.is_suggested} onCheckedChange={(checked) => form.setField('is_suggested', checked)} />
                                    </Field>
                                </FieldGroup>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button variant="outline" asChild>
                    <Link href={route('platform.tlds.index', { status: 'all' })}>
                        <ArrowLeftIcon data-icon="inline-start" />
                        Back to TLDs
                    </Link>
                </Button>

                <Button type="submit" disabled={form.processing}>
                    {form.processing ? <Spinner data-icon="inline-start" /> : <SaveIcon data-icon="inline-start" />}
                    {mode === 'create' ? 'Create TLD' : 'Save changes'}
                </Button>
            </div>
        </form>
    );
}
