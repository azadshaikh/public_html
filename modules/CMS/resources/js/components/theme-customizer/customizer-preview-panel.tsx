import type { RefObject } from 'react';
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
    const previewShellClassName =
        deviceMode === 'tablet'
            ? 'w-full max-w-[820px] rounded-[28px] p-4'
            : deviceMode === 'mobile'
              ? 'w-full max-w-[390px] rounded-[32px] p-3'
              : 'h-full w-full rounded-[24px] p-3';

    const previewCanvasClassName =
        deviceMode === 'desktop'
            ? 'items-stretch justify-stretch p-2 sm:p-3 lg:p-4'
            : 'items-start justify-center p-4 sm:p-6 lg:p-8';

    return (
        <div className="flex h-full min-h-0 flex-col overflow-hidden rounded-[28px] border border-slate-200/80 bg-[#eef1f5] shadow-[inset_0_1px_0_rgba(255,255,255,0.85)]">
            <div className={cn('flex min-h-0 flex-1 overflow-auto', previewCanvasClassName)}>
                <div
                    className={cn(
                        'mx-auto flex overflow-hidden border border-slate-200/90 bg-white shadow-[0_14px_34px_rgba(15,23,42,0.12)] transition-all duration-300',
                        deviceMode === 'desktop' && 'min-h-0',
                        previewShellClassName,
                    )}
                >
                    <iframe
                        ref={iframeRef}
                        key={iframeSource}
                        src={iframeSource}
                        onLoad={onLoad}
                        className={cn(
                            'block w-full overflow-hidden rounded-[18px] border-0 bg-white shadow-[inset_0_0_0_1px_rgba(148,163,184,0.16)]',
                            deviceMode === 'desktop' && 'h-full min-h-full rounded-[20px]',
                            deviceMode === 'tablet' && 'min-h-[860px] rounded-[18px]',
                            deviceMode === 'mobile' && 'min-h-[780px] rounded-[24px]',
                        )}
                        title="Theme preview"
                    />
                </div>
            </div>
        </div>
    );
}
