import type { route as ziggyRoute } from 'ziggy-js';
import type { SharedData } from '@/types/auth';

declare global {
    var route: typeof ziggyRoute;
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: SharedData & {
            [key: string]: unknown;
        };
    }
}
