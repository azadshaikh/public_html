import type { HTMLAttributes } from 'react';
import { normalizeFormErrorMessage } from '@/lib/forms';
import { cn } from '@/lib/utils';

export default function InputError({
    message,
    className = '',
    ...props
}: HTMLAttributes<HTMLParagraphElement> & { message?: string }) {
    const resolvedMessage = normalizeFormErrorMessage(message);

    return resolvedMessage ? (
        <p
            {...props}
            className={cn('text-sm text-red-600 dark:text-red-400', className)}
        >
            {resolvedMessage}
        </p>
    ) : null;
}
