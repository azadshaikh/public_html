import {
    ArrowLeftIcon,
    ExternalLinkIcon,
    LaptopIcon,
    PanelLeftCloseIcon,
    PanelLeftOpenIcon,
    PanelRightCloseIcon,
    PanelRightOpenIcon,
    Redo2Icon,
    SaveIcon,
    SmartphoneIcon,
    TabletIcon,
    Undo2Icon,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import type { BuilderThemeSummary } from '../../types/cms';
import type { BuilderDeviceMode } from './builder-utils';

type BuilderHeaderProps = {
    activeTheme: BuilderThemeSummary | null;
    pageTitle: string;
    deviceMode: BuilderDeviceMode;
    leftPanelCollapsed: boolean;
    rightPanelCollapsed: boolean;
    isSaving: boolean;
    isDirty: boolean;
    canUndo: boolean;
    canRedo: boolean;
    backHref: string;
    viewHref: string | null;
    onToggleLeftPanel: () => void;
    onToggleRightPanel: () => void;
    onOpenMobileSidebar: () => void;
    onDeviceModeChange: (deviceMode: BuilderDeviceMode) => void;
    onSave: () => void;
    onUndo: () => void;
    onRedo: () => void;
};

export function BuilderHeader({
    activeTheme,
    pageTitle,
    deviceMode,
    leftPanelCollapsed,
    rightPanelCollapsed,
    isSaving,
    isDirty,
    canUndo,
    canRedo,
    backHref,
    viewHref,
    onToggleLeftPanel,
    onToggleRightPanel,
    onOpenMobileSidebar,
    onDeviceModeChange,
    onSave,
    onUndo,
    onRedo,
}: BuilderHeaderProps) {
    return (
        <header className="flex h-[42px] shrink-0 items-center border-b border-border/70 bg-background/95 px-2 backdrop-blur">
            {/* Left section: Back + Panel toggle */}
            <div className="flex items-center gap-1">
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button variant="ghost" size="icon-sm" asChild>
                            <a href={backHref}>
                                <ArrowLeftIcon className="size-4" />
                            </a>
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">Back to editor</TooltipContent>
                </Tooltip>

                <Separator orientation="vertical" className="mx-1 h-5" />

                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            onClick={onToggleLeftPanel}
                            className="hidden lg:inline-flex"
                        >
                            {leftPanelCollapsed ? (
                                <PanelLeftOpenIcon className="size-4" />
                            ) : (
                                <PanelLeftCloseIcon className="size-4" />
                            )}
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        {leftPanelCollapsed ? 'Show components' : 'Hide components'}
                    </TooltipContent>
                </Tooltip>

                <Button
                    variant="ghost"
                    size="icon-sm"
                    onClick={onOpenMobileSidebar}
                    className="lg:hidden"
                >
                    <PanelLeftOpenIcon className="size-4" />
                </Button>

                <Separator orientation="vertical" className="mx-1 h-5" />

                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            onClick={onUndo}
                            disabled={!canUndo}
                        >
                            <Undo2Icon className="size-4" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">Undo (Ctrl+Z)</TooltipContent>
                </Tooltip>

                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            onClick={onRedo}
                            disabled={!canRedo}
                        >
                            <Redo2Icon className="size-4" />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">Redo (Ctrl+Y)</TooltipContent>
                </Tooltip>
            </div>

            {/* Center section: Title + Device modes */}
            <div className="flex min-w-0 flex-1 items-center justify-center gap-3">
                <h1 className="hidden max-w-[200px] truncate text-xs font-medium text-foreground/80 md:block">
                    {pageTitle}
                </h1>

                <div className="hidden md:block">
                    <ToggleGroup
                        type="single"
                        size="sm"
                        value={deviceMode}
                        onValueChange={(value) => {
                            if (value !== '') {
                                onDeviceModeChange(value as BuilderDeviceMode);
                            }
                        }}
                        className="gap-0"
                    >
                        <ToggleGroupItem
                            value="desktop"
                            aria-label="Desktop"
                            className="size-7 rounded-r-none p-0"
                        >
                            <LaptopIcon className="size-3.5" />
                        </ToggleGroupItem>
                        <ToggleGroupItem
                            value="tablet"
                            aria-label="Tablet"
                            className="size-7 rounded-none border-x-0 p-0"
                        >
                            <TabletIcon className="size-3.5" />
                        </ToggleGroupItem>
                        <ToggleGroupItem
                            value="mobile"
                            aria-label="Mobile"
                            className="size-7 rounded-l-none p-0"
                        >
                            <SmartphoneIcon className="size-3.5" />
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>
            </div>

            {/* Right section: View + Save + Panel toggle */}
            <div className="flex items-center gap-1">
                {viewHref ? (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button variant="ghost" size="icon-sm" asChild>
                                <a href={viewHref} target="_blank" rel="noopener noreferrer">
                                    <ExternalLinkIcon className="size-4" />
                                </a>
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent side="bottom">View page</TooltipContent>
                    </Tooltip>
                ) : null}

                <Separator orientation="vertical" className="mx-1 h-5" />

                <Button
                    size="sm"
                    onClick={onSave}
                    disabled={isSaving}
                    className="h-7 gap-1.5 px-3 text-xs"
                >
                    <SaveIcon className={isSaving ? 'size-3.5 animate-spin' : 'size-3.5'} />
                    {isSaving ? 'Saving...' : 'Save'}
                </Button>

                <Separator orientation="vertical" className="mx-1 hidden h-5 lg:block" />

                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            onClick={onToggleRightPanel}
                            className="hidden lg:inline-flex"
                        >
                            {rightPanelCollapsed ? (
                                <PanelRightOpenIcon className="size-4" />
                            ) : (
                                <PanelRightCloseIcon className="size-4" />
                            )}
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        {rightPanelCollapsed ? 'Show properties' : 'Hide properties'}
                    </TooltipContent>
                </Tooltip>
            </div>
        </header>
    );
}
