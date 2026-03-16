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
                'peer h-auto gap-2 bg-transparent p-0',
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
                '!h-10 rounded-t-lg !border-none bg-transparent !px-4 !py-2 text-muted-foreground !shadow-none data-[state=active]:z-10 data-[state=active]:rounded-b-none data-[state=active]:!bg-muted data-[state=active]:text-foreground data-[state=active]:ring-0 data-[state=active]:outline-none after:hidden dark:data-[state=active]:!border-none dark:data-[state=active]:!bg-muted',
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
            className={cn(
                '-mt-px rounded-lg bg-muted p-4 pt-6',
                'peer-has-[[data-state=active]:first-child]:rounded-tl-none',
                'peer-has-[[data-state=active]:last-child]:rounded-tr-none',
                className,
            )}
            {...props}
        />
    );
}

export { PanelTabs, PanelTabsContent, PanelTabsList, PanelTabsTrigger };
