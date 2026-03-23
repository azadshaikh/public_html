// TODO: like input we need variants and sizes. also if pressed / search should get active
import * as React from 'react';
import { SearchIcon, XIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupButton,
    InputGroupInput,
} from '@/components/ui/input-group';

type SearchInputProps = Omit<React.ComponentProps<typeof InputGroupInput>, 'value' | 'onChange'> & {
    value: string;
    onChange: (value: string) => void;
    onClear?: () => void;
    containerClassName?: string;
    size?: React.ComponentProps<typeof InputGroup>['size'];
};

export const SearchInput = React.forwardRef<HTMLInputElement, SearchInputProps>(
    ({
        value,
        onChange,
        onClear,
        className,
        containerClassName,
        size = 'sm',
        onKeyDown,
        placeholder = 'Search...',
        ...props
    }, ref) => {
        const handleClear = React.useCallback(() => {
            onChange('');
            onClear?.();
        }, [onChange, onClear]);

        return (
            <InputGroup size={size} className={cn('w-full', containerClassName)}>
                <InputGroupAddon>
                    <SearchIcon />
                </InputGroupAddon>

                <InputGroupInput
                    ref={ref}
                    value={value}
                    placeholder={placeholder}
                    className={cn(
                        size === 'sm' ? 'text-xs' : 'text-sm',
                        className,
                    )}
                    onChange={(event) => onChange(event.target.value)}
                    onKeyDown={(event) => {
                        if (event.key === 'Escape' && value !== '') {
                            event.preventDefault();
                            event.stopPropagation();
                            handleClear();

                            return;
                        }

                        onKeyDown?.(event);
                    }}
                    {...props}
                />

                {value !== '' ? (
                    <InputGroupAddon align="inline-end">
                        <InputGroupButton
                            aria-label="Clear search"
                            size={size === 'sm' ? 'icon-xs' : 'icon-sm'}
                            onClick={handleClear}
                        >
                            <XIcon />
                        </InputGroupButton>
                    </InputGroupAddon>
                ) : null}
            </InputGroup>
        );
    },
);

SearchInput.displayName = 'SearchInput';
