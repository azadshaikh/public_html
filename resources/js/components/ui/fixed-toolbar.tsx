'use client';

import type { ComponentProps } from 'react';
import { Toolbar } from '@/components/ui/toolbar';
import { cn } from '@/lib/utils';

export function FixedToolbar(
    props: ComponentProps<typeof Toolbar>,
) {
    return (
        <Toolbar
            {...props}
            className={cn(
                'sticky top-0 left-0 z-10 w-full justify-between overflow-x-auto rounded-t-[inherit] border-b border-border bg-background/95 p-1 backdrop-blur-sm supports-backdrop-blur:bg-background/60',
                props.className,
            )}
        />
    );
}
