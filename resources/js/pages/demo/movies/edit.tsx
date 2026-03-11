import MovieController from '@/actions/App/Http/Controllers/Demo/MovieController';
import MovieForm from '@/components/demo/movies/movie-form';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, MovieFormPageProps } from '@/types';

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
    <AppLayout
      breadcrumbs={breadcrumbs}
      title={movie ? `Edit ${movie.title}` : 'Edit movie'}
      description="Update the demo movie entry, replace images, or test media removal."
    >
      <MovieForm
        mode="edit"
        movie={movie}
        initialValues={initialValues}
        options={options}
      />
    </AppLayout>
  );
}
