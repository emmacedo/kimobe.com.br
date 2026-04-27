import { Head } from '@inertiajs/react';
import { CreditosKicol } from '@/components/creditos-kicol';

type Props = {
    titulo?: string;
    subtitulo?: string;
    variant?: 'assinante' | 'admin';
    showRegistro?: boolean;
    showCreditos?: boolean;
    children: React.ReactNode;
};

export default function AuthLayout({
    titulo = '',
    subtitulo = '',
    variant = 'assinante',
    showRegistro = true,
    showCreditos = true,
    children,
}: Props) {
    const bgColor = variant === 'admin' ? 'bg-[#073B45]' : 'bg-[#0A4F5C]';

    return (
        <div className="relative flex min-h-svh flex-col">
            <Head>
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            {/* Fundo dividido */}
            <div className={`absolute inset-x-0 top-0 h-[45%] ${bgColor}`} />
            <div className="absolute inset-x-0 bottom-0 h-[55%] bg-[#F7F8F7]" />

            {/* Conteúdo centralizado */}
            <div className="relative z-10 flex flex-1 flex-col items-center justify-center px-4 py-8">
                {/* Logo */}
                <div className="mb-6 flex h-16 w-16 items-center justify-center overflow-hidden rounded-full shadow-md">
                    <img src="/kimobe-abrev.png" alt="Kimobe" className="h-full w-full object-cover" />
                </div>

                {/* Card */}
                <div className="w-full max-w-[420px] rounded-2xl bg-white p-8 shadow-[0_4px_24px_rgba(0,0,0,0.08)] sm:p-10">
                    {/* Título */}
                    {titulo && (
                        <div className="mb-6 text-center">
                            {variant === 'admin' && (
                                <span className="mb-3 inline-block rounded-full bg-[#C9A84C]/15 px-3 py-0.5 text-[10px] font-medium text-[#C9A84C]">
                                    Administração
                                </span>
                            )}
                            <h1 className="text-2xl font-medium text-[#1E2D30]">{titulo}</h1>
                            {subtitulo && (
                                <p className="mt-2 text-sm text-[#6B7370]">{subtitulo}</p>
                            )}
                        </div>
                    )}

                    {/* Formulário */}
                    {children}
                </div>

                {/* Rodapé */}
                <div className="mt-8 text-center">
                    <p className="text-xs text-[#8A918E]">
                        © {new Date().getFullYear()} Kimobe. Todos os direitos reservados.
                    </p>
                    {showCreditos && <CreditosKicol className="mt-2" theme="light" />}
                </div>
            </div>
        </div>
    );
}
