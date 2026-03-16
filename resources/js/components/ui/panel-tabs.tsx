'use client';

import * as React from 'react';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { cn } from '@/lib/utils';

function PanelTabs({
    className,
    size = 'comfortable',
    ...props
}: React.ComponentProps<typeof Tabs>) {
    return <Tabs size={size} className={cn('gap-0', className)} {...props} />;
}

function PanelTabsList({
    className,
    ...props
}: React.ComponentProps<typeof TabsList>) {
    return (
        <TabsList
            className={cn(
                'h-auto w-full gap-0.5 bg-transparent p-0',
                className,
            )}
            {...props}
        />
    );
}

function PanelTabsTrigger({
    className,
    ...props
}: React.ComponentProps<typeof TabsTrigger>) {
    return (
        <TabsTrigger
            className={cn(
                'overflow-hidden rounded-b-none border-0 bg-transparent py-2 data-[state=active]:z-10 data-[state=active]:bg-muted data-[state=active]:shadow-none after:hidden',
                className,
            )}
            {...props}
        />
    );
}

function PanelTabsContent({
    className,
    ...props
}: React.ComponentProps<typeof TabsContent>) {
    return (
        <TabsContent
            className={cn('-mt-px rounded-b-lg bg-muted p-4', className)}
            {...props}
        />
    );
}

export { PanelTabs, PanelTabsContent, PanelTabsList, PanelTabsTrigger };
