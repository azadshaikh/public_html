'use client';

import * as React from 'react';

import type { DropdownMenuProps } from '@radix-ui/react-dropdown-menu';

import { DocxExportPlugin } from '@platejs/docx-io';
import { MarkdownPlugin } from '@platejs/markdown';
import {
  ArrowUpToLineIcon,
  FileTextIcon,
  FileTypeIcon,
  LetterTextIcon,
} from 'lucide-react';
import { useEditorRef } from 'platejs/react';
import { serializeHtml } from 'platejs/static';

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

import { ToolbarButton } from './toolbar';

export function ExportToolbarButton({
  children,
  ...props
}: DropdownMenuProps & { children?: React.ReactNode }) {
  const editor = useEditorRef();
  const [open, setOpen] = React.useState(false);

  const downloadFile = (content: string, filename: string, type: string) => {
    const blob = new Blob([content], { type });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  };

  return (
    <DropdownMenu open={open} onOpenChange={setOpen} modal={false} {...props}>
      <DropdownMenuTrigger asChild>
        <ToolbarButton pressed={open} tooltip="Export">
          {children ?? <ArrowUpToLineIcon />}
        </ToolbarButton>
      </DropdownMenuTrigger>

      <DropdownMenuContent
        className="ignore-click-outside/toolbar flex max-h-[500px] min-w-[180px] flex-col overflow-y-auto"
        align="start"
      >
        <DropdownMenuGroup>
          <DropdownMenuItem
            onSelect={async () => {
              try {
                await editor.tf.docxExport.exportAndDownload('document');
              } catch {
                // DOCX export not available
              }
            }}
          >
            <FileTypeIcon />
            Export as DOCX
          </DropdownMenuItem>

          <DropdownMenuItem
            onSelect={async () => {
              const html = await serializeHtml(editor);
              downloadFile(html, 'document.html', 'text/html');
            }}
          >
            <FileTextIcon />
            Export as HTML
          </DropdownMenuItem>

          <DropdownMenuItem
            onSelect={() => {
              try {
                const md = editor
                  .getApi(MarkdownPlugin)
                  .markdown.serialize();
                downloadFile(md, 'document.md', 'text/markdown');
              } catch {
                // Markdown export not available
              }
            }}
          >
            <LetterTextIcon />
            Export as Markdown
          </DropdownMenuItem>
        </DropdownMenuGroup>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
