import MovieController from '@/actions/App/Http/Controllers/Demo/MovieController';
import AppHead from '@/components/app-head';
import MovieForm from '@/components/demo/movies/movie-form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, MovieFormPageProps } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Movies demo',
        href: MovieController.index(),
    },
    {
        title: 'Create movie',
        href: MovieController.create(),
    },
];

export default function CreateMovieDemo({
    movie,
    initialValues,
    options,
}: MovieFormPageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Create movie"
            description="This demo CRUD includes text, numeric, checkbox, toggle, URL, and image upload inputs."
        >
            <AppHead
                title="Create movie demo"
                description="Create a large demo movie record with mixed field types and media uploads."
            />

            <MovieForm
                mode="create"
                movie={movie}
                initialValues={initialValues}
                options={options}
            />
        </AppLayout>
    );
}
