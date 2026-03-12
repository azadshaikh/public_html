import { Wrench } from 'lucide-react';
import AppHead from '@/components/app-head';
import AuthLayout from '@/layouts/auth-layout';

type MaintenanceProps = {
    maintenanceMessage?: string | null;
};

export default function Maintenance({ maintenanceMessage }: MaintenanceProps) {
    return (
        <AuthLayout
            title="Under maintenance"
            description="This placeholder page is ready for the full maintenance experience later."
            maxWidthClassName="max-w-xl"
        >
            <AppHead
                title="Under maintenance"
                description="Placeholder error page for future maintenance handling."
            />

            <div className="rounded-3xl border border-border/70 bg-card p-8 text-center shadow-sm">
                <div className="mx-auto flex size-14 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400">
                    <Wrench className="size-6" />
                </div>
                <div className="mt-4 space-y-2">
                    <h2 className="text-xl font-semibold">Under maintenance</h2>
                    <p className="text-sm leading-6 text-muted-foreground">
                        {maintenanceMessage?.trim() ||
                            'A dedicated maintenance experience can be added here when the backend flow is wired.'}
                    </p>
                </div>
            </div>
        </AuthLayout>
    );
}