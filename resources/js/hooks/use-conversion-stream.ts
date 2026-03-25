import { useEffect, useRef } from 'react';

export type ConversionStatus = {
    status: 'completed' | 'processing' | 'failed' | 'not_found' | 'not_applicable';
    conversions: string[];
    pending: string[];
    failed: string[];
    error?: string;
};

type RawConversionEvent = {
    media_id: number;
    conversion_status: ConversionStatus;
};

/**
 * Opens an SSE stream to `app.media.conversion-stream` for the given media IDs
 * and calls `onUpdate` whenever a media item's status changes.
 *
 * The stream is only opened when `mediaIds` has entries and `enabled` is true.
 * It auto-closes when every item reaches a terminal status or after 60 s server-side.
 */
export function useConversionStream(
    mediaIds: number[],
    enabled: boolean,
    onUpdate: (mediaId: number, status: ConversionStatus) => void,
): void {
    const onUpdateRef = useRef(onUpdate);
    onUpdateRef.current = onUpdate;

    useEffect(() => {
        if (!enabled || mediaIds.length === 0) {
            return;
        }

        const params = new URLSearchParams();
        mediaIds.forEach((id) => params.append('ids[]', String(id)));

        const url = `${route('app.media.conversion-stream')}?${params.toString()}`;

        let cancelled = false;
        const abort = new AbortController();

        (async () => {
            try {
                const response = await fetch(url, {
                    signal: abort.signal,
                    headers: { Accept: 'text/event-stream' },
                    credentials: 'same-origin',
                });

                if (!response.ok || !response.body) {
                    return;
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder();
                let buffer = '';

                while (!cancelled) {
                    const { done, value } = await reader.read();

                    if (done) {
                        break;
                    }

                    buffer += decoder.decode(value, { stream: true });
                    const lines = buffer.split('\n');
                    buffer = lines.pop() ?? '';

                    for (const line of lines) {
                        if (!line.startsWith('data: ')) {
                            continue;
                        }

                        const raw = line.slice(6).trim();

                        if (raw === '[DONE]') {
                            return;
                        }

                        try {
                            const event = JSON.parse(raw) as RawConversionEvent;
                            onUpdateRef.current(
                                event.media_id,
                                event.conversion_status,
                            );
                        } catch {
                            // skip malformed frames
                        }
                    }
                }
            } catch {
                // stream aborted or network error — silent
            }
        })();

        return () => {
            cancelled = true;
            abort.abort();
        };
        // Re-open stream when the set of tracked IDs or enabled flag changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [enabled, mediaIds.join(',')]);
}
