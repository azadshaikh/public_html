import { ClapperboardIcon, ImageIcon } from 'lucide-react';
import { AspectRatio } from '@/components/ui/aspect-ratio';
import { cn } from '@/lib/utils';

type MovieArtworkProps = {
    src?: string | null;
    title: string;
    variant?: 'poster' | 'backdrop';
    className?: string;
};

export default function MovieArtwork({
    src,
    title,
    variant = 'poster',
    className,
}: MovieArtworkProps) {
    const ratio = variant === 'poster' ? 2 / 3 : 16 / 9;
    const icon = variant === 'poster' ? <ClapperboardIcon /> : <ImageIcon />;
    const subtitle = variant === 'poster' ? 'Poster preview' : 'Backdrop preview';

    return (
        <div className={cn('overflow-hidden rounded-2xl border bg-muted/30', className)}>
            <AspectRatio ratio={ratio}>
                {src ? (
                    <img
                        src={src}
                        alt={`${title} ${subtitle}`}
                        className="size-full object-cover"
                    />
                ) : (
                    <div className="flex size-full flex-col items-center justify-center gap-3 bg-gradient-to-br from-muted to-muted/30 px-4 text-center text-muted-foreground">
                        <div className="flex size-12 items-center justify-center rounded-full border bg-background/70 [&_svg]:size-5">
                            {icon}
                        </div>
                        <div className="flex flex-col gap-1">
                            <div className="text-sm font-medium text-foreground">
                                {title}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {subtitle}
                            </div>
                        </div>
                    </div>
                )}
            </AspectRatio>
        </div>
    );
}
