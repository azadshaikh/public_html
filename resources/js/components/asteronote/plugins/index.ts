/**
 * AsteroNote Plugin Registry
 *
 * Each plugin is a self-contained React component that receives the
 * AsteroNoteController interface and renders toolbar UI.
 *
 * The plugin system mirrors the old vanilla JS BasePlugin architecture:
 * - Each plugin owns its own toolbar UI (button/dropdown/dialog)
 * - Plugins communicate with the editor via the controller interface
 * - The registry maps action names to plugin components
 */

import type { AsteroNotePluginComponent } from '@/components/asteronote/asteronote-types';
import { AlignPluginControl } from '@/components/asteronote/plugins/align';
import {
    FormatBlockPluginControl,
    HeadingPluginControl,
} from '@/components/asteronote/plugins/block-format';
import {
    CodeViewPluginControl,
    FullscreenPluginControl,
} from '@/components/asteronote/plugins/editor-mode';
import { ImagePluginControl } from '@/components/asteronote/plugins/image';
import {
    BoldPluginControl,
    HorizontalRulePluginControl,
    ItalicPluginControl,
    RedoPluginControl,
    RemoveFormatPluginControl,
    StrikethroughPluginControl,
    UnderlinePluginControl,
    UndoPluginControl,
} from '@/components/asteronote/plugins/inline-format';
import { LinkPluginControl } from '@/components/asteronote/plugins/link';
import { ListPluginControl } from '@/components/asteronote/plugins/list';
import { TablePluginControl } from '@/components/asteronote/plugins/table';
import { VideoPluginControl } from '@/components/asteronote/plugins/video';

export { BubbleToolbar } from '@/components/asteronote/plugins/bubble-toolbar';
export { ToolbarIconButton } from '@/components/asteronote/plugins/toolbar-icon-button';

/**
 * Null component for virtual toolbar entries
 * (separator / bubbleToolbar that render nothing in the main toolbar).
 */
function SeparatorPluginControl() {
    return null;
}

function BubbleToolbarPluginControl() {
    return null;
}

/**
 * Map of action names to the plugin component that renders toolbar UI for it.
 * Mirrors the old AsteroNote PluginRegistry pattern.
 */
export const asteronotePluginMap: Record<string, AsteroNotePluginComponent> = {
    undo: UndoPluginControl,
    redo: RedoPluginControl,
    bold: BoldPluginControl,
    italic: ItalicPluginControl,
    underline: UnderlinePluginControl,
    strikethrough: StrikethroughPluginControl,
    removeFormat: RemoveFormatPluginControl,
    list: ListPluginControl,
    link: LinkPluginControl,
    formatblock: FormatBlockPluginControl,
    heading: HeadingPluginControl,
    hr: HorizontalRulePluginControl,
    align: AlignPluginControl,
    image: ImagePluginControl,
    codeview: CodeViewPluginControl,
    fullscreen: FullscreenPluginControl,
    video: VideoPluginControl,
    table: TablePluginControl,
    separator: SeparatorPluginControl,
    bubbleToolbar: BubbleToolbarPluginControl,
};
