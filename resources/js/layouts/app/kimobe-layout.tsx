import { Toaster } from 'sonner';
import { FullFlowStatusBanner } from '@/components/fullflow-status-banner';
import { CreditosKicol } from '@/components/creditos-kicol';
import { KimobeContextBar } from '@/components/kimobe-context-bar';
import { KimobeNavbar } from '@/components/kimobe-navbar';

type Props = {
    children: React.ReactNode;
};

export default function KimobeLayout({ children }: Props) {
    return (
        <div className="flex min-h-screen flex-col bg-[#EEF0EF]">
            <KimobeNavbar />
            <KimobeContextBar />
            <FullFlowStatusBanner />
            <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-5 md:px-6">
                {children}
            </main>
            <CreditosKicol className="pt-10 pb-4" theme="light" />
            <Toaster
                position="top-right"
                richColors
                toastOptions={{
                    style: { fontFamily: 'inherit' },
                }}
            />
        </div>
    );
}
