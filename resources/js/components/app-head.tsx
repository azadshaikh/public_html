import { Head, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import type { SharedData } from '@/types';

type AppHeadProps = PropsWithChildren<{
    title?: string;
    description?: string;
}>;

export default function AppHead({
    title,
    description,
    children,
}: AppHeadProps) {
    const { appName } = usePage<SharedData>().props;
    const pageTitle = title ? `${title} | ${appName}` : appName;

    return (
        <Head title={pageTitle}>
            <meta
                head-key="description"
                name="description"
                content={description ?? `${appName} application`}
            />
            {children}
        </Head>
    );
}
