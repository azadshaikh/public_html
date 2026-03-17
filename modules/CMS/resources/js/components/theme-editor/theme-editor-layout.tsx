import type { ReactNode } from 'react';
import AppHead from '@/components/app-head';

type ThemeEditorLayoutProps = {
    children: ReactNode;
    title?: string;
    description?: string;
};

export default function ThemeEditorLayout({
    children,
    title,
    description,
}: ThemeEditorLayoutProps) {
    return (
        <div className="dark flex h-screen w-full flex-col overflow-hidden bg-[#1e1e1e] text-[#cccccc]">
            <AppHead title={title} description={description} />
            {children}
        </div>
    );
}
