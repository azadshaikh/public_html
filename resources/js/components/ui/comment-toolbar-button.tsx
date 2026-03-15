'use client';

import * as React from 'react';

import { MessageSquareIcon } from 'lucide-react';
import { useEditorPlugin } from 'platejs/react';

import { commentPlugin } from '@/components/editor/plugins/comment-kit';

import { ToolbarButton } from './toolbar';

export function CommentToolbarButton(
  props: React.ComponentProps<typeof ToolbarButton>
) {
  const { tf } = useEditorPlugin(commentPlugin);

  return (
    <ToolbarButton
      {...props}
      onClick={() => {
        tf.comment.setDraft();
      }}
      onMouseDown={(e) => {
        e.preventDefault();
      }}
      tooltip="Comment (⌘+⇧+M)"
    >
      <MessageSquareIcon />
    </ToolbarButton>
  );
}
