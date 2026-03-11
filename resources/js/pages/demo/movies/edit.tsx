import AppHead from '@/components/app-head';
import MovieForm from '@/components/demo/movies/movie-form';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, MovieFormPageProps } from '@/types';
import MovieController from '@/actions/App/Http/Controllers/Demo/MovieController';

export default function EditMovieDemo({
    movie,
    initialValues,
    options,
}: MovieFormPageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Movies demo',
            href: MovieController.index(),
        },
        ...(movie
            ? [
                  {
                      title: movie.title,
                      href: MovieController.show(movie.id),
                  },
              ]
            : []),
        ...(movie
            ? [
                  {
                      title: 'Edit',
                      href: MovieController.edit(movie.id),
                  },
              ]
            : []),
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <AppHead
                title={movie ? `Edit ${movie.title}` : 'Edit movie demo'}
                description="Update the demo movie entry, replace images, or test media removal."
            />

            <div className="flex flex-col gap-8 p-4 md:p-6">
                <Heading
                    title={movie ? `Edit ${movie.title}` : 'Edit movie'}
                    description="Use this page to test update flows, validation, and media replacement."
                />

                <MovieForm
                    mode="edit"
                    movie={movie}
                    initialValues={initialValues}
                    options={options}
                />
            </div>
        </AppLayout>
    );
}
