import type { ReactNode } from 'react';
import AppHead from '@/components/app-head';

type ThemeCustomizerLayoutProps = {
    children: ReactNode;
    title?: string;
    description?: string;
};

export default function ThemeCustomizerLayout({
    children,
    title,
    description,
}: ThemeCustomizerLayoutProps) {
    return (
        <div className="flex h-screen w-full flex-col overflow-hidden bg-[linear-gradient(180deg,#f8fafc_0%,#eef2ff_100%)] text-foreground">
            <AppHead title={title} description={description} />
            {children}
        </div>
    );
}