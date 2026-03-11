import { Link, router } from '@inertiajs/react';
import {
    ArrowLeftIcon,
    ExternalLinkIcon,
    PencilIcon,
    PlayIcon,
    Trash2Icon,
} from 'lucide-react';
import MovieController from '@/actions/App/Http/Controllers/Demo/MovieController';
import MovieArtwork from '@/components/demo/movies/movie-artwork';
import Heading from '@/components/heading';
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
import type { BreadcrumbItem, MovieShowPageProps } from '@/types';

export default function ShowMovieDemo({ movie }: MovieShowPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Movies demo',
            href: MovieController.index(),
        },
        {
            title: movie.title,
            href: MovieController.show(movie.id),
        },
    ];

    const handleDelete = () => {
        if (!window.confirm(`Delete ${movie.title}? This also removes uploaded poster and backdrop files.`)) {
            return;
        }

        router.delete(MovieController.destroy(movie.id).url);
    };

    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title={movie.title}
            description={movie.tagline ?? 'Inspect the full movie demo record and media attachments.'}
        >
            <div className="flex flex-col gap-8">
                <section className="grid gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(320px,1fr)]">
                    <Card className="border-none bg-gradient-to-br from-foreground to-foreground/85 text-background shadow-none">
                        <CardHeader className="gap-4">
                            <div className="flex flex-wrap gap-2">
                                <Badge variant="secondary" className="bg-background/15 text-background hover:bg-background/15">
                                    {movie.status_label}
                                </Badge>
                                <Badge variant="secondary" className="bg-background/15 text-background hover:bg-background/15">
                                    Rated {movie.rating}
                                </Badge>
                                {movie.is_featured && (
                                    <Badge variant="secondary" className="bg-background/15 text-background hover:bg-background/15">
                                        Featured
                                    </Badge>
                                )}
                                {movie.is_now_showing && (
                                    <Badge variant="secondary" className="bg-background/15 text-background hover:bg-background/15">
                                        Now showing
                                    </Badge>
                                )}
                            </div>
                            <div className="flex flex-col gap-3">
                                <CardTitle className="text-3xl font-semibold tracking-tight text-background md:text-4xl">
                                    {movie.title}
                                </CardTitle>
                                <CardDescription className="max-w-2xl text-background/75">
                                    {movie.tagline ?? movie.synopsis}
                                </CardDescription>
                            </div>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div className="grid gap-3 sm:grid-cols-3">
                                <Metric label="Release" value={movie.release_date_label ?? 'TBD'} />
                                <Metric label="Runtime" value={movie.runtime_label ?? 'TBD'} />
                                <Metric label="IMDb" value={movie.imdb_rating ?? '—'} />
                            </div>
                            <div className="flex flex-wrap gap-3">
                                <Button asChild variant="secondary" className="bg-background text-foreground hover:bg-background/90">
                                    <Link href={MovieController.edit(movie.id)}>
                                        <PencilIcon data-icon="inline-start" />
                                        Edit movie
                                    </Link>
                                </Button>
                                <Button asChild variant="outline" className="border-background/20 bg-transparent text-background hover:bg-background/10 hover:text-background">
                                    <Link href={MovieController.index()}>
                                        <ArrowLeftIcon data-icon="inline-start" />
                                        Back to list
                                    </Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Quick facts</CardTitle>
                            <CardDescription>High-signal data points from the record.</CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4 text-sm">
                            <Fact label="Director" value={movie.director} />
                            <Fact label="Studio" value={movie.studio ?? 'Not provided'} />
                            <Fact label="Language" value={movie.language_label ?? 'Not provided'} />
                            <Fact label="Premiere time" value={movie.release_time ?? 'Not provided'} />
                            <Fact label="Ticket price" value={movie.ticket_price_formatted ?? 'Not provided'} />
                        </CardContent>
                    </Card>
                </section>

                <section className="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
                    <div className="grid gap-6">
                        <MovieArtwork
                            title={movie.title}
                            src={movie.poster_url}
                            variant="poster"
                        />
                        <Card>
                            <CardHeader>
                                <CardTitle>Flags</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-wrap gap-2">
                                <Badge variant={movie.is_family_friendly ? 'default' : 'outline'}>
                                    {movie.is_family_friendly ? 'Family friendly' : 'General audience unknown'}
                                </Badge>
                                <Badge variant={movie.has_post_credit_scene ? 'default' : 'outline'}>
                                    {movie.has_post_credit_scene ? 'Post-credit scene' : 'No post-credit scene'}
                                </Badge>
                            </CardContent>
                        </Card>
                    </div>

                    <div className="grid gap-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Backdrop</CardTitle>
                                <CardDescription>Secondary upload field for testing media replacement.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <MovieArtwork
                                    title={movie.title}
                                    src={movie.backdrop_url}
                                    variant="backdrop"
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Synopsis</CardTitle>
                                <CardDescription>Main long-form text field.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p className="whitespace-pre-line text-sm leading-7 text-muted-foreground">
                                    {movie.synopsis}
                                </p>
                            </CardContent>
                        </Card>

                        {movie.notes && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Internal notes</CardTitle>
                                    <CardDescription>Optional textarea content for admin-style workflows.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <p className="whitespace-pre-line text-sm leading-7 text-muted-foreground">
                                        {movie.notes}
                                    </p>
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle>Taxonomy and distribution</CardTitle>
                                <CardDescription>Grouped array data rendered as badges.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-6">
                                <BadgeGroup title="Genres" values={movie.genres} />
                                <BadgeGroup title="Spoken languages" values={movie.spoken_languages} />
                                <BadgeGroup title="Formats" values={movie.available_formats} />
                                <BadgeGroup title="Streaming platforms" values={movie.streaming_platforms} />
                                <BadgeGroup title="Content warnings" values={movie.content_warnings} emptyLabel="None listed" />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Commercial performance</CardTitle>
                                <CardDescription>Numeric fields and URL metadata.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-6 lg:grid-cols-2">
                                <FactBlock
                                    title="Budget"
                                    value={movie.budget_formatted ?? 'Not provided'}
                                    description="Production budget"
                                />
                                <FactBlock
                                    title="Box office"
                                    value={movie.box_office_formatted ?? 'Not provided'}
                                    description="Worldwide gross"
                                />
                                <FactBlock
                                    title="Metascore"
                                    value={movie.metascore !== null ? `${movie.metascore}` : '—'}
                                    description="Critic aggregate"
                                />
                                <FactBlock
                                    title="Audience score"
                                    value={movie.audience_score !== null ? `${movie.audience_score}` : '—'}
                                    description="Audience aggregate"
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Outbound links</CardTitle>
                                <CardDescription>URL fields for external navigation testing.</CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-wrap gap-3">
                                {movie.trailer_url && (
                                    <Button asChild variant="outline">
                                        <a href={movie.trailer_url} target="_blank" rel="noreferrer">
                                            <PlayIcon data-icon="inline-start" />
                                            Watch trailer
                                        </a>
                                    </Button>
                                )}
                                {movie.official_site && (
                                    <Button asChild variant="outline">
                                        <a href={movie.official_site} target="_blank" rel="noreferrer">
                                            <ExternalLinkIcon data-icon="inline-start" />
                                            Official site
                                        </a>
                                    </Button>
                                )}
                                {!movie.trailer_url && !movie.official_site && (
                                    <div className="text-sm text-muted-foreground">
                                        No external links were provided.
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </section>

                <div className="flex flex-wrap gap-3">
                    <Button asChild variant="outline">
                        <Link href={MovieController.index()}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back to movies
                        </Link>
                    </Button>
                    <Button asChild>
                        <Link href={MovieController.edit(movie.id)}>
                            <PencilIcon data-icon="inline-start" />
                            Edit movie
                        </Link>
                    </Button>
                    <Button variant="destructive" onClick={handleDelete}>
                        <Trash2Icon data-icon="inline-start" />
                        Delete movie
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}

function Metric({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-2xl font-semibold">{value}</div>
            <div className="text-sm text-background/70">{label}</div>
        </div>
    );
}

function Fact({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-center justify-between gap-4 border-b pb-3 last:border-b-0 last:pb-0">
            <div className="text-muted-foreground">{label}</div>
            <div className="font-medium text-foreground">{value}</div>
        </div>
    );
}

function FactBlock({
    title,
    value,
    description,
}: {
    title: string;
    value: string;
    description: string;
}) {
    return (
        <div className="rounded-2xl border bg-muted/30 p-4">
            <div className="text-sm text-muted-foreground">{title}</div>
            <div className="mt-1 text-2xl font-semibold">{value}</div>
            <div className="mt-1 text-xs text-muted-foreground">{description}</div>
        </div>
    );
}

function BadgeGroup({
    title,
    values,
    emptyLabel = 'Not provided',
}: {
    title: string;
    values: string[];
    emptyLabel?: string;
}) {
    return (
        <div className="grid gap-2">
            <Heading variant="small" title={title} />
            <div className="flex flex-wrap gap-2">
                {values.length > 0 ? (
                    values.map((value) => (
                        <Badge key={value} variant="outline">
                            {value}
                        </Badge>
                    ))
                ) : (
                    <div className="text-sm text-muted-foreground">{emptyLabel}</div>
                )}
            </div>
        </div>
    );
}
