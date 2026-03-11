import type { ReactNode } from 'react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type ResourceStatCardProps = {
    title: string;
    value: ReactNode;
    description: string;
};

export function ResourceStatCard({
    title,
    value,
    description,
}: ResourceStatCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardDescription>{title}</CardDescription>
                <CardTitle className="text-3xl">{value}</CardTitle>
            </CardHeader>
            <CardContent className="pt-0 text-sm text-muted-foreground">
                {description}
            </CardContent>
        </Card>
    );
}
