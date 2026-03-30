import KimobeLayout from '@/layouts/app/kimobe-layout';

export default function AppLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return <KimobeLayout>{children}</KimobeLayout>;
}
