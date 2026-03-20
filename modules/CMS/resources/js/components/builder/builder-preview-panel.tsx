import type { RefObject } from 'react';
import { cn } from '@/lib/utils';
import type { BuilderDeviceMode } from './builder-utils';

type BuilderPreviewPanelProps = {
    deviceMode: BuilderDeviceMode;
    iframeRef: RefObject<HTMLIFrameElement | null>;
    onLoad: () => void;
    previewUrl?: string | null;
    previewHtml?: string;
    title: string;
};

export function BuilderPreviewPanel({
    deviceMode,
    iframeRef,
    onLoad,
    previewUrl,
    previewHtml,
    title,
}: BuilderPreviewPanelProps) {
    const previewShellClassName =
        deviceMode === 'tablet'
            ? 'w-full max-w-[820px] rounded-[28px] p-4'
            : deviceMode === 'mobile'
              ? 'w-full max-w-[390px] rounded-[32px] p-3'
              : 'h-full w-full p-0';

    const previewCanvasClassName =
        deviceMode === 'desktop'
            ? 'items-stretch justify-stretch p-0'
            : 'items-start justify-center p-4 sm:p-6 lg:p-8';

    return (
        <div className={cn(
            'flex h-full min-h-0 flex-col overflow-hidden',
            deviceMode === 'desktop'
                ? 'bg-white'
                : 'rounded-[28px] border border-slate-200/80 bg-[#eef1f5] shadow-[inset_0_1px_0_rgba(255,255,255,0.85)]',
        )}>
            <div
                className={cn(
                    'flex min-h-0 flex-1 overflow-auto',
                    previewCanvasClassName,
                )}
            >
                <div
                    className={cn(
                        'mx-auto flex overflow-hidden transition-all duration-300',
                        deviceMode === 'desktop'
                            ? 'bg-white'
                            : 'border border-slate-200/90 bg-white shadow-[0_14px_34px_rgba(15,23,42,0.12)]',
                        deviceMode === 'desktop' && 'min-h-0',
                        previewShellClassName,
                    )}
                >
                    <iframe
                        ref={iframeRef}
                        {...(previewUrl ? { src: previewUrl } : { srcDoc: previewHtml })}
                        className={cn(
                            'block w-full overflow-hidden border-0 bg-white',
                            deviceMode === 'desktop' &&
                                'h-full min-h-full',
                            deviceMode === 'tablet' &&
                                'min-h-[860px] rounded-[18px] shadow-[inset_0_0_0_1px_rgba(148,163,184,0.16)]',
                            deviceMode === 'mobile' &&
                                'min-h-[780px] rounded-[24px] shadow-[inset_0_0_0_1px_rgba(148,163,184,0.16)]',
                        )}
                        onLoad={onLoad}
                        {...(!previewUrl && { sandbox: 'allow-scripts' })}
                        title={title}
                    />
                </div>
            </div>
        </div>
    );
}
