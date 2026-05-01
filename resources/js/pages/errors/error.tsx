import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Home } from 'lucide-react';

type ErrorStatus = 403 | 404 | 419 | 500 | 503;

type ErrorContent = {
    title: string;
    heading: string;
    description: string;
};

const ERROR_MAP: Record<ErrorStatus, ErrorContent> = {
    403: {
        title: '403 — Acesso negado',
        heading: 'Acesso negado',
        description: 'Você não tem permissão para acessar esta página.',
    },
    404: {
        title: '404 — Página não encontrada',
        heading: 'Página não encontrada',
        description:
            'A página que você procurou não existe, foi movida ou está temporariamente indisponível.',
    },
    419: {
        title: '419 — Sessão expirada',
        heading: 'Sua sessão expirou',
        description:
            'Por segurança, sua sessão foi encerrada. Faça login novamente para continuar.',
    },
    500: {
        title: '500 — Erro inesperado',
        heading: 'Algo deu errado',
        description:
            'Tivemos um problema inesperado e nossa equipe já foi notificada. Tente novamente em alguns instantes.',
    },
    503: {
        title: '503 — Em manutenção',
        heading: 'Estamos em manutenção',
        description:
            'O sistema está passando por uma manutenção programada. Voltamos em breve.',
    },
};

const FALLBACK: ErrorContent = {
    title: 'Erro',
    heading: 'Ocorreu um erro',
    description: 'Não conseguimos completar sua solicitação.',
};

export default function ErrorPage({ status }: { status: number }) {
    const content = ERROR_MAP[status as ErrorStatus] ?? FALLBACK;

    return (
        <>
            <Head title={content.title} />

            <div className="flex min-h-screen flex-col bg-[#FDFDFC]">
                <header className="mx-auto flex w-full max-w-7xl items-center px-4 py-6 md:px-6">
                    <Link href="/" className="inline-flex items-center">
                        <img
                            src="/logo-kimobe.webp"
                            alt="Kimobe"
                            className="h-8"
                        />
                    </Link>
                </header>

                <main className="flex flex-1 items-center justify-center px-4 py-12 md:px-6">
                    <div className="mx-auto flex w-full max-w-xl flex-col items-center text-center">
                        <span className="mb-4 inline-block rounded-full bg-[#0A4F5C]/10 px-4 py-1 text-xs font-semibold uppercase tracking-wider text-[#0A4F5C]">
                            Erro {status}
                        </span>

                        <h1 className="mb-3 text-3xl font-semibold text-[#1E2D30] md:text-4xl">
                            {content.heading}
                        </h1>

                        <p className="mb-8 text-base text-[#3A4240] md:text-lg">
                            {content.description}
                        </p>

                        <div className="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                            <button
                                type="button"
                                onClick={() => window.history.back()}
                                className="inline-flex items-center justify-center gap-2 rounded-lg border border-[#0A4F5C] px-5 py-2.5 text-sm font-medium text-[#0A4F5C] transition-colors hover:bg-[#0A4F5C] hover:text-white"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Voltar
                            </button>
                            <Link
                                href="/"
                                className="inline-flex items-center justify-center gap-2 rounded-lg bg-[#C9A84C] px-5 py-2.5 text-sm font-medium text-[#2E2410] transition-colors hover:bg-[#B8993F]"
                            >
                                <Home className="h-4 w-4" />
                                Ir para o início
                            </Link>
                        </div>
                    </div>
                </main>

                <footer className="bg-[#073B45] py-6 text-center text-xs text-[#8DCAD6]">
                    © {new Date().getFullYear()} Kimobe. Todos os direitos
                    reservados.
                </footer>
            </div>
        </>
    );
}
