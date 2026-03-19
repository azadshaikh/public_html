import { router } from '@inertiajs/react';
import {
    CircleCheckBigIcon,
    LoaderCircleIcon,
    RefreshCwIcon,
    WifiOffIcon,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type ConnectionBannerState = 'hidden' | 'offline' | 'restored';

function isReachabilityError(error: unknown): boolean {
    if (!(error instanceof Error)) {
        return false;
    }

    return /network|fetch|load failed|failed to fetch|network request failed|internet|offline/i.test(
        error.message,
    );
}

export default function AppConnectionStatus() {
    const [bannerState, setBannerState] = useState<ConnectionBannerState>(() =>
        typeof navigator !== 'undefined' && !navigator.onLine ? 'offline' : 'hidden',
    );
    const [isRetrying, setIsRetrying] = useState(false);
    const [detailMessage, setDetailMessage] = useState<string | null>(null);
    const wasDisconnectedRef = useRef(
        typeof navigator !== 'undefined' ? !navigator.onLine : false,
    );
    const retryTimeoutRef = useRef<number | null>(null);

    useEffect(() => {
        const handleOffline = () => {
            wasDisconnectedRef.current = true;
            setIsRetrying(false);
            setDetailMessage(
                'Check your Wi-Fi or mobile data. We will reconnect automatically as soon as the network is back.',
            );
            setBannerState('offline');
        };

        const handleOnline = () => {
            setIsRetrying(false);

            if (!wasDisconnectedRef.current) {
                return;
            }

            wasDisconnectedRef.current = false;
            setDetailMessage('Connection restored. You can continue where you left off.');
            setBannerState('restored');
        };

        const removeNetworkErrorListener = router.on('networkError', (event) => {
            const error = event.detail.exception;

            if (!isReachabilityError(error) && navigator.onLine) {
                return;
            }

            event.preventDefault();
            wasDisconnectedRef.current = true;
            setIsRetrying(false);
            setDetailMessage(
                navigator.onLine
                    ? 'We cannot reach the server right now. Your internet may be unstable.'
                    : 'The internet connection dropped while the app was talking to the server.',
            );
            setBannerState('offline');
        });

        window.addEventListener('offline', handleOffline);
        window.addEventListener('online', handleOnline);

        return () => {
            if (retryTimeoutRef.current !== null) {
                window.clearTimeout(retryTimeoutRef.current);
            }

            removeNetworkErrorListener();
            window.removeEventListener('offline', handleOffline);
            window.removeEventListener('online', handleOnline);
        };
    }, []);

    useEffect(() => {
        if (bannerState !== 'restored') {
            return;
        }

        const timeout = window.setTimeout(() => {
            setBannerState('hidden');
            setDetailMessage(null);
        }, 2600);

        return () => {
            window.clearTimeout(timeout);
        };
    }, [bannerState]);

    if (bannerState === 'hidden') {
        return null;
    }

    const isOffline = bannerState === 'offline';

    return (
        <div className="pointer-events-none fixed inset-x-4 bottom-4 z-[120] sm:inset-x-auto sm:right-4 sm:bottom-4 sm:w-[26rem]">
            <div
                role="status"
                aria-live="polite"
                className={cn(
                    'pointer-events-auto overflow-hidden rounded-[2rem] border shadow-2xl ring-1 backdrop-blur-xl transition-all duration-300',
                    'bg-background/92 dark:bg-card/92',
                    isOffline
                        ? 'border-amber-300/70 ring-amber-500/15 dark:border-amber-500/30 dark:ring-amber-400/10'
                        : 'border-emerald-300/70 ring-emerald-500/15 dark:border-emerald-500/30 dark:ring-emerald-400/10',
                )}
            >
                <div
                    className={cn(
                        'absolute inset-x-0 top-0 h-1',
                        isOffline
                            ? 'bg-linear-to-r from-amber-400 via-orange-400 to-rose-400'
                            : 'bg-linear-to-r from-emerald-400 via-teal-400 to-sky-400',
                    )}
                />

                <div className="relative flex items-start gap-4 p-4 sm:p-5">
                    <div
                        className={cn(
                            'flex size-12 shrink-0 items-center justify-center rounded-2xl ring-1',
                            isOffline
                                ? 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-200 dark:ring-amber-400/20'
                                : 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-200 dark:ring-emerald-400/20',
                        )}
                    >
                        {isOffline ? (
                            <WifiOffIcon className="size-5" />
                        ) : (
                            <CircleCheckBigIcon className="size-5" />
                        )}
                    </div>

                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <p className="text-base font-semibold text-foreground">
                                {isOffline ? 'Connection lost' : 'Back online'}
                            </p>
                            <span
                                className={cn(
                                    'inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-medium tracking-[0.14em] uppercase',
                                    isOffline
                                        ? 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200'
                                        : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200',
                                )}
                            >
                                {isOffline ? 'Offline' : 'Restored'}
                            </span>
                        </div>

                        <p className="mt-1.5 text-sm leading-6 text-muted-foreground">
                            {detailMessage ??
                                (isOffline
                                    ? 'The app cannot reach the internet right now. Any new requests will fail until the connection comes back.'
                                    : 'The app is connected again.')}
                        </p>

                        <div className="mt-4 flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant={isOffline ? 'default' : 'secondary'}
                                onClick={() => {
                                    if (isRetrying) {
                                        return;
                                    }

                                    setIsRetrying(true);
                                    setDetailMessage('Checking connection...');

                                    if (retryTimeoutRef.current !== null) {
                                        window.clearTimeout(retryTimeoutRef.current);
                                    }

                                    retryTimeoutRef.current = window.setTimeout(() => {
                                        retryTimeoutRef.current = null;

                                        if (!navigator.onLine) {
                                            setIsRetrying(false);
                                            setDetailMessage(
                                                'Still offline. Your current work is safe in this tab, and we will reconnect automatically once the network returns.',
                                            );

                                            return;
                                        }

                                        router.reload({
                                            onFinish: () => {
                                                setIsRetrying(false);
                                            },
                                        });
                                    }, 850);
                                }}
                            >
                                {isRetrying ? (
                                    <LoaderCircleIcon className="size-4 animate-spin" />
                                ) : (
                                    <RefreshCwIcon className="size-4" />
                                )}
                                Retry now
                            </Button>

                            {!isOffline ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => {
                                        setBannerState('hidden');
                                        setDetailMessage(null);
                                    }}
                                >
                                    Dismiss
                                </Button>
                            ) : null}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}