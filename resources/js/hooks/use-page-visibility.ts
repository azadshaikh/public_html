import { useEffect, useState } from 'react';
import { isPageVisible } from '@/lib/page-visibility';

export function usePageVisibility(): boolean {
    const [visible, setVisible] = useState<boolean>(() => {
        if (typeof document === 'undefined') {
            return true;
        }

        return isPageVisible(document.visibilityState);
    });

    useEffect(() => {
        const handleVisibilityChange = () => {
            setVisible(isPageVisible(document.visibilityState));
        };

        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => {
            document.removeEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );
        };
    }, []);

    return visible;
}
