import type { RefObject } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { DeviceMode } from '../../pages/cms/themes/customizer/types';

type ThemeCustomizerPreviewPanelProps = {
    deviceMode: DeviceMode;
    iframeRef: RefObject<HTMLIFrameElement | null>;
    iframeSource: string;
    onLoad: () => void;
};

export function ThemeCustomizerPreviewPanel({
    deviceMode,
    iframeRef,
    iframeSource,
    onLoad,
}: ThemeCustomizerPreviewPanelProps) {
    const previewFrameClassName =
        deviceMode === 'tablet'
            ? 'w-full max-w-[820px]'
            : deviceMode === 'mobile'
              ? 'w-full max-w-[390px]'
              : 'w-full';

    return (
        <div className="flex h-full min-h-0 flex-col overflow-hidden rounded-[24px] border border-white/70 bg-white/82 shadow-[0_20px_60px_rgba(15,23,42,0.08)] backdrop-blur-sm">
            <div className="flex items-center justify-between gap-3 border-b border-border/60 px-3 py-2.5 sm:px-4">
                <div className="min-w-0">
                    <p className="text-sm font-semibold text-foreground">Frontend preview</p>
                    <p className="hidden text-xs text-muted-foreground sm:block">
                        Navigate inside the frame to inspect live theme changes before saving.
                    </p>
                </div>
                <Badge variant="outline" className="capitalize">
                    {deviceMode}
                </Badge>
            </div>

            <div className="min-h-0 flex-1 overflow-auto p-2 sm:p-3">
                <div
                    className={cn(
                        'mx-auto overflow-hidden rounded-[20px] border border-slate-200 bg-white shadow-[0_20px_50px_rgba(15,23,42,0.12)] transition-all duration-300',
                        previewFrameClassName,
                    )}
                >
                    <iframe
                        ref={iframeRef}
                        key={iframeSource}
                        src={iframeSource}
                        onLoad={onLoad}
                        className={cn(
                            'block w-full border-0 bg-white',
                            deviceMode === 'desktop' && 'min-h-[calc(100vh-11rem)]',
                            deviceMode === 'tablet' && 'min-h-[860px]',
                            deviceMode === 'mobile' && 'min-h-[780px]',
                        )}
                        title="Theme preview"
                    />
                </div>
            </div>
        </div>
    );
}