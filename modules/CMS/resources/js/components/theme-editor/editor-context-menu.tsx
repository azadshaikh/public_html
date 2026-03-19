import {
    useCallback,
    useEffect,
    useRef,
    useState
    
} from 'react';
import type {ReactNode} from 'react';
import { createPortal } from 'react-dom';
import { cn } from '@/lib/utils';

type Position = { x: number; y: number };

type EditorContextMenuItemDef = {
    type?: 'item';
    label: string;
    icon?: ReactNode;
    disabled?: boolean;
    variant?: 'default' | 'destructive';
    onSelect: () => void;
};

type EditorContextMenuSeparatorDef = {
    type: 'separator';
};

export type EditorContextMenuEntry =
    | EditorContextMenuItemDef
    | EditorContextMenuSeparatorDef;

type EditorContextMenuProps = {
    children: ReactNode;
    items: EditorContextMenuEntry[];
};

function MenuContent({
    items,
    position,
    onClose,
}: {
    items: EditorContextMenuEntry[];
    position: Position;
    onClose: () => void;
}) {
    const menuRef = useRef<HTMLDivElement>(null);
    const [adjusted, setAdjusted] = useState<Position>(position);

    useEffect(() => {
        const menu = menuRef.current;
        if (!menu) {
            return;
        }

        const rect = menu.getBoundingClientRect();
        let { x, y } = position;

        if (x + rect.width > window.innerWidth) {
            x = window.innerWidth - rect.width - 4;
        }
        if (y + rect.height > window.innerHeight) {
            y = window.innerHeight - rect.height - 4;
        }

        setAdjusted({ x: Math.max(0, x), y: Math.max(0, y) });
    }, [position]);

    useEffect(() => {
        const handleKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        const handleClickOutside = (event: MouseEvent) => {
            if (
                menuRef.current &&
                !menuRef.current.contains(event.target as Node)
            ) {
                onClose();
            }
        };

        const handleScroll = () => onClose();

        document.addEventListener('keydown', handleKey, true);
        document.addEventListener('mousedown', handleClickOutside, true);
        document.addEventListener('scroll', handleScroll, true);
        window.addEventListener('resize', onClose);

        return () => {
            document.removeEventListener('keydown', handleKey, true);
            document.removeEventListener('mousedown', handleClickOutside, true);
            document.removeEventListener('scroll', handleScroll, true);
            window.removeEventListener('resize', onClose);
        };
    }, [onClose]);

    return createPortal(
        <div
            ref={menuRef}
            className="fixed z-[9999] min-w-44 rounded-md border border-[#3c3c3c] bg-[#252526] py-1 shadow-xl"
            style={{ left: adjusted.x, top: adjusted.y }}
        >
            {items.map((entry, index) => {
                if (entry.type === 'separator') {
                    return (
                        <div key={index} className="my-1 h-px bg-[#3c3c3c]" />
                    );
                }

                const item = entry as EditorContextMenuItemDef;
                const isDestructive = item.variant === 'destructive';

                return (
                    <button
                        key={index}
                        type="button"
                        disabled={item.disabled}
                        className={cn(
                            'flex w-full items-center gap-2 px-3 py-1 text-left text-[13px] outline-none',
                            item.disabled
                                ? 'cursor-default text-[#6a6a6a]'
                                : isDestructive
                                  ? 'text-[#f48771] hover:bg-[#04395e] hover:text-[#f48771]'
                                  : 'text-[#cccccc] hover:bg-[#04395e] hover:text-white',
                        )}
                        onClick={() => {
                            if (!item.disabled) {
                                item.onSelect();
                                onClose();
                            }
                        }}
                    >
                        {item.icon ? (
                            <span className="flex size-4 shrink-0 items-center justify-center [&>svg]:size-4">
                                {item.icon}
                            </span>
                        ) : null}
                        <span>{item.label}</span>
                    </button>
                );
            })}
        </div>,
        document.body,
    );
}

export function EditorContextMenu({ children, items }: EditorContextMenuProps) {
    const [menu, setMenu] = useState<Position | null>(null);

    const handleContextMenu = useCallback((event: React.MouseEvent) => {
        event.preventDefault();
        event.stopPropagation();
        setMenu({ x: event.clientX, y: event.clientY });
    }, []);

    const close = useCallback(() => setMenu(null), []);

    return (
        <>
            <div onContextMenu={handleContextMenu}>{children}</div>
            {menu ? (
                <MenuContent items={items} position={menu} onClose={close} />
            ) : null}
        </>
    );
}
