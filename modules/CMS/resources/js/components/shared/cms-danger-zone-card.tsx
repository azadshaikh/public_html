import { Trash2Icon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

type CmsDangerZoneCardProps = {
    show: boolean;
    description: string;
    onDelete: () => void;
};

export function CmsDangerZoneCard({
    show,
    description,
    onDelete,
}: CmsDangerZoneCardProps) {
    if (!show) {
        return null;
    }

    return (
        <Card className="border-destructive/30">
            <CardHeader>
                <CardTitle>Danger zone</CardTitle>
                <CardDescription>{description}</CardDescription>
            </CardHeader>
            <CardFooter>
                <Button
                    type="button"
                    variant="destructive"
                    className="w-full"
                    onClick={onDelete}
                >
                    <Trash2Icon data-icon="inline-start" />
                    Move to Trash
                </Button>
            </CardFooter>
        </Card>
    );
}
