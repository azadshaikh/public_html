import { FileWarning } from 'lucide-react';
import AppHead from '@/components/app-head';
import AuthLayout from '@/layouts/auth-layout';

type TwigErrorProps = {
    template?: string | null;
    error?: string | null;
};

export default function TwigError({ template, error }: TwigErrorProps) {
    return (
        <AuthLayout
            title="Template error"
            description="This placeholder page is ready for a richer template-debug experience later."
            maxWidthClassName="max-w-xl"
        >
            <AppHead
                title="Template error"
                description="Placeholder error page for future Twig error handling."
            />

            <div className="rounded-3xl border border-border/70 bg-card p-8 text-center shadow-sm">
                <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-destructive/10 text-destructive">
                    <FileWarning className="size-6" />
                </div>
                <div className="mt-4 space-y-2">
                    <h2 className="text-xl font-semibold">
                        Template error placeholder
                    </h2>
                    <p className="text-sm leading-6 text-muted-foreground">
                        A dedicated debugging view can be implemented later for
                        template rendering failures.
                    </p>
                    {template ? (
                        <p className="text-xs text-muted-foreground">
                            Template:{' '}
                            <span className="font-medium text-foreground">
                                {template}
                            </span>
                        </p>
                    ) : null}
                    {error ? (
                        <p className="text-xs text-muted-foreground">{error}</p>
                    ) : null}
                </div>
            </div>
        </AuthLayout>
    );
}
