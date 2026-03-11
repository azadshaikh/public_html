import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { cn } from '@/lib/utils';

type ResourceSectionCardProps = {
    title: string;
    description?: string;
    children: ReactNode;
    contentClassName?: string;
};

export function ResourceSectionCard({
    title,
    description,
    children,
    contentClassName,
}: ResourceSectionCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>{title}</CardTitle>
                {description ? (
                    <CardDescription>{description}</CardDescription>
                ) : null}
            </CardHeader>
            <CardContent className={cn(contentClassName)}>
                {children}
            </CardContent>
        </Card>
    );
}
