import { Form, Link, router } from '@inertiajs/react';
import {
  EyeIcon,
  PencilIcon,
  PlusIcon,
  SearchIcon,
  SparklesIcon,
  Trash2Icon,
} from 'lucide-react';
import MovieController from '@/actions/App/Http/Controllers/Demo/MovieController';
import MovieArtwork from '@/components/demo/movies/movie-artwork';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import {
  Empty,
  EmptyContent,
  EmptyDescription,
  EmptyHeader,
  EmptyMedia,
  EmptyTitle,
} from '@/components/ui/empty';
import {
  InputGroup,
  InputGroupAddon,
  InputGroupInput,
} from '@/components/ui/input-group';
import {
  NativeSelect,
  NativeSelectOption,
} from '@/components/ui/native-select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type {
  BreadcrumbItem,
  MovieListItem,
  MoviesIndexPageProps,
} from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Movies demo',
    href: MovieController.index(),
  },
];

export default function MoviesDemoIndex({
  filters,
  movies,
  options,
  stats,
}: MoviesIndexPageProps) {
  const handleDelete = (movie: MovieListItem) => {
    if (
      !window.confirm(
        `Delete ${movie.title}? This will also remove uploaded media.`,
      )
    ) {
      return;
    }

    router.delete(MovieController.destroy(movie.id).url, {
      preserveScroll: true,
    });
  };

  return (
    <AppLayout
      breadcrumbs={breadcrumbs}
      title="Movies demo"
      description="Browse, filter, and manage a large demo movie CRUD with mixed field types."
    >
      <div className="flex flex-col gap-8">
        <section className="grid gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(320px,1fr)]">
          <Card className="border-none bg-gradient-to-br from-foreground to-foreground/85 text-background shadow-none">
            <CardHeader>
              <Badge
                variant="secondary"
                className="w-fit bg-background/15 text-background hover:bg-background/15"
              >
                Demo CRUD
              </Badge>
              <CardTitle className="text-3xl font-semibold tracking-tight text-background md:text-4xl">
                Movies catalogue playground
              </CardTitle>
              <CardDescription className="max-w-2xl text-background/75">
                A feature-rich demo CRUD for testing text inputs, numeric
                fields, toggles, grouped checkboxes, filters, and image uploads.
              </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
              <div className="grid gap-3 sm:grid-cols-3">
                <div>
                  <div className="text-2xl font-semibold">{stats.total}</div>
                  <div className="text-sm text-background/70">
                    total demo titles
                  </div>
                </div>
                <div>
                  <div className="text-2xl font-semibold">{stats.released}</div>
                  <div className="text-sm text-background/70">
                    released entries
                  </div>
                </div>
                <div>
                  <div className="text-2xl font-semibold">
                    {stats.average_imdb}
                  </div>
                  <div className="text-sm text-background/70">
                    average IMDb score
                  </div>
                </div>
              </div>
              <Button
                asChild
                variant="secondary"
                className="bg-background text-foreground hover:bg-background/90"
              >
                <Link href={MovieController.create()}>
                  <PlusIcon data-icon="inline-start" />
                  Add movie
                </Link>
              </Button>
            </CardContent>
          </Card>

          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <Card>
              <CardHeader>
                <CardTitle>Featured titles</CardTitle>
                <CardDescription>
                  Titles marked for spotlight placement.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="text-3xl font-semibold">{stats.featured}</div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader>
                <CardTitle>Now showing</CardTitle>
                <CardDescription>
                  Useful for testing badge and filter combinations.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div className="text-3xl font-semibold">
                  {stats.now_showing}
                </div>
              </CardContent>
            </Card>
          </div>
        </section>

        <Card>
          <CardHeader>
            <CardTitle>Filter catalogue</CardTitle>
            <CardDescription>
              Search and combine filters to exercise the query-string state.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Form
              {...MovieController.index.form()}
              method="get"
              options={{ preserveScroll: true }}
              className="grid gap-4 lg:grid-cols-[minmax(0,2fr)_repeat(4,minmax(0,1fr))_auto]"
            >
              <InputGroup className="w-full">
                <InputGroupAddon>
                  <SearchIcon />
                </InputGroupAddon>
                <InputGroupInput
                  name="search"
                  defaultValue={filters.search}
                  placeholder="Search title, slug, director, or studio"
                />
              </InputGroup>

              <NativeSelect
                className="w-full"
                name="status"
                defaultValue={filters.status}
              >
                <NativeSelectOption value="">All statuses</NativeSelectOption>
                {options.statusOptions.map((option) => (
                  <NativeSelectOption key={option.value} value={option.value}>
                    {option.label}
                  </NativeSelectOption>
                ))}
              </NativeSelect>

              <NativeSelect
                className="w-full"
                name="rating"
                defaultValue={filters.rating}
              >
                <NativeSelectOption value="">All ratings</NativeSelectOption>
                {options.ratingOptions.map((option) => (
                  <NativeSelectOption key={option.value} value={option.value}>
                    {option.label}
                  </NativeSelectOption>
                ))}
              </NativeSelect>

              <NativeSelect
                className="w-full"
                name="genre"
                defaultValue={filters.genre}
              >
                <NativeSelectOption value="">All genres</NativeSelectOption>
                {options.genreOptions.map((option) => (
                  <NativeSelectOption key={option.value} value={option.value}>
                    {option.label}
                  </NativeSelectOption>
                ))}
              </NativeSelect>

              <NativeSelect
                className="w-full"
                name="featured"
                defaultValue={filters.featured}
              >
                {options.featuredOptions.map((option) => (
                  <NativeSelectOption key={option.label} value={option.value}>
                    {option.label}
                  </NativeSelectOption>
                ))}
              </NativeSelect>

              <div className="flex gap-2">
                <NativeSelect
                  className="w-full"
                  name="sort"
                  defaultValue={filters.sort}
                >
                  {options.sortOptions.map((option) => (
                    <NativeSelectOption key={option.value} value={option.value}>
                      {option.label}
                    </NativeSelectOption>
                  ))}
                </NativeSelect>
                <Button type="submit">Apply</Button>
              </div>
            </Form>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
              <div className="flex flex-col gap-1">
                <CardTitle>Catalogue results</CardTitle>
                <CardDescription>
                  Showing {movies.from ?? 0}–{movies.to ?? 0} of {movies.total}{' '}
                  demo titles.
                </CardDescription>
              </div>
              <Button asChild variant="outline">
                <Link href={MovieController.create()}>
                  <SparklesIcon data-icon="inline-start" />
                  New demo entry
                </Link>
              </Button>
            </div>
          </CardHeader>
          <CardContent className="grid gap-6">
            {movies.data.length === 0 ? (
              <Empty>
                <EmptyHeader>
                  <EmptyMedia variant="icon">
                    <SparklesIcon />
                  </EmptyMedia>
                  <EmptyTitle>No movies matched the current filters</EmptyTitle>
                  <EmptyDescription>
                    Clear the filters or create the first demo movie entry.
                  </EmptyDescription>
                </EmptyHeader>
                <EmptyContent>
                  <Button asChild>
                    <Link href={MovieController.create()}>
                      <PlusIcon data-icon="inline-start" />
                      Create movie
                    </Link>
                  </Button>
                </EmptyContent>
              </Empty>
            ) : (
              <>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Movie</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Director</TableHead>
                      <TableHead>Release</TableHead>
                      <TableHead>Scores</TableHead>
                      <TableHead>Tags</TableHead>
                      <TableHead className="text-right">Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {movies.data.map((movie) => (
                      <TableRow key={movie.id}>
                        <TableCell>
                          <div className="flex min-w-[240px] items-center gap-3">
                            <div className="w-16 shrink-0">
                              <MovieArtwork
                                title={movie.title}
                                src={movie.poster_url}
                                className="rounded-xl"
                              />
                            </div>
                            <div className="flex flex-col gap-1">
                              <div className="font-medium">{movie.title}</div>
                              <div className="text-xs text-muted-foreground">
                                /{movie.slug}
                              </div>
                              <div className="flex flex-wrap gap-2">
                                {movie.is_featured && <Badge>Featured</Badge>}
                                {movie.is_now_showing && (
                                  <Badge variant="secondary">Now showing</Badge>
                                )}
                              </div>
                            </div>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-col gap-1">
                            <Badge variant={statusVariant(movie.status)}>
                              {movie.status_label}
                            </Badge>
                            <div className="text-xs text-muted-foreground">
                              Rated {movie.rating}
                            </div>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-col gap-1">
                            <div>{movie.director}</div>
                            <div className="text-xs text-muted-foreground">
                              {movie.language ?? 'Language not set'}
                            </div>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-col gap-1">
                            <div>{movie.release_date ?? 'TBD'}</div>
                            <div className="text-xs text-muted-foreground">
                              {movie.runtime_label ?? 'Runtime TBD'}
                            </div>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex flex-col gap-1 text-sm">
                            <div>IMDb {movie.imdb_rating ?? '—'}</div>
                            <div className="text-xs text-muted-foreground">
                              Metascore {movie.metascore ?? '—'}
                            </div>
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex max-w-56 flex-wrap gap-1.5">
                            {movie.genres.slice(0, 3).map((genre) => (
                              <Badge key={genre} variant="outline">
                                {genre}
                              </Badge>
                            ))}
                          </div>
                        </TableCell>
                        <TableCell>
                          <div className="flex justify-end gap-2">
                            <Button asChild size="sm" variant="ghost">
                              <Link href={MovieController.show(movie.id)}>
                                <EyeIcon data-icon="inline-start" />
                                View
                              </Link>
                            </Button>
                            <Button asChild size="sm" variant="outline">
                              <Link href={MovieController.edit(movie.id)}>
                                <PencilIcon data-icon="inline-start" />
                                Edit
                              </Link>
                            </Button>
                            <Button
                              size="sm"
                              variant="destructive"
                              onClick={() => handleDelete(movie)}
                            >
                              <Trash2Icon data-icon="inline-start" />
                              Delete
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>

                <div className="flex flex-col gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-between">
                  <div className="text-sm text-muted-foreground">
                    Page {movies.current_page} of {movies.last_page}
                  </div>
                  <div className="flex gap-2">
                    <Button
                      asChild={movies.prev_page_url !== null}
                      variant="outline"
                      disabled={movies.prev_page_url === null}
                    >
                      {movies.prev_page_url ? (
                        <Link href={movies.prev_page_url} preserveScroll>
                          Previous
                        </Link>
                      ) : (
                        <span>Previous</span>
                      )}
                    </Button>
                    <Button
                      asChild={movies.next_page_url !== null}
                      variant="outline"
                      disabled={movies.next_page_url === null}
                    >
                      {movies.next_page_url ? (
                        <Link href={movies.next_page_url} preserveScroll>
                          Next
                        </Link>
                      ) : (
                        <span>Next</span>
                      )}
                    </Button>
                  </div>
                </div>
              </>
            )}
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}

function statusVariant(
  status: string,
): 'default' | 'secondary' | 'outline' | 'ghost' {
  switch (status) {
    case 'released':
      return 'default';
    case 'scheduled':
      return 'secondary';
    case 'archived':
      return 'outline';
    default:
      return 'ghost';
  }
}
