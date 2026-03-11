import { Link, useForm } from '@inertiajs/react';
import { ArrowLeftIcon, SaveIcon } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import type { FormEvent } from 'react';
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
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldContent,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
    FieldLegend,
    FieldSet,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
    InputGroupText,
} from '@/components/ui/input-group';
import {
    NativeSelect,
    NativeSelectOption,
} from '@/components/ui/native-select';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import type {
    MovieEditingTarget,
    MovieFormDefaults,
    MovieFormOptions,
    MovieFormValues,
    MovieOption,
} from '@/types';

type MultiSelectField =
    | 'genres'
    | 'spoken_languages'
    | 'available_formats'
    | 'streaming_platforms'
    | 'content_warnings';

type BooleanField =
    | 'is_featured'
    | 'is_now_showing'
    | 'has_post_credit_scene'
    | 'is_family_friendly'
    | 'remove_poster'
    | 'remove_backdrop';

type MovieFormProps = {
    mode: 'create' | 'edit';
    movie: MovieEditingTarget | null;
    initialValues: MovieFormDefaults;
    options: MovieFormOptions;
};

type CheckboxCollectionProps = {
    title: string;
    description: string;
    field: MultiSelectField;
    values: string[];
    options: MovieOption[];
    error?: string;
    onToggle: (
        field: MultiSelectField,
        value: string,
        checked: boolean,
    ) => void;
};

