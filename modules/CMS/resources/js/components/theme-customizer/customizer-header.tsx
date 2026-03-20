import {
    ArrowLeftIcon,
    DownloadIcon,
    ImportIcon,
    LaptopIcon,
    PanelLeftCloseIcon,
    PanelLeftOpenIcon,
    RefreshCwIcon,
    RotateCcwIcon,
    SaveIcon,
    SmartphoneIcon,
    TabletIcon,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { cn } from '@/lib/utils';
import type { DeviceMode } from '../../pages/cms/themes/customizer/types';

type ThemeCustomizerHeaderProps = {
    activeThemeName: string;
    deviceMode: DeviceMode;
    sidebarCollapsed: boolean;
    isRefreshingPreview: boolean;
    isSaving: boolean;
    onBackHref: string;
    onToggleSidebar: () => void;
    onDeviceModeChange: (deviceMode: DeviceMode) => void;
    onRefreshPreview: () => void;
    onOpenMobileSidebar: () => void;
    onExport: () => void;
    onOpenImport: () => void;
    onOpenReset: () => void;
    onSave: () => void;
};

export function ThemeCustomizerHeader({
    activeThemeName,
    deviceMode,
    sidebarCollapsed,
    isRefreshingPreview,
    isSaving,
    onBackHref,
    onToggleSidebar,
    onDeviceModeChange,
    onRefreshPreview,
    onOpenMobileSidebar,
    onExport,
    onOpenImport,
    onOpenReset,
    onSave,
}: ThemeCustomizerHeaderProps) {
    return (
        <header className="border-b border-border/70 bg-white/80 px-3 py-2 backdrop-blur sm:px-4">
            <div className="relative flex items-center justify-between gap-3">
                <div className="flex min-w-0 flex-1 items-center gap-2.5">
                    <Button variant="outline" size="sm" asChild>
                        <a href={onBackHref}>
                            <ArrowLeftIcon data-icon="inline-start" />
                            Back
                        </a>
                    </Button>

                    <div className="min-w-0">
                        <div className="flex items-center gap-2">
                            <h1 className="truncate text-sm font-semibold text-foreground sm:text-base">
                                Theme Customizer
                            </h1>
                            <Badge variant="secondary" className="hidden sm:inline-flex">
                                {activeThemeName}
                            </Badge>
                        </div>
                    </div>
                </div>

                <div className="pointer-events-none absolute left-1/2 hidden -translate-x-1/2 md:flex">
                    <ToggleGroup
                        type="single"
                        size="sm"
                        className="pointer-events-auto"
                        value={deviceMode}
                        onValueChange={(value) => {
                            if (value) {
                                onDeviceModeChange(value as DeviceMode);
                            }
                        }}
                    >
                        <ToggleGroupItem value="desktop" aria-label="Desktop preview">
                            <LaptopIcon />
                        </ToggleGroupItem>
                        <ToggleGroupItem value="tablet" aria-label="Tablet preview">
                            <TabletIcon />
                        </ToggleGroupItem>
                        <ToggleGroupItem value="mobile" aria-label="Mobile preview">
                            <SmartphoneIcon />
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>

                <div className="flex flex-1 items-center justify-end gap-1.5">
                    <div className="hidden items-center gap-1.5 lg:flex">
                        <Button
                            variant="outline"
                            size="icon-sm"
                            onClick={onToggleSidebar}
                            title={sidebarCollapsed ? 'Show settings' : 'Hide settings'}
                        >
                            {sidebarCollapsed ? <PanelLeftOpenIcon /> : <PanelLeftCloseIcon />}
                        </Button>
                    </div>

                    <Button
                        variant="outline"
                        size="icon-sm"
                        onClick={onRefreshPreview}
                        disabled={isRefreshingPreview}
                        title="Refresh preview"
                    >
                        <RefreshCwIcon className={cn(isRefreshingPreview && 'animate-spin')} />
                    </Button>

                    <div className="lg:hidden">
                        <Button
                            variant="outline"
                            size="icon-sm"
                            onClick={onOpenMobileSidebar}
                            title="Open settings"
                        >
                            <PanelLeftOpenIcon />
                        </Button>
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm">More</Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuGroup>
                                <DropdownMenuItem onClick={onExport}>
                                    <DownloadIcon />
                                    Export settings
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={onOpenImport}>
                                    <ImportIcon />
                                    Import settings
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={onOpenReset}>
                                    <RotateCcwIcon />
                                    Reset to defaults
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Button size="sm" onClick={onSave} disabled={isSaving}>
                        <SaveIcon data-icon="inline-start" className={cn(isSaving && 'animate-pulse')} />
                        Save
                    </Button>
                </div>
            </div>
        </header>
    );
}
