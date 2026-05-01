import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import ReactDOMServer from 'react-dom/server';
import { TooltipProvider } from '@/components/ui/tooltip';
import AdminLayout from '@/layouts/admin-layout';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import PublicLayout from '@/layouts/public-layout';
import SettingsLayout from '@/layouts/settings/layout';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createServer((page) =>
    createInertiaApp({
        page,
        render: ReactDOMServer.renderToString,
        title: (title) => (title ? `${title} - ${appName}` : appName),
        resolve: (name) => {
            const pages = import.meta.glob('./pages/**/*.tsx', { eager: false });
            return pages[`./pages/${name}.tsx`]();
        },
        layout: (name) => {
            switch (true) {
                case name === 'welcome':
                case name.startsWith('errors/'):
                    return null;
                case name.startsWith('public/'):
                    return PublicLayout;
                case name.startsWith('auth/'):
                case name.startsWith('tenant/'):
                case name === 'admin/auth/login':
                case name === 'admin/auth/two-factor-challenge':
                    return null;
                case name.startsWith('admin/'):
                    return AdminLayout;
                case name.startsWith('settings/'):
                    return [AppLayout, SettingsLayout];
                default:
                    return AppLayout;
            }
        },
        setup: ({ App, props }) => (
            <TooltipProvider delayDuration={0}>
                <App {...props} />
            </TooltipProvider>
        ),
    }),
);
