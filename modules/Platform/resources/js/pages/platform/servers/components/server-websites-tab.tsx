import { Link } from '@inertiajs/react';
import { ExternalLinkIcon, GlobeIcon, RefreshCwIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { WebsiteListItem } from '../../../types/platform';
import { statusBadgeVariant } from './show-shared';

type WebsiteApiItem = {
    id: number;
    uid: string | null;
    name: string;
    domain: string;
    status: {
        value: string;
        label: string;
        color: string;
    };
    created_at: string | null;
    urls: {
        show: string;
        domain: string | null;
    };
};

type ServerWebsitesTabProps = {
    serverId: number;
    active: boolean;
};

export function ServerWebsitesTab({ serverId, active }: ServerWebsitesTabProps) {
    const [items, setItems] = useState<WebsiteApiItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [loaded, setLoaded] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function loadWebsites(): Promise<void> {
        setLoading(true);
        setError(null);

        try {
            const response = await fetch(route('platform.servers.websites', { server: serverId }), {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Unable to load websites.');
            }

            const payload = (await response.json()) as {
                status?: string;
                data?: {
                    items?: WebsiteApiItem[];
                };
            };

            setItems(Array.isArray(payload.data?.items) ? payload.data.items : []);
            setLoaded(true);
        } catch (loadError) {
            setError(loadError instanceof Error ? loadError.message : 'Unable to load websites.');
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        if (active && !loaded && !loading) {
            void loadWebsites();
        }
    }, [active, loaded, loading]);

    if (loading && !loaded) {
        return (
            <div className="flex min-h-40 items-center justify-center">
                <Spinner className="size-5" />
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex flex-col items-center justify-center gap-3 py-10 text-center">
                <p className="text-sm text-destructive">{error}</p>
                <Button variant="outline" onClick={() => void loadWebsites()}>
                    <RefreshCwIcon data-icon="inline-start" />
                    Retry
                </Button>
            </div>
        );
    }

    if (items.length === 0) {
        return (
            <div className="py-10 text-center text-muted-foreground">
                <GlobeIcon className="mx-auto mb-3 size-8 opacity-50" />
                <p>No websites are currently assigned to this server.</p>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-3">
            <div className="flex justify-end">
                <Button variant="outline" size="sm" onClick={() => void loadWebsites()}>
                    <RefreshCwIcon data-icon="inline-start" />
                    Refresh
                </Button>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="border-b text-left">
                            <th className="pb-3 pr-4 font-semibold text-muted-foreground">Website</th>
                            <th className="pb-3 pr-4 font-semibold text-muted-foreground">Domain</th>
                            <th className="pb-3 pr-4 font-semibold text-muted-foreground">Status</th>
                            <th className="pb-3 pr-4 font-semibold text-muted-foreground">Created</th>
                            <th className="pb-3 text-right font-semibold text-muted-foreground">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((website) => (
                            <tr key={website.id} className="border-b last:border-0">
                                <td className="py-4 pr-4 font-medium">{website.name}</td>
                                <td className="py-4 pr-4 text-muted-foreground">{website.domain}</td>
                                <td className="py-4 pr-4">
                                    <Badge variant={statusBadgeVariant(website.status.value)}>{website.status.label}</Badge>
                                </td>
                                <td className="py-4 pr-4 text-muted-foreground">{website.created_at ?? '—'}</td>
                                <td className="py-4 text-right">
                                    <div className="flex justify-end gap-2">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={website.urls.show}>Open</Link>
                                        </Button>
                                        {website.urls.domain ? (
                                            <Button variant="ghost" size="sm" asChild>
                                                <a href={website.urls.domain} target="_blank" rel="noreferrer">
                                                    <ExternalLinkIcon className="size-3.5" />
                                                </a>
                                            </Button>
                                        ) : null}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}