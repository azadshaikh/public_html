import { Link } from '@inertiajs/react';
import { GlobeIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type DomainRow = {
    id: number;
    name: string | null;
    domain: string;
    status: string;
    status_label: string;
    dns_mode: 'managed' | 'external' | 'subdomain';
};

type AgencyDomainsIndexPageProps = {
    websites: DomainRow[];
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'Domains', href: route('agency.domains.index') },
];

function dnsModeLabel(value: DomainRow['dns_mode']): string {
    switch (value) {
        case 'managed':
            return 'Managed DNS';
        case 'external':
            return 'External DNS';
        default:
            return 'Subdomain';
    }
}

export default function AgencyDomainsIndex({
    websites,
}: AgencyDomainsIndexPageProps) {
    return (
        <AppLayout
            breadcrumbs={breadcrumbs}
            title="Domains"
            description="Inspect DNS mode and open per-domain configuration details."
        >
            {websites.length === 0 ? (
                <Card>
                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                        <GlobeIcon className="mb-3 size-10 text-muted-foreground" />
                        <p className="font-medium">No domains found</p>
                        <p className="text-sm text-muted-foreground">
                            Domains will appear here once websites are created.
                        </p>
                    </CardContent>
                </Card>
            ) : (
                <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                    {websites.map((website) => (
                        <Card key={website.id}>
                            <CardHeader>
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <CardTitle className="text-lg">
                                            <Link
                                                href={route('agency.domains.show', website.id)}
                                                className="hover:text-primary"
                                            >
                                                {website.domain}
                                            </Link>
                                        </CardTitle>
                                        <CardDescription>
                                            {website.name && website.name !== website.domain
                                                ? website.name
                                                : 'Connected website'}
                                        </CardDescription>
                                    </div>
                                    <Badge variant="secondary">
                                        {website.status_label}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="flex items-center justify-between text-sm">
                                <span className="text-muted-foreground">
                                    {dnsModeLabel(website.dns_mode)}
                                </span>
                                <Link
                                    href={route('agency.domains.show', website.id)}
                                    className="font-medium text-primary"
                                >
                                    Open
                                </Link>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            )}
        </AppLayout>
    );
}
