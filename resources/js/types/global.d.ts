import type { Auth, CurrentTenant } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            current_tenant: CurrentTenant | null;
            current_papeis: string[];
            has_multiple_tenants: boolean;
            [key: string]: unknown;
        };
    }
}
