import { Link, usePage } from '@inertiajs/react';
import {
    FileTextIcon,
    HomeIcon,
    InfoIcon,
    NewspaperIcon,
    PlusIcon,
    SaveIcon,
    ScrollTextIcon,
} from 'lucide-react';
import type { ComponentType, FormEvent, ReactNode } from 'react';
import { FormErrorSummary } from '@/components/forms/form-error-summary';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
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
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { useAppForm } from '@/hooks/use-app-form';
import AppLayout from '@/layouts/app-layout';
import type { AuthenticatedSharedData, BreadcrumbItem } from '@/types';
import type { DefaultPagesPageProps } from '../../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'CMS', href: route('cms.posts.index', 'all') },
    { title: 'Default Pages', href: route('cms.settings.default-pages') },
];

type DefaultPagesFormValues = DefaultPagesPageProps['settings'];
type DefaultPagesFieldName = keyof DefaultPagesFormValues & string;

type SettingCardProps = {
    title: string;
    description: string;
    icon: ComponentType<{ className?: string }>;
    children: ReactNode;
};

function SettingCard({
    title,
    description,
    icon: Icon,
    children,
}: SettingCardProps) {
    return (
        <Card>
            <CardHeader>
                <div className="flex items-start gap-3">
                    <div className="rounded-lg border bg-muted p-2 text-muted-foreground">
                        <Icon className="size-4" />
                    </div>
                    <div className="space-y-1">
                        <CardTitle>{title}</CardTitle>
                        <CardDescription>{description}</CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <FieldGroup>{children}</FieldGroup>
            </CardContent>
        </Card>
    );
}

function PageSelectField({
    id,
    label,
    description,
    value,
    error,
    invalid,
    options,
    onChange,
    onBlur,
}: {
    id: DefaultPagesFieldName;
    label: string;
    description: string;
    value: string;
    error?: string;
    invalid: boolean;
    options: DefaultPagesPageProps['pageOptions'];
    onChange: (value: string) => void;
    onBlur: () => void;
}) {
    return (
        <Field data-invalid={invalid || undefined}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <FieldDescription>{description}</FieldDescription>
            <NativeSelect
                id={id}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                onBlur={onBlur}
                aria-invalid={invalid || undefined}
                className="w-full"
            >
                {options.map(
                    (option: DefaultPagesPageProps['pageOptions'][number]) => (
                        <NativeSelectOption
                            key={`${id}-${option.value}`}
                            value={String(option.value)}
                        >
                            {option.label}
                        </NativeSelectOption>
                    ),
                )}
            </NativeSelect>
            <FieldError>{error}</FieldError>
        </Field>
    );
}

