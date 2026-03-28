import { SaveIcon, SparklesIcon } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';

type LocalSeoScoreCardProps = {
    score: {
        score: number;
        grade: string;
        completed: number;
        total: number;
    };
    processing: boolean;
};

export function LocalSeoScoreCard({
    score,
    processing,
}: LocalSeoScoreCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Completion score</CardTitle>
                <CardDescription>
                    A quick view of how complete the local profile is for search
                    engines.
                </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
                <div className="flex items-end justify-between gap-3">
                    <div>
                        <div className="text-4xl font-semibold tabular-nums">
                            {score.score}
                            <span className="text-lg text-muted-foreground">
                                %
                            </span>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {score.completed} of {score.total} recommended
                            signals completed.
                        </p>
                    </div>
                    <Badge variant="secondary">Grade {score.grade}</Badge>
                </div>
                <div className="h-2 rounded-full bg-muted">
                    <div
                        className="h-2 rounded-full bg-primary transition-all"
                        style={{ width: `${score.score}%` }}
                    />
                </div>
                <Alert>
                    <SparklesIcon className="size-4" />
                    <AlertTitle>Recommended next step</AlertTitle>
                    <AlertDescription>
                        {score.score >= 80
                            ? 'Your profile is in strong shape. Keep hours and profile links updated.'
                            : 'Add a logo, full address, and official social links to improve trust signals.'}
                    </AlertDescription>
                </Alert>
            </CardContent>
            <CardFooter>
                <Button type="submit" className="w-full" disabled={processing}>
                    {processing ? (
                        <Spinner className="mr-2 size-4" />
                    ) : (
                        <SaveIcon data-icon="inline-start" />
                    )}
                    Save local SEO settings
                </Button>
            </CardFooter>
        </Card>
    );
}