export default function MovieForm({
    mode,
    movie,
    initialValues,
    options,
}: MovieFormProps) {
    const form = useForm<MovieFormValues>({
        ...initialValues,
        poster: null,
        backdrop: null,
    });
    const posterObjectUrl = useObjectUrl(form.data.poster);
    const backdropObjectUrl = useObjectUrl(form.data.backdrop);

    const submitLabel = mode === 'create' ? 'Create movie' : 'Update movie';
    const submitAction = movie
        ? MovieController.update(movie.id)
        : MovieController.store();

    const posterPreviewUrl =
        form.data.remove_poster && form.data.poster === null
            ? null
            : (posterObjectUrl ?? movie?.poster_url ?? null);
    const backdropPreviewUrl =
        form.data.remove_backdrop && form.data.backdrop === null
            ? null
            : (backdropObjectUrl ?? movie?.backdrop_url ?? null);

    const selectedSummary = useMemo(
        () => [
            `${form.data.genres.length} genres`,
            `${form.data.spoken_languages.length} languages`,
            `${form.data.available_formats.length} formats`,
        ],
        [
            form.data.available_formats.length,
            form.data.genres.length,
            form.data.spoken_languages.length,
        ],
    );

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            poster: data.poster ?? undefined,
            backdrop: data.backdrop ?? undefined,
        }));

        form.submit(submitAction, {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const handleTextChange = <K extends keyof MovieFormValues>(
        key: K,
        value: MovieFormValues[K],
    ) => {
        form.setData(key, value as never);
    };

    const handleTitleChange = (value: string) => {
        const currentDerivedSlug = slugify(form.data.title);
        form.setData('title', value);

        if (form.data.slug === '' || form.data.slug === currentDerivedSlug) {
            form.setData('slug', slugify(value));
        }
    };

    const handleFileChange = (
        field: 'poster' | 'backdrop',
        file: File | null,
    ) => {
        form.setData(field, file);

        if (field === 'poster' && file !== null) {
            form.setData('remove_poster', false);
        }

        if (field === 'backdrop' && file !== null) {
            form.setData('remove_backdrop', false);
        }
    };

    const toggleCollectionValue = (
        field: MultiSelectField,
        value: string,
        checked: boolean,
    ) => {
        const currentValues = form.data[field];

        if (checked) {
            if (currentValues.includes(value)) {
                return;
            }

            form.setData(field, [...currentValues, value]);

            return;
        }

        form.setData(
            field,
            currentValues.filter((currentValue) => currentValue !== value),
        );
    };

    const toggleBoolean = (field: BooleanField, checked: boolean) => {
        form.setData(field, checked);

        if (field === 'remove_poster' && checked) {
            form.setData('poster', null);
        }

        if (field === 'remove_backdrop' && checked) {
            form.setData('backdrop', null);
        }
    };

    return (
        <form className="flex flex-col gap-6" onSubmit={handleSubmit}>
            <div className="grid gap-6 xl:grid-cols-[minmax(0,1.8fr)_minmax(320px,1fr)]">
                <div className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Movie identity</CardTitle>
                            <CardDescription>
                                Core details for the demo catalogue entry.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6">
                            <FieldGroup>
                                <Field
                                    data-invalid={Boolean(form.errors.title)}
                                >
                                    <FieldLabel htmlFor="title">
                                        Title
                                    </FieldLabel>
                                    <Input
                                        id="title"
                                        value={form.data.title}
                                        onChange={(event) =>
                                            handleTitleChange(
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(form.errors.title) ||
                                            undefined
                                        }
                                        placeholder="The Midnight Circuit"
                                    />
                                    <FieldError>{form.errors.title}</FieldError>
                                </Field>

                                <Field data-invalid={Boolean(form.errors.slug)}>
                                    <FieldLabel htmlFor="slug">Slug</FieldLabel>
                                    <InputGroup className="w-full">
                                        <InputGroupAddon>
                                            <InputGroupText>
                                                demo/movies/
                                            </InputGroupText>
                                        </InputGroupAddon>
                                        <InputGroupInput
                                            id="slug"
                                            value={form.data.slug}
                                            onChange={(event) =>
                                                handleTextChange(
                                                    'slug',
                                                    slugify(event.target.value),
                                                )
                                            }
                                            aria-invalid={
                                                Boolean(form.errors.slug) ||
                                                undefined
                                            }
                                            placeholder="the-midnight-circuit"
                                        />
                                    </InputGroup>
                                    <FieldDescription>
                                        Auto-generated from the title until you
                                        edit it.
                                    </FieldDescription>
                                    <FieldError>{form.errors.slug}</FieldError>
                                </Field>
                            </FieldGroup>

                            <FieldGroup>
                                <Field
                                    data-invalid={Boolean(form.errors.tagline)}
                                >
                                    <FieldLabel htmlFor="tagline">
                                        Tagline
                                    </FieldLabel>
                                    <Input
                                        id="tagline"
                                        value={form.data.tagline}
                                        onChange={(event) =>
                                            handleTextChange(
                                                'tagline',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(form.errors.tagline) ||
                                            undefined
                                        }
                                        placeholder="A neon-soaked chase through a city that never sleeps"
                                    />
                                    <FieldError>
                                        {form.errors.tagline}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(form.errors.director)}
                                >
                                    <FieldLabel htmlFor="director">
                                        Director
                                    </FieldLabel>
                                    <Input
                                        id="director"
                                        value={form.data.director}
                                        onChange={(event) =>
                                            handleTextChange(
                                                'director',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(form.errors.director) ||
                                            undefined
                                        }
                                        placeholder="Jordan Vega"
                                    />
                                    <FieldError>
                                        {form.errors.director}
                                    </FieldError>
                                </Field>
                            </FieldGroup>

                            <FieldGroup>
                                <Field
                                    data-invalid={Boolean(form.errors.synopsis)}
                                >
                                    <FieldLabel htmlFor="synopsis">
                                        Synopsis
                                    </FieldLabel>
                                    <Textarea
                                        id="synopsis"
                                        value={form.data.synopsis}
                                        onChange={(event) =>
                                            handleTextChange(
                                                'synopsis',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(form.errors.synopsis) ||
                                            undefined
                                        }
                                        placeholder="Describe the plot, stakes, and why the audience should care."
                                        rows={6}
                                    />
                                    <FieldDescription>
                                        Use this large textarea to test
                                        multi-paragraph content.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.errors.synopsis}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(form.errors.notes)}
                                >
                                    <FieldLabel htmlFor="notes">
                                        Internal notes
                                    </FieldLabel>
                                    <Textarea
                                        id="notes"
                                        value={form.data.notes}
                                        onChange={(event) =>
                                            handleTextChange(
                                                'notes',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(form.errors.notes) ||
                                            undefined
                                        }
                                        placeholder="Launch plan, QA notes, or editing instructions for the demo team."
                                        rows={4}
                                    />
                                    <FieldError>{form.errors.notes}</FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Release and commercial data</CardTitle>
                            <CardDescription>
                                Dates, timings, scores, and pricing inputs.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6">
                            <FieldGroup className="md:grid-cols-2">
                                <Field
                                    data-invalid={Boolean(
                                        form.errors.release_date,
                                    )}
                                >
                                    <FieldLabel htmlFor="release_date">
                                        Release date
                                    </FieldLabel>
                                    <Input
                                        id="release_date"
                                        type="date"
                                        value={form.data.release_date}
                                        onChange={(event) =>
                                            handleTextChange(
                                                'release_date',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(form.errors.release_date) ||
                                            undefined
                                        }
                                    />
                                    <FieldError>
                                        {form.errors.release_date}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(
                                        form.errors.release_time,
                                    )}
                                >
                                    <FieldLabel htmlFor="release_time">
                                        Premiere time
                                    </FieldLabel>
                                    <Input
                                        id="release_time"
                                        type="time"
                                        value={form.data.release_time}
                                        onChange={(event) =>
                                            handleTextChange(
                                                'release_time',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(form.errors.release_time) ||
                                            undefined
                                        }
                                    />
                                    <FieldError>
                                        {form.errors.release_time}
                                    </FieldError>
                                </Field>
                            </FieldGroup>

                            <FieldGroup className="md:grid-cols-3">
                                <Field
                                    data-invalid={Boolean(
                                        form.errors.runtime_minutes,
                                    )}
                                >
                                    <FieldLabel htmlFor="runtime_minutes">
                                        Runtime (minutes)
                                    </FieldLabel>
                                    <Input
                                        id="runtime_minutes"
                                        type="number"
                                        min={45}
                                        max={320}
                                        value={form.data.runtime_minutes}
                                        onChange={(event) =>
                                            handleTextChange(
                                                'runtime_minutes',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(
                                                form.errors.runtime_minutes,
                                            ) || undefined
                                        }
                                        placeholder="124"
                                    />
                                    <FieldError>
                                        {form.errors.runtime_minutes}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(
                                        form.errors.metascore,
                                    )}
                                >
                                    <FieldLabel htmlFor="metascore">
                                        Metascore
                                    </FieldLabel>
                                    <Input
                                        id="metascore"
                                        type="number"
                                        min={0}
                                        max={100}
                                        value={form.data.metascore}
                                        onChange={(event) =>
                                            handleTextChange(
                                                'metascore',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(form.errors.metascore) ||
                                            undefined
                                        }
                                        placeholder="79"
                                    />
                                    <FieldError>
                                        {form.errors.metascore}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(
                                        form.errors.audience_score,
                                    )}
                                >
                                    <FieldLabel htmlFor="audience_score">
                                        Audience score
                                    </FieldLabel>
                                    <Input
                                        id="audience_score"
                                        type="number"
                                        min={0}
                                        max={100}
                                        value={form.data.audience_score}
                                        onChange={(event) =>
                                            handleTextChange(
                                                'audience_score',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(
                                                form.errors.audience_score,
                                            ) || undefined
                                        }
                                        placeholder="92"
                                    />
                                    <FieldError>
                                        {form.errors.audience_score}
                                    </FieldError>
                                </Field>
                            </FieldGroup>

                            <FieldGroup className="md:grid-cols-3">
                                <Field
                                    data-invalid={Boolean(
                                        form.errors.imdb_rating,
                                    )}
                                >
                                    <FieldLabel htmlFor="imdb_rating">
                                        IMDb rating
                                    </FieldLabel>
                                    <Input
                                        id="imdb_rating"
                                        type="number"
                                        min={0}
                                        max={10}
                                        step="0.1"
                                        value={form.data.imdb_rating}
                                        onChange={(event) =>
                                            handleTextChange(
                                                'imdb_rating',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(form.errors.imdb_rating) ||
                                            undefined
                                        }
                                        placeholder="8.4"
                                    />
                                    <FieldError>
                                        {form.errors.imdb_rating}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(
                                        form.errors.ticket_price,
                                    )}
                                >
                                    <FieldLabel htmlFor="ticket_price">
                                        Average ticket price
                                    </FieldLabel>
                                    <Input
                                        id="ticket_price"
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={form.data.ticket_price}
                                        onChange={(event) =>
                                            handleTextChange(
                                                'ticket_price',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(form.errors.ticket_price) ||
                                            undefined
                                        }
                                        placeholder="18.50"
                                    />
                                    <FieldError>
                                        {form.errors.ticket_price}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(form.errors.language)}
                                >
                                    <FieldLabel htmlFor="language">
                                        Original language
                                    </FieldLabel>
                                    <NativeSelect
                                        id="language"
                                        className="w-full"
                                        value={form.data.language}
                                        onChange={(event) =>
                                            handleTextChange(
                                                'language',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(form.errors.language) ||
                                            undefined
                                        }
                                    >
                                        {options.languageOptions.map(
                                            (option) => (
                                                <NativeSelectOption
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </NativeSelectOption>
                                            ),
                                        )}
                                    </NativeSelect>
                                    <FieldError>
                                        {form.errors.language}
                                    </FieldError>
                                </Field>
                            </FieldGroup>

                            <FieldGroup className="md:grid-cols-3">
                                <Field
                                    data-invalid={Boolean(form.errors.budget)}
                                >
                                    <FieldLabel htmlFor="budget">
                                        Production budget
                                    </FieldLabel>
                                    <Input
                                        id="budget"
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={form.data.budget}
                                        onChange={(event) =>
                                            handleTextChange(
                                                'budget',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(form.errors.budget) ||
                                            undefined
                                        }
                                        placeholder="150000000"
                                    />
                                    <FieldError>
                                        {form.errors.budget}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(
                                        form.errors.box_office,
                                    )}
                                >
                                    <FieldLabel htmlFor="box_office">
                                        Worldwide box office
                                    </FieldLabel>
                                    <Input
                                        id="box_office"
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={form.data.box_office}
                                        onChange={(event) =>
                                            handleTextChange(
                                                'box_office',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(form.errors.box_office) ||
                                            undefined
                                        }
                                        placeholder="480000000"
                                    />
                                    <FieldError>
                                        {form.errors.box_office}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(form.errors.studio)}
                                >
                                    <FieldLabel htmlFor="studio">
                                        Studio
                                    </FieldLabel>
                                    <Input
                                        id="studio"
                                        value={form.data.studio}
                                        onChange={(event) =>
                                            handleTextChange(
                                                'studio',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(form.errors.studio) ||
                                            undefined
                                        }
                                        placeholder="North Star Pictures"
                                    />
                                    <FieldError>
                                        {form.errors.studio}
                                    </FieldError>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Classification and discoverability
                            </CardTitle>
                            <CardDescription>
                                Use toggles and multi-select fields to test
                                different input types.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6">
                            <FieldSet>
                                <FieldLegend>Status</FieldLegend>
                                <FieldDescription>
                                    Choose the publishing state used by the demo
                                    listing filters.
                                </FieldDescription>
                                <ToggleGroup
                                    type="single"
                                    variant="outline"
                                    className="flex w-full flex-wrap gap-2"
                                    value={form.data.status}
                                    onValueChange={(value) => {
                                        if (value) {
                                            form.setData('status', value);
                                        }
                                    }}
                                >
                                    {options.statusOptions.map((option) => (
                                        <ToggleGroupItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </ToggleGroupItem>
                                    ))}
                                </ToggleGroup>
                                <FieldError>{form.errors.status}</FieldError>
                            </FieldSet>

                            <FieldSet>
                                <FieldLegend>Content rating</FieldLegend>
                                <FieldDescription>
                                    Compact toggle buttons are useful for small
                                    option sets.
                                </FieldDescription>
                                <ToggleGroup
                                    type="single"
                                    variant="outline"
                                    className="flex w-full flex-wrap gap-2"
                                    value={form.data.rating}
                                    onValueChange={(value) => {
                                        if (value) {
                                            form.setData('rating', value);
                                        }
                                    }}
                                >
                                    {options.ratingOptions.map((option) => (
                                        <ToggleGroupItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </ToggleGroupItem>
                                    ))}
                                </ToggleGroup>
                                <FieldError>{form.errors.rating}</FieldError>
                            </FieldSet>

                            <CheckboxCollection
                                title="Genres"
                                description="At least one genre is required."
                                field="genres"
                                values={form.data.genres}
                                options={options.genreOptions}
                                error={form.errors.genres}
                                onToggle={toggleCollectionValue}
                            />

                            <CheckboxCollection
                                title="Spoken languages"
                                description="Use multiple selections to test array validation and checkbox UI."
                                field="spoken_languages"
                                values={form.data.spoken_languages}
                                options={options.languageOptions}
                                error={form.errors.spoken_languages}
                                onToggle={toggleCollectionValue}
                            />
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Distribution</CardTitle>
                            <CardDescription>
                                URLs, formats, and platform-related inputs.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6">
                            <FieldGroup>
                                <Field
                                    data-invalid={Boolean(
                                        form.errors.trailer_url,
                                    )}
                                >
                                    <FieldLabel htmlFor="trailer_url">
                                        Trailer URL
                                    </FieldLabel>
                                    <InputGroup className="w-full">
                                        <InputGroupAddon>
                                            <InputGroupText>
                                                https://
                                            </InputGroupText>
                                        </InputGroupAddon>
                                        <InputGroupInput
                                            id="trailer_url"
                                            value={trimProtocol(
                                                form.data.trailer_url,
                                            )}
                                            onChange={(event) =>
                                                handleTextChange(
                                                    'trailer_url',
                                                    event.target.value === ''
                                                        ? ''
                                                        : `https://${trimProtocol(event.target.value)}`,
                                                )
                                            }
                                            aria-invalid={
                                                Boolean(
                                                    form.errors.trailer_url,
                                                ) || undefined
                                            }
                                            placeholder="youtube.com/watch?v=demo123"
                                        />
                                    </InputGroup>
                                    <FieldError>
                                        {form.errors.trailer_url}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(
                                        form.errors.official_site,
                                    )}
                                >
                                    <FieldLabel htmlFor="official_site">
                                        Official site
                                    </FieldLabel>
                                    <Input
                                        id="official_site"
                                        type="url"
                                        value={form.data.official_site}
                                        onChange={(event) =>
                                            handleTextChange(
                                                'official_site',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={
                                            Boolean(
                                                form.errors.official_site,
                                            ) || undefined
                                        }
                                        placeholder="https://midnightcircuit.example"
                                    />
                                    <FieldError>
                                        {form.errors.official_site}
                                    </FieldError>
                                </Field>
                            </FieldGroup>

                            <CheckboxCollection
                                title="Available formats"
                                description="Cinema, physical, and streaming release formats."
                                field="available_formats"
                                values={form.data.available_formats}
                                options={options.formatOptions}
                                error={form.errors.available_formats}
                                onToggle={toggleCollectionValue}
                            />

                            <CheckboxCollection
                                title="Streaming platforms"
                                description="Optional platforms for catalogue distribution testing."
                                field="streaming_platforms"
                                values={form.data.streaming_platforms}
                                options={options.streamingOptions}
                                error={form.errors.streaming_platforms}
                                onToggle={toggleCollectionValue}
                            />

                            <CheckboxCollection
                                title="Content warnings"
                                description="Optional audience guidance metadata."
                                field="content_warnings"
                                values={form.data.content_warnings}
                                options={options.warningOptions}
                                error={form.errors.content_warnings}
                                onToggle={toggleCollectionValue}
                            />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Media uploads</CardTitle>
                            <CardDescription>
                                Upload poster and backdrop images to validate
                                file handling.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-6">
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="grid gap-3">
                                    <MovieArtwork
                                        title={form.data.title || 'Poster'}
                                        src={posterPreviewUrl}
                                        variant="poster"
                                    />
                                    <Field
                                        data-invalid={Boolean(
                                            form.errors.poster,
                                        )}
                                    >
                                        <FieldLabel htmlFor="poster">
                                            Poster image
                                        </FieldLabel>
                                        <Input
                                            id="poster"
                                            type="file"
                                            accept="image/*"
                                            onChange={(event) =>
                                                handleFileChange(
                                                    'poster',
                                                    event.target.files?.[0] ??
                                                        null,
                                                )
                                            }
                                            aria-invalid={
                                                Boolean(form.errors.poster) ||
                                                undefined
                                            }
                                        />
                                        <FieldError>
                                            {form.errors.poster}
                                        </FieldError>
                                    </Field>
                                    {movie?.poster_url && (
                                        <Field orientation="horizontal">
                                            <Switch
                                                checked={
                                                    form.data.remove_poster
                                                }
                                                onCheckedChange={(checked) =>
                                                    toggleBoolean(
                                                        'remove_poster',
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <FieldContent>
                                                <FieldLabel htmlFor="poster">
                                                    Remove current poster
                                                </FieldLabel>
                                                <FieldDescription>
                                                    Useful when testing
                                                    replacement and deletion
                                                    flows.
                                                </FieldDescription>
                                            </FieldContent>
                                        </Field>
                                    )}
                                </div>

                                <div className="grid gap-3">
                                    <MovieArtwork
                                        title={form.data.title || 'Backdrop'}
                                        src={backdropPreviewUrl}
                                        variant="backdrop"
                                    />
                                    <Field
                                        data-invalid={Boolean(
                                            form.errors.backdrop,
                                        )}
                                    >
                                        <FieldLabel htmlFor="backdrop">
                                            Backdrop image
                                        </FieldLabel>
                                        <Input
                                            id="backdrop"
                                            type="file"
                                            accept="image/*"
                                            onChange={(event) =>
                                                handleFileChange(
                                                    'backdrop',
                                                    event.target.files?.[0] ??
                                                        null,
                                                )
                                            }
                                            aria-invalid={
                                                Boolean(form.errors.backdrop) ||
                                                undefined
                                            }
                                        />
                                        <FieldError>
                                            {form.errors.backdrop}
                                        </FieldError>
                                    </Field>
                                    {movie?.backdrop_url && (
                                        <Field orientation="horizontal">
                                            <Switch
                                                checked={
                                                    form.data.remove_backdrop
                                                }
                                                onCheckedChange={(checked) =>
                                                    toggleBoolean(
                                                        'remove_backdrop',
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <FieldContent>
                                                <FieldLabel htmlFor="backdrop">
                                                    Remove current backdrop
                                                </FieldLabel>
                                                <FieldDescription>
                                                    Lets you test media removal
                                                    without uploading a
                                                    replacement.
                                                </FieldDescription>
                                            </FieldContent>
                                        </Field>
                                    )}
                                </div>
                            </div>

                            {form.progress && (
                                <div className="grid gap-2">
                                    <div className="text-sm text-muted-foreground">
                                        Upload progress:{' '}
                                        {form.progress.percentage}%
                                    </div>
                                    <Progress
                                        value={form.progress.percentage}
                                    />
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Publishing flags</CardTitle>
                            <CardDescription>
                                Switches help test boolean state changes.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <Field orientation="horizontal">
                                <Switch
                                    checked={form.data.is_featured}
                                    onCheckedChange={(checked) =>
                                        toggleBoolean(
                                            'is_featured',
                                            checked === true,
                                        )
                                    }
                                />
                                <FieldContent>
                                    <FieldLabel>Featured title</FieldLabel>
                                    <FieldDescription>
                                        Highlight this movie in the demo
                                        listing.
                                    </FieldDescription>
                                </FieldContent>
                            </Field>

                            <Field orientation="horizontal">
                                <Switch
                                    checked={form.data.is_now_showing}
                                    onCheckedChange={(checked) =>
                                        toggleBoolean(
                                            'is_now_showing',
                                            checked === true,
                                        )
                                    }
                                />
                                <FieldContent>
                                    <FieldLabel>Now showing</FieldLabel>
                                    <FieldDescription>
                                        Helps test index filters and badges.
                                    </FieldDescription>
                                </FieldContent>
                            </Field>

                            <Field orientation="horizontal">
                                <Switch
                                    checked={form.data.has_post_credit_scene}
                                    onCheckedChange={(checked) =>
                                        toggleBoolean(
                                            'has_post_credit_scene',
                                            checked === true,
                                        )
                                    }
                                />
                                <FieldContent>
                                    <FieldLabel>Post-credit scene</FieldLabel>
                                    <FieldDescription>
                                        Boolean metadata for the detail page.
                                    </FieldDescription>
                                </FieldContent>
                            </Field>

                            <Field orientation="horizontal">
                                <Switch
                                    checked={form.data.is_family_friendly}
                                    onCheckedChange={(checked) =>
                                        toggleBoolean(
                                            'is_family_friendly',
                                            checked === true,
                                        )
                                    }
                                />
                                <FieldContent>
                                    <FieldLabel>Family friendly</FieldLabel>
                                    <FieldDescription>
                                        A simple yes or no field for audience
                                        suitability.
                                    </FieldDescription>
                                </FieldContent>
                            </Field>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Submission summary</CardTitle>
                            <CardDescription>
                                Quick summary of your current demo entry.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <Heading
                                variant="small"
                                title={form.data.title || 'Untitled movie'}
                                description={
                                    form.data.tagline ||
                                    'Add a tagline to make the detail page feel richer.'
                                }
                            />

                            <div className="flex flex-wrap gap-2">
                                <Badge variant="outline">
                                    {selectedSummary[0]}
                                </Badge>
                                <Badge variant="outline">
                                    {selectedSummary[1]}
                                </Badge>
                                <Badge variant="outline">
                                    {selectedSummary[2]}
                                </Badge>
                                {form.data.is_featured && (
                                    <Badge>Featured</Badge>
                                )}
                                {form.data.is_now_showing && (
                                    <Badge variant="secondary">
                                        Now showing
                                    </Badge>
                                )}
                            </div>

                            <div className="flex flex-wrap gap-3">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {form.processing ? (
                                        <Spinner data-icon="inline-start" />
                                    ) : (
                                        <SaveIcon data-icon="inline-start" />
                                    )}
                                    {submitLabel}
                                </Button>

                                <Button asChild type="button" variant="outline">
                                    <Link href={MovieController.index()}>
                                        <ArrowLeftIcon data-icon="inline-start" />
                                        Back to movies
                                    </Link>
                                </Button>
                            </div>

                            {form.recentlySuccessful && (
                                <div className="text-sm text-muted-foreground">
                                    Saved successfully.
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </form>
    );
}

function CheckboxCollection({
    title,
    description,
    field,
    values,
    options,
    error,
    onToggle,
}: CheckboxCollectionProps) {
    return (
        <FieldSet>
            <FieldLegend>{title}</FieldLegend>
            <FieldDescription>{description}</FieldDescription>
            <div className="grid gap-3 md:grid-cols-2">
                {options.map((option) => {
                    const id = `${field}-${option.value}`;
                    const checked = values.includes(option.value);

                    return (
                        <Field
                            key={option.value}
                            orientation="horizontal"
                            className="rounded-xl border p-3"
                        >
                            <Checkbox
                                id={id}
                                checked={checked}
                                onCheckedChange={(nextValue) =>
                                    onToggle(
                                        field,
                                        option.value,
                                        nextValue === true,
                                    )
                                }
                            />
                            <FieldContent>
                                <FieldLabel htmlFor={id}>
                                    {option.label}
                                </FieldLabel>
                            </FieldContent>
                        </Field>
                    );
                })}
            </div>
            <FieldError>{error}</FieldError>
        </FieldSet>
    );
}

function slugify(value: string): string {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function trimProtocol(value: string): string {
    return value.replace(/^https?:\/\//, '');
}

function useObjectUrl(file: File | null): string | null {
    const objectUrl = useMemo(() => {
        if (file === null) {
            return null;
        }

        return URL.createObjectURL(file);
    }, [file]);

    useEffect(() => {
        if (objectUrl === null) {
            return;
        }

        return () => {
            URL.revokeObjectURL(objectUrl);
        };
    }, [objectUrl]);

    return objectUrl;
}
