import { Link } from '@inertiajs/react';
import { ChevronLeftIcon, ChevronRightIcon } from 'lucide-react';
import { normalizePaginationLabel } from '@/components/datagrid/utils';
import { Button } from '@/components/ui/button';
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
} from '@/components/ui/pagination';
import { cn } from '@/lib/utils';
import type { PaginatorLink } from '@/types';

export function DatagridPagination({ links }: { links: PaginatorLink[] }) {
    const previousLink = links.find(
        (link) => normalizePaginationLabel(link.label) === 'Previous',
    );
    const nextLink = links.find(
        (link) => normalizePaginationLabel(link.label) === 'Next',
    );
    const pageLinks = links.filter((link) => {
        const normalizedLabel = normalizePaginationLabel(link.label);

        return normalizedLabel !== 'Previous' && normalizedLabel !== 'Next';
    });

    return (
        <Pagination className="w-full justify-center sm:mx-0 sm:w-auto sm:justify-end">
            <PaginationContent className="flex-wrap justify-center sm:justify-end">
                <PaginationItem>
                    {previousLink?.url ? (
                        <Button asChild variant="ghost" size="icon-sm">
                            <Link
                                href={previousLink.url}
                                preserveScroll
                                preserveState
                                aria-label="Previous page"
                            >
                                <ChevronLeftIcon />
                            </Link>
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            disabled
                            aria-label="Previous page"
                        >
                            <ChevronLeftIcon />
                        </Button>
                    )}
                </PaginationItem>

                {pageLinks.map((link, index) => {
                    const normalizedLabel = normalizePaginationLabel(
                        link.label,
                    );

                    const isSecondAndFollowedByEllipsis =
                        index === 1 &&
                        pageLinks.length > 2 &&
                        normalizePaginationLabel(pageLinks[2].label) === '...';
                    const isSecondToLastAndPrecededByEllipsis =
                        index === pageLinks.length - 2 &&
                        pageLinks.length > 2 &&
                        normalizePaginationLabel(
                            pageLinks[pageLinks.length - 3].label,
                        ) === '...';
                    const isHiddenOnMobile =
                        isSecondAndFollowedByEllipsis ||
                        isSecondToLastAndPrecededByEllipsis;

                    if (normalizedLabel === '...') {
                        return (
                            <PaginationItem key={`ellipsis-${index}`}>
                                <PaginationEllipsis />
                            </PaginationItem>
                        );
                    }

                    return (
                        <PaginationItem
                            key={`${normalizedLabel}-${index}`}
                            className={cn(
                                isHiddenOnMobile && 'hidden sm:block',
                            )}
                        >
                            {link.url ? (
                                <Button
                                    asChild
                                    variant={link.active ? 'default' : 'ghost'}
                                    size="sm"
                                    className={cn(
                                        'min-w-7 rounded-md px-2',
                                        !link.active && 'text-muted-foreground',
                                    )}
                                >
                                    <Link
                                        href={link.url}
                                        preserveScroll
                                        preserveState
                                    >
                                        {normalizedLabel}
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    type="button"
                                    variant={link.active ? 'default' : 'ghost'}
                                    size="sm"
                                    className="min-w-7 rounded-md px-2"
                                    disabled
                                >
                                    {normalizedLabel}
                                </Button>
                            )}
                        </PaginationItem>
                    );
                })}

                <PaginationItem>
                    {nextLink?.url ? (
                        <Button asChild variant="ghost" size="icon-sm">
                            <Link
                                href={nextLink.url}
                                preserveScroll
                                preserveState
                                aria-label="Next page"
                            >
                                <ChevronRightIcon />
                            </Link>
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            disabled
                            aria-label="Next page"
                        >
                            <ChevronRightIcon />
                        </Button>
                    )}
                </PaginationItem>
            </PaginationContent>
        </Pagination>
    );
}
