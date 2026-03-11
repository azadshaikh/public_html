import type { ReactNode } from 'react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import type { BreadcrumbItem } from '@/types';

type AppPageHeaderProps = {
  breadcrumbs?: BreadcrumbItem[];
  title?: string;
  description?: string;
  actions?: ReactNode;
};

export function AppPageHeader({
  breadcrumbs = [],
  title,
  description,
  actions,
}: AppPageHeaderProps) {
  if (breadcrumbs.length === 0 && !title && !description && !actions) {
    return null;
  }

  return (
    <div className="flex flex-col gap-4">
      {breadcrumbs.length > 0 && <Breadcrumbs breadcrumbs={breadcrumbs} />}

      {(title || description || actions) && (
        <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
          <div className="flex min-w-0 flex-col gap-1.5">
            {title && (
              <h1 className="text-2xl font-semibold tracking-tight text-foreground md:text-2xl">
                {title}
              </h1>
            )}
            {description && (
              <p className="max-w-3xl text-sm text-muted-foreground md:text-base">
                {description}
              </p>
            )}
          </div>

          {actions && (
            <div className="flex shrink-0 flex-wrap items-center gap-2">
              {actions}
            </div>
          )}
        </div>
      )}
    </div>
  );
}
