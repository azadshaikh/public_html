import { cn } from '@/lib/utils';

type NavigationIconProps = {
    svg?: string | null;
    className?: string;
};

export function NavigationIcon({ svg, className }: NavigationIconProps) {
    if (!svg || svg.trim() === '') {
        return null;
    }

    return (
        <span
            aria-hidden="true"
            className={cn('shrink-0', className)}
            dangerouslySetInnerHTML={{ __html: svg }}
        />
    );
}
