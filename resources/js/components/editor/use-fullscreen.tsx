'use client';

import {
    createContext,
    useCallback,
    useContext,
    useEffect,
    useState,
} from 'react';

interface FullscreenContextValue {
    isFullscreen: boolean;
    toggleFullscreen: () => void;
}

const FullscreenContext = createContext<FullscreenContextValue>({
    isFullscreen: false,
    toggleFullscreen: () => {},
});

export function FullscreenProvider({
    children,
}: {
    children: React.ReactNode;
}) {
    const [isFullscreen, setIsFullscreen] = useState(false);

    const toggleFullscreen = useCallback(() => {
        setIsFullscreen((prev) => !prev);
    }, []);

    useEffect(() => {
        if (!isFullscreen) return;

        const handleEsc = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                setIsFullscreen(false);
            }
        };

        document.addEventListener('keydown', handleEsc);

        return () => document.removeEventListener('keydown', handleEsc);
    }, [isFullscreen]);

    return (
        <FullscreenContext value={{ isFullscreen, toggleFullscreen }}>
            {children}
        </FullscreenContext>
    );
}

export function useFullscreen() {
    return useContext(FullscreenContext);
}
