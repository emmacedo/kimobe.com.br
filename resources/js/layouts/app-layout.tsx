import { Head } from '@inertiajs/react';
import KimobeLayout from '@/layouts/app/kimobe-layout';

export default function AppLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return (
        <KimobeLayout>
            <Head>
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            {children}
        </KimobeLayout>
    );
}
