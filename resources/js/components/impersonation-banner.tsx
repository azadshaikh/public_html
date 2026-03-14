import { Link, usePage } from '@inertiajs/react';
import { ArrowLeftRightIcon, ShieldAlertIcon } from 'lucide-react';
import {
    Alert,
    AlertAction,
    AlertDescription,
    AlertTitle,
} from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import type { AuthenticatedSharedData } from '@/types';

export function ImpersonationBanner() {
    const { auth } = usePage<AuthenticatedSharedData>().props;
    const impersonation = auth.impersonation;

    if (!impersonation?.active) {
        return null;
    }

    return (
        <div className="border-b border-amber-200/70 bg-amber-50/90 dark:border-amber-500/20 dark:bg-amber-500/10">
            <div className="mx-auto w-full max-w-7xl px-4 py-3 sm:px-6 lg:px-8">
                <Alert className="border-amber-200 bg-transparent text-amber-950 shadow-none dark:border-amber-500/30 dark:text-amber-100">
                    <ShieldAlertIcon className="size-4 text-amber-700 dark:text-amber-300" />
                    <AlertTitle className="text-amber-950 dark:text-amber-100">
                        Impersonation active
                    </AlertTitle>
                    <AlertDescription className="pr-4 text-amber-800 dark:text-amber-100">
                        You are signed in as {auth.user.name}. Original account:{' '}
                        {impersonation.impersonator.name} (
                        {impersonation.impersonator.email}).
                    </AlertDescription>
                    <AlertAction>
                        <Button
                            asChild
                            variant="outline"
                            className="border-amber-300 bg-white/80 text-amber-900 hover:bg-white dark:border-amber-400/30 dark:bg-amber-950/40 dark:text-amber-100 dark:hover:bg-amber-950/60"
                        >
                            <Link
                                href={impersonation.stopUrl}
                                method="post"
                                as="button"
                            >
                                <ArrowLeftRightIcon data-icon="inline-start" />
                                Stop impersonating
                            </Link>
                        </Button>
                    </AlertAction>
                </Alert>
            </div>
        </div>
    );
}
