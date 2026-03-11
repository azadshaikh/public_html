import { LaptopMinimalIcon, MoonStarIcon, SunMediumIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/hooks/use-appearance';
import type { Appearance } from '@/hooks/use-appearance';

const themes: Array<{
    value: Appearance;
    label: string;
    icon: typeof SunMediumIcon;
}> = [
    { value: 'light', label: 'Light', icon: SunMediumIcon },
    { value: 'dark', label: 'Dark', icon: MoonStarIcon },
    { value: 'system', label: 'System', icon: LaptopMinimalIcon },
];

export function AppThemeToggle() {
    const { appearance, resolvedAppearance, updateAppearance } =
        useAppearance();
    const ActiveIcon =
        resolvedAppearance === 'dark' ? MoonStarIcon : SunMediumIcon;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    className="rounded-full"
                    aria-label="Change theme"
                >
                    <ActiveIcon />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-40 min-w-40">
                <DropdownMenuLabel>Appearance</DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    value={appearance}
                    onValueChange={(value) =>
                        updateAppearance(value as Appearance)
                    }
                >
                    {themes.map(({ value, label, icon: Icon }) => (
                        <DropdownMenuRadioItem key={value} value={value}>
                            <Icon />
                            {label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
