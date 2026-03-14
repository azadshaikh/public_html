import { Rocket } from 'lucide-react';
import AppHead from '@/components/app-head';
import AuthLayout from '@/layouts/auth-layout';

type ComingSoonProps = {
    comingSoonMessage?: string | null;
};

export default function ComingSoon({ comingSoonMessage }: ComingSoonProps) {
    return (
        <AuthLayout
            title="Coming soon"
            description="This placeholder page is ready for the full implementation later."
            maxWidthClassName="max-w-xl"
        >
            <AppHead
                title="Coming soon"
                description="Placeholder error page for future coming soon handling."
            />

            <div className="rounded-3xl border border-border/70 bg-card p-8 text-center shadow-sm">
                <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <Rocket className="size-6" />
                </div>
                <div className="mt-4 space-y-2">
                    <h2 className="text-xl font-semibold">Coming soon</h2>
                    <p className="text-sm leading-6 text-muted-foreground">
                        {comingSoonMessage?.trim() ||
                            'A dedicated Inertia implementation can be added here when this flow is ready.'}
                    </p>
                </div>
            </div>
        </AuthLayout>
    );
}
