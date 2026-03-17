import { Link } from '@inertiajs/react';
import { LayoutGridIcon, PencilIcon, PuzzleIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type {
    AvailableWidget,
    WidgetArea,
    WidgetIndexPageProps,
    WidgetInstance,
} from '../../../types/cms';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Appearance', href: route('cms.appearance.themes.index') },
    { title: 'Widgets', href: route('cms.appearance.widgets.index') },
];

function WidgetPreviewItem({
    widget,
    availableWidgets,
}: {
    widget: WidgetInstance;
    availableWidgets: Record<string, AvailableWidget>;
}) {
    const info = availableWidgets[widget.type];
    const typeName = info?.name ?? widget.type;

    return (
        <li className="flex items-center gap-2 py-1.5 text-sm">
            <PuzzleIcon className="size-4 shrink-0 text-primary" />
            <span className="min-w-0 flex-1 truncate font-medium">
                {widget.title || 'Untitled Widget'}
            </span>
            <span className="shrink-0 text-xs text-muted-foreground">
                {typeName}
            </span>
        </li>
    );
}

function WidgetAreaCard({
    area,
    widgets,
    availableWidgets,
}: {
    area: WidgetArea;
    widgets: WidgetInstance[];
    availableWidgets: Record<string, AvailableWidget>;
}) {
    const count = widgets.length;
    const preview = widgets.slice(0, 4);
    const overflow = count - preview.length;

    return (
        <Card className="flex flex-col">
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <LayoutGridIcon className="size-4 shrink-0 text-primary" />
                        <CardTitle className="text-base">{area.name}</CardTitle>
                    </div>
                    <Badge
                        variant={count > 0 ? 'secondary' : 'outline'}
                        className="shrink-0"
                    >
                        {count} {count === 1 ? 'widget' : 'widgets'}
                    </Badge>
                </div>
                {area.description && (
                    <CardDescription className="mt-1">
                        {area.description}
                    </CardDescription>
                )}
            </CardHeader>

            <CardContent className="flex-1 py-0">
                {count > 0 ? (
                    <ul className="divide-y">
                        {preview.map((w) => (
                            <WidgetPreviewItem
                                key={w.id}
                                widget={w}
                                availableWidgets={availableWidgets}
                            />
                        ))}
                        {overflow > 0 && (
                            <li className="py-1.5 text-xs text-muted-foreground">
                                …and {overflow} more
                            </li>
                        )}
                    </ul>
                ) : (
                    <p className="py-2 text-sm text-muted-foreground">
                        No widgets in this area.
                    </p>
                )}
            </CardContent>

            <CardFooter className="pt-4">
                <Button asChild className="w-full" variant="outline">
                    <Link
                        href={route('cms.appearance.widgets.edit', {
                            area_id: area.id,
                        })}
                    >
                        <PencilIcon data-icon="inline-start" />
                        Edit Area
                    </Link>
                </Button>
            </CardFooter>
        </Card>
    );
}

export default function WidgetsIndex({
    widgetAreas,
    currentWidgets,
    availableWidgets,
}: WidgetIndexPageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Widget Areas"
            description="Manage content for the widget-ready sections of your theme."
        >
            {widgetAreas.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-3 py-20 text-center">
                    <LayoutGridIcon className="size-12 text-muted-foreground/40" />
                    <h3 className="text-lg font-medium">
                        No widget areas available
                    </h3>
                    <p className="max-w-sm text-sm text-muted-foreground">
                        Your active theme doesn't define any widget areas, or no
                        theme is currently active.
                    </p>
                </div>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {widgetAreas.map((area) => (
                        <WidgetAreaCard
                            key={area.id}
                            area={area}
                            widgets={currentWidgets[area.id] ?? []}
                            availableWidgets={availableWidgets}
                        />
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
