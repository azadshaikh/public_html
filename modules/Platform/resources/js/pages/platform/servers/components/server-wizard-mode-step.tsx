import { ServerCogIcon, ServerIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type ModeCardProps = {
    mode: 'manual' | 'provision';
    title: string;
    description: string;
    badge: string;
    icon: React.ComponentType<{ className?: string }>;
    selected: boolean;
    onSelect: (mode: 'manual' | 'provision') => void;
};

function ModeCard({ mode, title, description, badge, icon: Icon, selected, onSelect }: ModeCardProps) {
    return (
        <button
            type="button"
            onClick={() => onSelect(mode)}
            className={['group text-left transition-all', selected ? 'scale-[1.01]' : ''].join(' ')}
        >
            <Card
                className={[
                    'h-full border-2 shadow-sm transition-all group-hover:-translate-y-0.5 group-hover:shadow-md',
                    selected ? 'border-primary bg-primary/5' : 'border-border/70 bg-card',
                ].join(' ')}
            >
                <CardContent className="flex h-full flex-col items-center gap-4 px-8 py-10 text-center">
                    <div
                        className={[
                            'flex size-16 items-center justify-center rounded-2xl border',
                            selected
                                ? 'border-primary/30 bg-primary/10 text-primary'
                                : 'border-border bg-muted/40 text-muted-foreground',
                        ].join(' ')}
                    >
                        <Icon className="size-8" />
                    </div>
                    <div className="space-y-2">
                        <h3 className="text-lg font-semibold tracking-tight">{title}</h3>
                        <p className="text-sm leading-6 text-muted-foreground">{description}</p>
                    </div>
                    <Badge variant={selected ? 'default' : 'secondary'}>{badge}</Badge>
                </CardContent>
            </Card>
        </button>
    );
}

type ServerWizardModeStepProps = {
    selectedMode: 'manual' | 'provision' | null;
    onSelectMode: (mode: 'manual' | 'provision') => void;
    onContinue: () => void;
};

export function ServerWizardModeStep({
    selectedMode,
    onSelectMode,
    onContinue,
}: ServerWizardModeStepProps) {
    return (
        <section className="space-y-6">
            <div className="mx-auto max-w-2xl space-y-2 text-center">
                <Badge variant="secondary" className="rounded-full px-3 py-1">
                    Two-step onboarding
                </Badge>
                <h2 className="text-2xl font-semibold tracking-tight">
                    How would you like to add your server?
                </h2>
                <p className="text-sm leading-6 text-muted-foreground">
                    Connect an existing HestiaCP endpoint in a few fields, or provision a fresh VPS
                    with the full Astero install profile.
                </p>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <ModeCard
                    mode="manual"
                    title="Connect Existing Server"
                    description="Server already has HestiaCP installed. Enter its API credentials and attach it to your platform."
                    badge="Quick setup"
                    icon={ServerIcon}
                    selected={selectedMode === 'manual'}
                    onSelect={onSelectMode}
                />
                <ModeCard
                    mode="provision"
                    title="Provision New Server"
                    description="Fresh Ubuntu or Debian VPS. We will authorize SSH, verify connectivity, and install HestiaCP for you."
                    badge="Auto install"
                    icon={ServerCogIcon}
                    selected={selectedMode === 'provision'}
                    onSelect={onSelectMode}
                />
            </div>

            <div className="flex justify-center">
                <Button onClick={onContinue} disabled={!selectedMode} className="min-w-40">
                    Continue
                </Button>
            </div>
        </section>
    );
}
