'use client';

import { ExternalLinkIcon } from 'lucide-react';

type CmsPermalinkDisplayProps = {
    href: string;
};

export function CmsPermalinkDisplay({ href }: CmsPermalinkDisplayProps) {
    return (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-start gap-2 text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
        >
            <span className="min-w-0 break-all leading-5">{href}</span>
            <ExternalLinkIcon className="mt-0.5 size-3.5 shrink-0" />
        </a>
    );
}