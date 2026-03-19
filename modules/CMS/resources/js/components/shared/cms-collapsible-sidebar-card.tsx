'use client';

import type { LucideIcon } from 'lucide-react';
import { ChevronDownIcon } from 'lucide-react';
import { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';

type CmsCollapsibleSidebarCardProps = {
    title: string;
    description: string;
    icon: LucideIcon;
    hasErrors?: boolean;
    children: React.ReactNode;
};

export function CmsCollapsibleSidebarCard({
    title,
    description,
    icon: Icon,
    hasErrors = false,
    children,
}: CmsCollapsibleSidebarCardProps) {
    const [open, setOpen] = useState(false);
    const isOpen = open || hasErrors;

    return (
        <Card>
            <Collapsible open={isOpen} onOpenChange={setOpen}>
                <CardHeader>
                    <CollapsibleTrigger asChild>
                        <button
                            type="button"
                            className="flex w-full items-start justify-between gap-4 text-left"
                        >
                            <div className="flex min-w-0 flex-1 items-start gap-2 overflow-hidden">
                                <Icon className="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                                <div className="flex min-w-0 flex-1 flex-col gap-1 overflow-hidden">
                                    <CardTitle>{title}</CardTitle>
                                    <CardDescription
                                        className="mb-3 truncate text-xs"
                                        title={description}
                                    >
                                        {description}
                                    </CardDescription>
                                </div>
                            </div>
                            <ChevronDownIcon
                                className={cn(
                                    'mt-0.5 size-4 shrink-0 text-muted-foreground transition-transform',
                                    isOpen ? 'rotate-180' : '',
                                )}
                            />
                        </button>
                    </CollapsibleTrigger>
                </CardHeader>
                <CollapsibleContent>
                    <CardContent className="flex flex-col gap-6">
                        {children}
                    </CardContent>
                </CollapsibleContent>
            </Collapsible>
        </Card>
    );
}