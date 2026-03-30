import { Head, router } from '@inertiajs/react';
import { Lock, LogOut, RefreshCw, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';

type Props = {
    tenant: { nome: string; status: string; motivo_bloqueio: string | null } | null;
    has_multiple_tenants: boolean;
};

export default function TenantBloqueado({ tenant, has_multiple_tenants }: Props) {
    const status = tenant?.status ?? 'bloqueado';

    const configs: Record<string, { icon: typeof Lock; titulo: string; cor: string; mensagem: string; instrucao: string | null }> = {
        bloqueado: { icon: Lock, titulo: 'Acesso bloqueado', cor: '#A83232',
            mensagem: `O acesso à ${tenant?.nome ?? 'empresa'} foi bloqueado devido a pendência financeira.`,
            instrucao: 'Para regularizar, entre em contato pelo email financeiro@kimobe.com.br.' },
        suspenso: { icon: Lock, titulo: 'Acesso suspenso', cor: '#8C5A10',
            mensagem: `O acesso à ${tenant?.nome ?? 'empresa'} foi suspenso pelo administrador da plataforma.`,
            instrucao: 'Entre em contato pelo email suporte@kimobe.com.br para mais informações.' },
        cancelado: { icon: XCircle, titulo: 'Assinatura cancelada', cor: '#6B7370',
            mensagem: `A assinatura de ${tenant?.nome ?? 'empresa'} foi cancelada.`,
            instrucao: null },
    };

    const cfg = configs[status] ?? configs.bloqueado;

    return (
        <>
            <Head title={cfg.titulo} />
            <div className="flex min-h-svh items-center justify-center bg-[#EEF0EF] p-6">
                <div className="w-full max-w-md rounded-xl bg-white p-8 text-center shadow-sm">
                    <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full" style={{ backgroundColor: `${cfg.cor}15` }}>
                        <cfg.icon className="h-7 w-7" style={{ color: cfg.cor }} />
                    </div>

                    <span className="text-lg font-medium tracking-tight text-[#E4CC82]">kimobe</span>

                    <h1 className="mt-4 text-xl font-medium text-[#1E2D30]">{cfg.titulo}</h1>
                    <p className="mt-2 text-sm text-[#6B7370]">{cfg.mensagem}</p>

                    {tenant?.motivo_bloqueio && (
                        <div className="mt-3 rounded-md bg-[#FDECEC] p-3 text-xs text-[#A83232]">{tenant.motivo_bloqueio}</div>
                    )}

                    {cfg.instrucao && <p className="mt-4 text-xs text-[#8A918E]">{cfg.instrucao}</p>}

                    <div className="mt-6 space-y-2">
                        {has_multiple_tenants && (
                            <Button variant="outline" className="w-full border-[#D8DCDA]" onClick={() => router.post('/trocar-contexto')}>
                                <RefreshCw className="mr-2 h-4 w-4" />Acessar outro ambiente
                            </Button>
                        )}
                        <Button variant="ghost" className="w-full text-[#6B7370]" onClick={() => router.post('/logout')}>
                            <LogOut className="mr-2 h-4 w-4" />Sair
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
