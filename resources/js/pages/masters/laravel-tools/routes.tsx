import { RouteIcon } from 'lucide-react';
import { Datagrid } from '@/components/datagrid/datagrid';
import type {
    DatagridColumn,
    DatagridFilter,
} from '@/components/datagrid/datagrid';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import {
    LaravelToolsNavigation,
    getLaravelToolsBreadcrumbs,
} from '@/pages/masters/laravel-tools/components/shared';
import type {
    LaravelRouteListItem,
    LaravelToolsRoutesPageProps,
} from '@/types/laravel-tools';

const METHOD_OPTIONS = ['all', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

export default function LaravelToolsRoutes({
    routes,
    total,
    filters,
}: LaravelToolsRoutesPageProps) {
    const gridFilters: DatagridFilter[] = [
        {
            type: 'search',
            name: 'search',
            value: filters.search,
            placeholder: 'Search URIs, route names, or controller actions...',
            className: 'lg:min-w-80',
        },
        {
            type: 'select',
            name: 'method',
            value: filters.method,
            options: METHOD_OPTIONS.map((option) => ({
                value: option,
                label: option === 'all' ? 'All methods' : option,
            })),
            className: 'lg:min-w-48',
        },
    ];

    const columns: DatagridColumn<LaravelRouteListItem>[] = [
        {
            key: 'method_label',
            header: 'Method',
            sortable: true,
            headerClassName: 'w-44',
            cellClassName: 'w-44 align-top',
            cell: (item) => (
                <div className="flex flex-wrap gap-1">
                    {item.methods.map((method) => (
                        <Badge
                            key={method}
                            variant={badgeVariantForMethod(method)}
                        >
                            {method}
                        </Badge>
                    ))}
                </div>
            ),
        },
        {
            key: 'uri',
            header: 'URI',
            sortable: true,
            cellClassName: 'align-top font-mono text-xs text-foreground',
            cell: (item) => `/${item.uri}`,
        },
        {
            key: 'name',
            header: 'Name',
            sortable: true,
            cellClassName: 'align-top',
            cell: (item) =>
                item.name ? (
                    <Badge variant="outline">{item.name}</Badge>
                ) : (
                    <span className="text-sm text-muted-foreground">—</span>
                ),
        },
        {
            key: 'action',
            header: 'Action',
            sortable: true,
            cellClassName: 'align-top font-mono text-xs text-muted-foreground',
        },
        {
            key: 'middleware',
            header: 'Middleware',
            cellClassName: 'align-top',
            cell: (item) =>
                item.middleware.length > 0 ? (
                    <div className="flex flex-wrap gap-1">
                        {item.middleware.map((middleware) => (
                            <Badge key={middleware} variant="secondary">
                                {middleware}
                            </Badge>
                        ))}
                    </div>
                ) : (
                    <span className="text-sm text-muted-foreground">—</span>
                ),
        },
    ];

    return (
        <AppLayout
            breadcrumbs={getLaravelToolsBreadcrumbs('Route List')}
            title="Route List"
            description="Search registered routes by path, name, controller action, or HTTP method."
        >
            <div className="flex flex-col gap-6">
                <LaravelToolsNavigation current="routes" />

                <Card>
                    <CardContent>
                        <Datagrid
                            action={route('app.masters.laravel-tools.routes')}
                            rows={routes}
                            columns={columns}
                            filters={gridFilters}
                            getRowKey={(item) =>
                                `${item.uri}-${item.method_label}-${item.name ?? 'unnamed'}`
                            }
                            sorting={{
                                sort: filters.sort,
                                direction: filters.direction,
                            }}
                            perPage={{
                                value: filters.per_page,
                                options: [10, 25, 50, 100],
                            }}
                            summary={`${total} registered routes available in the current application map.`}
                            empty={{
                                icon: <RouteIcon className="size-5" />,
                                title: 'No routes found',
                                description:
                                    'Adjust the search query or method filter to widen the result set.',
                            }}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function badgeVariantForMethod(
    method: string,
): 'success' | 'info' | 'warning' | 'danger' | 'secondary' {
    switch (method) {
        case 'GET':
            return 'success';
        case 'POST':
            return 'info';
        case 'PUT':
        case 'PATCH':
            return 'warning';
        case 'DELETE':
            return 'danger';
        default:
            return 'secondary';
    }
}
