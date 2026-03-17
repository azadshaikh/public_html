import { Link } from '@inertiajs/react';
import {
    ArrowRightIcon,
    BotIcon,
    BracesIcon,
    CheckCircle2Icon,
    Globe2Icon,
    MapPinnedIcon,
    ScanSearchIcon,
    SearchCheckIcon,
    Share2Icon,
    TagsIcon,
    TriangleAlertIcon,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { SeoDashboardPageProps } from '../../types/seo';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'SEO', href: route('seo.dashboard') },
];

const quickLinkIcons = {
    titlesmeta: TagsIcon,
    localseo: MapPinnedIcon,
    socialmedia: Share2Icon,
    sitemap: ScanSearchIcon,
    robots: BotIcon,
    schema: BracesIcon,
} as const;

function formatDate(value: string | null): string {
    if (!value) {
        return 'Not generated yet';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function SeoDashboardPage({
    quickLinks,
    searchEngineEnabled,
    sitemapStatus,
    stats,
    titlesMetaHref,
}: SeoDashboardPageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="SEO Dashboard"
            description="Monitor indexability, sitemap health, and jump into the SEO settings that need attention fastest."
            headerActions={
                <Button asChild>
                    <Link href={titlesMetaHref}>Open titles & meta</Link>
                </Button>
            }
        >
            <div className="flex flex-col gap-6">
                <Alert
                    variant={searchEngineEnabled ? 'default' : 'destructive'}
                >
                    {searchEngineEnabled ? (
                        <SearchCheckIcon className="size-4" />
                    ) : (
                        <TriangleAlertIcon className="size-4" />
                    )}
                    <AlertTitle>
                        {searchEngineEnabled
                            ? 'Search engines can index your site'
                            : 'Search engines are currently blocked'}
                    </AlertTitle>
                    <AlertDescription className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                        <span>
                            {searchEngineEnabled
                                ? 'Your current global SEO configuration allows crawlers to discover and index public pages.'
                                : 'The site is sending noindex, nofollow defaults. Update Titles & Meta when you are ready to go live.'}
                        </span>
                        <Button
                            asChild
                            size="default"
                            variant={
                                searchEngineEnabled ? 'outline' : 'secondary'
                            }
                        >
                            <Link href={titlesMetaHref}>
                                {searchEngineEnabled
                                    ? 'Review indexing settings'
                                    : 'Enable indexing'}
                            </Link>
                        </Button>
                    </AlertDescription>
                </Alert>

                <section className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <CardDescription>
                                        robots.txt
                                    </CardDescription>
                                    <CardTitle className="mt-2 text-2xl">
                                        {stats.robots_txt_exists
                                            ? 'Present'
                                            : 'Missing'}
                                    </CardTitle>
                                </div>
                                <div className="rounded-xl border bg-muted/50 p-2">
                                    <BotIcon className="size-5 text-primary" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Badge
                                variant={
                                    stats.robots_txt_exists
                                        ? 'secondary'
                                        : 'destructive'
                                }
                            >
                                {stats.robots_txt_exists
                                    ? 'Crawler rules published'
                                    : 'Needs attention'}
                            </Badge>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <CardDescription>Sitemap</CardDescription>
                                    <CardTitle className="mt-2 text-2xl">
                                        {stats.sitemap_exists
                                            ? sitemapStatus.total_urls
                                            : 0}
                                    </CardTitle>
                                </div>
                                <div className="rounded-xl border bg-muted/50 p-2">
                                    <Globe2Icon className="size-5 text-primary" />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm text-muted-foreground">
                            <div>
                                {stats.sitemap_exists
                                    ? 'URLs currently published in XML sitemaps'
                                    : 'No sitemap has been generated yet'}
                            </div>
                            <div>
                                Last generated:{' '}
                                {formatDate(stats.sitemap_last_generated)}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <CardDescription>Indexing</CardDescription>
                                    <CardTitle className="mt-2 text-2xl">
                                        {searchEngineEnabled
                                            ? 'Enabled'
                                            : 'Blocked'}
                                    </CardTitle>
                                </div>
                                <div className="rounded-xl border bg-muted/50 p-2">
                                    {searchEngineEnabled ? (
                                        <CheckCircle2Icon className="size-5 text-primary" />
                                    ) : (
                                        <TriangleAlertIcon className="size-5 text-destructive" />
                                    )}
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Badge
                                variant={
                                    searchEngineEnabled
                                        ? 'secondary'
                                        : 'destructive'
                                }
                            >
                                {searchEngineEnabled
                                    ? 'Production-ready visibility'
                                    : 'Hidden from search engines'}
                            </Badge>
                        </CardContent>
                    </Card>
                </section>

                <section className="flex flex-col gap-4">
                    <div className="space-y-1">
                        <h2 className="text-lg font-semibold">SEO settings</h2>
                        <p className="text-sm text-muted-foreground">
                            Open any section to manage metadata, crawlers,
                            social previews, and structured data.
                        </p>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {quickLinks.map((link) => {
                            const Icon = quickLinkIcons[link.key];

                            return (
                                <Card
                                    key={link.key}
                                    className="transition-colors hover:border-primary/40"
                                >
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="rounded-xl border bg-muted/50 p-2">
                                                <Icon className="size-5 text-primary" />
                                            </div>
                                            <Button
                                                asChild
                                                variant="ghost"
                                                className="-mr-2"
                                            >
                                                <Link
                                                    href={link.href}
                                                    aria-label={`Open ${link.label}`}
                                                >
                                                    <ArrowRightIcon className="size-4" />
                                                </Link>
                                            </Button>
                                        </div>
                                        <div className="space-y-1">
                                            <CardTitle className="text-base">
                                                {link.label}
                                            </CardTitle>
                                            <CardDescription>
                                                {link.description}
                                            </CardDescription>
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <Button
                                            asChild
                                            variant="outline"
                                            className="w-full justify-between"
                                        >
                                            <Link href={link.href}>
                                                Manage {link.label}
                                                <ArrowRightIcon className="size-4" />
                                            </Link>
                                        </Button>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}
