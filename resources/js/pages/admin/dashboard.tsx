import { Head, Link } from '@inertiajs/react';
import { Building2, CreditCard, Gift, Home, Package, Receipt } from 'lucide-react';
import { StatusBadge } from '@/components/status-badge';
import { saudacao } from '@/lib/utils';

type AssinanteRecente = {
    id: number;
    nome: string;
    status: string;
    is_exempt_from_subscription?: boolean;
    fullflow_subscription?: { plan_code?: string | null } | null;
};

type Props = {
    admin: { nome: string };
    assinantes_ativos: number;
    assinaturas_ativas: number;
    cortesias: number;
    imoveis_total: number;
    imoveis_novos_mes: number;
    total_planos: number;
    assinantes_recentes: AssinanteRecente[];
    plan_names_by_code: Record<string, string>;
};

export default function AdminDashboard(props: Props) {
    const cards = [
        { titulo: 'Assinantes ativos', valor: String(props.assinantes_ativos), cor: '#1B6B3A', icon: Building2, sub: '' },
        { titulo: 'Assinaturas FullFlow', valor: String(props.assinaturas_ativas), cor: '#0A4F5C', icon: Receipt, sub: '' },
        { titulo: 'Cortesias', valor: String(props.cortesias), cor: '#C9A84C', icon: Gift, sub: '' },
        { titulo: 'Imóveis na plataforma', valor: String(props.imoveis_total), cor: '#6B7370', icon: Home, sub: `+${props.imoveis_novos_mes} este mês` },
        { titulo: 'Planos no catálogo', valor: String(props.total_planos), cor: '#8A918E', icon: Package, sub: '' },
    ];

    return (
        <>
            <Head title="Admin — Dashboard" />
            <div className="space-y-5">
                <h1 className="text-lg font-medium text-[#1E2D30]">{saudacao(props.admin.nome)}</h1>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                    {cards.map((c) => (
                        <div key={c.titulo} className="rounded-[10px] border border-[#D8DCDA] bg-white p-4">
                            <div className="mb-1.5 flex items-center gap-2">
                                <c.icon className="h-3.5 w-3.5" style={{ color: c.cor }} />
                                <p className="text-[10px] font-medium uppercase tracking-wide text-[#6B7370]">{c.titulo}</p>
                            </div>
                            <p className="text-xl font-medium" style={{ color: c.cor }}>{c.valor}</p>
                            {c.sub && <p className="mt-1 text-[10px] text-[#8A918E]">{c.sub}</p>}
                        </div>
                    ))}
                </div>

                <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                    <div className="mb-3 flex items-center justify-between">
                        <p className="text-sm font-medium text-[#1E2D30]">Assinantes recentes</p>
                        <Link href="/admin/assinantes" className="text-xs text-[#0A4F5C] hover:underline">Ver todos →</Link>
                    </div>
                    <div className="space-y-2">
                        {props.assinantes_recentes.map((a) => {
                            const planName = a.fullflow_subscription?.plan_code
                                ? (props.plan_names_by_code[a.fullflow_subscription.plan_code] ?? a.fullflow_subscription.plan_code)
                                : a.is_exempt_from_subscription
                                  ? 'Cortesia'
                                  : '—';
                            return (
                                <div key={a.id} className="flex items-center justify-between rounded-md border border-[#EEF0EF] px-3 py-2">
                                    <div>
                                        <p className="text-sm text-[#1E2D30]">{a.nome}</p>
                                        <p className="text-[10px] text-[#8A918E]">{planName}</p>
                                    </div>
                                    <StatusBadge status={a.status} tipo="contrato" />
                                </div>
                            );
                        })}
                        {props.assinantes_recentes.length === 0 && <p className="text-sm text-[#8A918E]">Nenhum assinante ainda.</p>}
                    </div>
                </div>

                <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                    <p className="mb-3 text-sm font-medium text-[#1E2D30]">Cobranças e faturamento</p>
                    <p className="text-sm text-[#6B7370]">
                        A gestão de cobranças (boletos, Pix, conciliação) é feita no painel central do FullFlow.{' '}
                        <a href="https://fullflow.app.br" target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-1 text-[#0A4F5C] hover:underline">
                            fullflow.app.br <CreditCard className="h-3 w-3" />
                        </a>{' '}
                        para extratos, conciliação e cancelamentos manuais.
                    </p>
                </div>
            </div>
        </>
    );
}
