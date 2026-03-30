import { Toaster } from 'sonner';
import { BannerCobrancaKimobe } from '@/components/banner-cobranca-kimobe';
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
            <BannerCobrancaKimobe />
            <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-5 md:px-6">
                {children}
            </main>
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