export default function DefaultPages({
    pageOptions,
    settings,
    publishedPageCount,
}: DefaultPagesPageProps) {
    const page = usePage<AuthenticatedSharedData>();
    const canManageDefaultPages = page.props.auth.abilities.manageDefaultPages;

    const form = useAppForm<DefaultPagesFormValues>({
        defaults: settings,
        rememberKey: 'cms.settings.default-pages',
        dirtyGuard: { enabled: true },
        rules: {
            blog_base_url: [
                (value) => {
                    if (typeof value !== 'string' || value.trim() === '') {
                        return undefined;
                    }

                    return /^[a-z0-9-]*$/.test(value)
                        ? undefined
                        : 'Blog URL slug may only contain lowercase letters, numbers, and hyphens.';
                },
                (value) => {
                    if (typeof value !== 'string') {
                        return undefined;
                    }

                    return value.length <= 50
                        ? undefined
                        : 'Blog URL slug must not exceed 50 characters.';
                },
            ],
            home_page: [
                (value, data) => {
                    if (
                        typeof value !== 'string' ||
                        typeof data.blogs_page !== 'string'
                    ) {
                        return undefined;
                    }

                    if (
                        value !== '' &&
                        value === data.blogs_page &&
                        data.blog_same_as_home === false
                    ) {
                        return 'Homepage and blog page should be different unless blog on homepage is enabled.';
                    }

                    return undefined;
                },
            ],
            blogs_page: [
                (value, data) => {
                    if (data.blog_same_as_home) {
                        return undefined;
                    }

                    if (
                        typeof value !== 'string' ||
                        typeof data.home_page !== 'string'
                    ) {
                        return undefined;
                    }

                    if (value !== '' && value === data.home_page) {
                        return 'Blog page should be different from the homepage.';
                    }

                    return undefined;
                },
            ],
        },
    });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.submit('put', route('cms.settings.default-pages.update'), {
            preserveScroll: true,
            setDefaultsOnSuccess: true,
            successToast: {
                title: 'Default pages updated',
                description:
                    'Your default page assignments have been saved successfully.',
            },
        });
    };

    const assignments = [
        { label: 'Homepage', value: form.data.home_page },
        {
            label: form.data.blog_same_as_home ? 'Blog listing' : 'Blog page',
            value: form.data.blog_same_as_home
                ? form.data.home_page
                : form.data.blogs_page,
        },
        { label: 'Contact', value: form.data.contact_page },
        { label: 'About', value: form.data.about_page },
        { label: 'Privacy policy', value: form.data.privacy_policy_page },
        { label: 'Terms', value: form.data.terms_of_service_page },
    ];

    const activeAssignments = assignments.filter((item) => item.value !== '');

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Default Pages"
            description="Choose which CMS pages power your homepage, blog, key business pages, and legal links."
            headerActions={
                <div className="flex flex-wrap gap-3">
                    <Button variant="outline" asChild>
                        <Link href={route('cms.pages.index', 'all')}>
                            <FileTextIcon data-icon="inline-start" />
                            View pages
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={route('cms.pages.create')}>
                            <PlusIcon data-icon="inline-start" />
                            Create page
                        </Link>
                    </Button>
                </div>
            }
        >
            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <form
                    noValidate
                    onSubmit={handleSubmit}
                    className="flex min-w-0 flex-col gap-6"
                >
                    {form.dirtyGuardDialog}
                    <FormErrorSummary errors={form.errors} minMessages={2} />

                    {publishedPageCount === 0 ? (
                        <Alert>
                            <InfoIcon />
                            <AlertTitle>
                                No published pages available
                            </AlertTitle>
                            <AlertDescription>
                                Publish at least one page before assigning it as
                                the homepage, blog page, or a legal page.
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    <SettingCard
                        title="Homepage & blog"
                        description="Control the front page experience and where your post archive lives."
                        icon={HomeIcon}
                    >
                        <PageSelectField
                            id="home_page"
                            label="Homepage"
                            description="Select the page shown at the root URL. Leave empty to show the latest posts instead."
                            value={form.data.home_page}
                            error={form.error('home_page')}
                            invalid={form.invalid('home_page')}
                            options={pageOptions}
                            onChange={(value) =>
                                form.setField('home_page', value)
                            }
                            onBlur={() => form.touch('home_page')}
                        />

                        <Field orientation="horizontal">
                            <Switch
                                id="blog_same_as_home"
                                checked={form.data.blog_same_as_home}
                                onCheckedChange={(checked) => {
                                    const isEnabled = checked === true;
                                    form.setField(
                                        'blog_same_as_home',
                                        isEnabled,
                                    );
                                    if (isEnabled) {
                                        form.setField('blogs_page', '');
                                    }
                                }}
                            />
                            <div className="flex flex-col gap-1">
                                <FieldLabel htmlFor="blog_same_as_home">
                                    Blog on homepage
                                </FieldLabel>
                                <FieldDescription>
                                    When enabled, the homepage also becomes the
                                    blog listing page.
                                </FieldDescription>
                            </div>
                        </Field>

                        {!form.data.blog_same_as_home ? (
                            <>
                                <PageSelectField
                                    id="blogs_page"
                                    label="Blog page"
                                    description="Pick a dedicated page for your post archive and blog landing content."
                                    value={form.data.blogs_page}
                                    error={form.error('blogs_page')}
                                    invalid={form.invalid('blogs_page')}
                                    options={pageOptions}
                                    onChange={(value) =>
                                        form.setField('blogs_page', value)
                                    }
                                    onBlur={() => form.touch('blogs_page')}
                                />

                                <Field
                                    data-invalid={
                                        form.invalid('blog_base_url') ||
                                        undefined
                                    }
                                >
                                    <FieldLabel htmlFor="blog_base_url">
                                        Blog URL slug
                                    </FieldLabel>
                                    <FieldDescription>
                                        Controls the archive URL segment, such
                                        as /blog or /news.
                                    </FieldDescription>
                                    <input
                                        id="blog_base_url"
                                        value={form.data.blog_base_url}
                                        onChange={(event) =>
                                            form.setField(
                                                'blog_base_url',
                                                event.target.value.toLowerCase(),
                                            )
                                        }
                                        onBlur={() =>
                                            form.touch('blog_base_url')
                                        }
                                        aria-invalid={
                                            form.invalid('blog_base_url') ||
                                            undefined
                                        }
                                        placeholder="blog"
                                        className="flex h-9 w-full rounded-lg border border-input bg-transparent px-3 py-2 text-sm transition-colors outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:bg-input/30"
                                    />
                                    <FieldError>
                                        {form.error('blog_base_url')}
                                    </FieldError>
                                </Field>
                            </>
                        ) : null}
                    </SettingCard>

                    <SettingCard
                        title="Important pages"
                        description="Assign the core informational pages used across your site."
                        icon={NewspaperIcon}
                    >
                        <PageSelectField
                            id="contact_page"
                            label="Contact page"
                            description="Use this for your contact form, office details, or support information."
                            value={form.data.contact_page}
                            error={form.error('contact_page')}
                            invalid={form.invalid('contact_page')}
                            options={pageOptions}
                            onChange={(value) =>
                                form.setField('contact_page', value)
                            }
                            onBlur={() => form.touch('contact_page')}
                        />

                        <PageSelectField
                            id="about_page"
                            label="About page"
                            description="Use this for your company story, mission, and team overview."
                            value={form.data.about_page}
                            error={form.error('about_page')}
                            invalid={form.invalid('about_page')}
                            options={pageOptions}
                            onChange={(value) =>
                                form.setField('about_page', value)
                            }
                            onBlur={() => form.touch('about_page')}
                        />
                    </SettingCard>

                    <SettingCard
                        title="Legal pages"
                        description="Keep your footer and compliance links pointed at the right content."
                        icon={ScrollTextIcon}
                    >
                        <PageSelectField
                            id="privacy_policy_page"
                            label="Privacy policy"
                            description="Recommended for consent, tracking, and privacy compliance requirements."
                            value={form.data.privacy_policy_page}
                            error={form.error('privacy_policy_page')}
                            invalid={form.invalid('privacy_policy_page')}
                            options={pageOptions}
                            onChange={(value) =>
                                form.setField('privacy_policy_page', value)
                            }
                            onBlur={() => form.touch('privacy_policy_page')}
                        />

                        <PageSelectField
                            id="terms_of_service_page"
                            label="Terms of service"
                            description="Assign your terms and conditions, service rules, or purchase terms."
                            value={form.data.terms_of_service_page}
                            error={form.error('terms_of_service_page')}
                            invalid={form.invalid('terms_of_service_page')}
                            options={pageOptions}
                            onChange={(value) =>
                                form.setField('terms_of_service_page', value)
                            }
                            onBlur={() => form.touch('terms_of_service_page')}
                        />
                    </SettingCard>

                    <Card>
                        <CardFooter className="flex flex-wrap items-center justify-between gap-3 py-6">
                            <p className="text-sm text-muted-foreground">
                                Changes update the page assignments used by
                                navigation, archive routing, and legal links.
                            </p>
                            <Button
                                type="submit"
                                disabled={
                                    form.processing || !canManageDefaultPages
                                }
                            >
                                {form.processing ? (
                                    <Spinner />
                                ) : (
                                    <SaveIcon data-icon="inline-start" />
                                )}
                                Save settings
                            </Button>
                        </CardFooter>
                    </Card>
                </form>

                <div className="flex flex-col gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Current assignments</CardTitle>
                            <CardDescription>
                                Quick overview of how many key page slots are
                                currently configured.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4">
                            <div className="flex items-center justify-between rounded-lg border bg-muted/30 px-4 py-3">
                                <div>
                                    <p className="text-sm font-medium">
                                        Published pages
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Available to assign right now
                                    </p>
                                </div>
                                <Badge variant="secondary">
                                    {publishedPageCount}
                                </Badge>
                            </div>
                            <div className="flex items-center justify-between rounded-lg border bg-muted/30 px-4 py-3">
                                <div>
                                    <p className="text-sm font-medium">
                                        Assigned slots
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Out of 6 possible assignments
                                    </p>
                                </div>
                                <Badge variant="secondary">
                                    {activeAssignments.length}
                                </Badge>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {activeAssignments.length > 0 ? (
                                    activeAssignments.map((item) => (
                                        <Badge
                                            key={item.label}
                                            variant="outline"
                                        >
                                            {item.label}
                                        </Badge>
                                    ))
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No page assignments yet.
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>How it works</CardTitle>
                            <CardDescription>
                                A few practical notes before saving your
                                defaults.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4 text-sm text-muted-foreground">
                            <div className="space-y-1">
                                <p className="font-medium text-foreground">
                                    Homepage
                                </p>
                                <p>
                                    If no homepage is selected, visitors see
                                    your latest posts at the site root.
                                </p>
                            </div>
                            <div className="space-y-1">
                                <p className="font-medium text-foreground">
                                    Blog page
                                </p>
                                <p>
                                    Enable blog on homepage to merge the
                                    homepage and post archive, or assign a
                                    separate page for a dedicated archive.
                                </p>
                            </div>
                            <div className="space-y-1">
                                <p className="font-medium text-foreground">
                                    Legal links
                                </p>
                                <p>
                                    Privacy and terms pages are commonly
                                    surfaced in the footer and other compliance
                                    touchpoints.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
