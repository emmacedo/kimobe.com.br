import { Head, Link, router } from '@inertiajs/react';
import { AlertTriangle, Building2, DollarSign, Home, Wand2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formataMoeda, saudacao } from '@/lib/utils';

function formatDate(d: string) { return new Date(d).toLocaleDateString('pt-BR'); }
function getCsrfToken() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''; }

type Props = {
    admin: { nome: string };
    assinantes_ativos: number; receita_mensal: number; variacao_receita: number;
    inadimplentes: number; valor_inadimplente: number;
    imoveis_total: number; imoveis_novos_mes: number;
    receita_6_meses: Array<{ mes: string; valor: number }>;
    assinantes_recentes: any[]; faturas_pendentes: any[];
};

export default function AdminDashboard(props: Props) {
    const [executando, setExecutando] = useState(false);

    async function executarInadimplencia() {
        setExecutando(true);
        try {
            const resp = await fetch('/admin/executar-inadimplencia', { method: 'POST', headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' } });
            const data = await resp.json();
            toast.success(`${data.faturas_atrasadas} fatura(s) marcada(s) como atrasada(s), ${data.tenants_bloqueados} tenant(s) bloqueado(s).`);
            router.reload();
        } catch { toast.error('Erro.'); } finally { setExecutando(false); }
    }

    const cards = [
        { titulo: 'Assinantes ativos', valor: String(props.assinantes_ativos), cor: '#1B6B3A', icon: Building2, sub: '' },
        { titulo: 'Receita mensal', valor: formataMoeda(props.receita_mensal), cor: '#0A4F5C', icon: DollarSign, sub: props.variacao_receita !== 0 ? `${props.variacao_receita > 0 ? '+' : ''}${props.variacao_receita}% vs anterior` : '' },
        { titulo: 'Inadimplentes', valor: String(props.inadimplentes), cor: '#A83232', icon: AlertTriangle, sub: `${formataMoeda(props.valor_inadimplente)} em aberto` },
        { titulo: 'Imóveis na plataforma', valor: String(props.imoveis_total), cor: '#6B7370', icon: Home, sub: `+${props.imoveis_novos_mes} este mês` },
    ];

    // Gráfico simples com barras CSS
    const maxReceita = Math.max(...props.receita_6_meses.map((r) => r.valor), 1);

    return (
        <>
            <Head title="Admin — Dashboard" />
            <div className="space-y-5">
                <h1 className="text-lg font-medium text-[#1E2D30]">{saudacao(props.admin.nome)}</h1>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {cards.map((c) => (
                        <div key={c.titulo} className="rounded-[10px] border border-[#D8DCDA] bg-white p-4">
                            <div className="mb-1.5 flex items-center gap-2"><c.icon className="h-3.5 w-3.5" style={{ color: c.cor }} /><p className="text-[10px] font-medium uppercase tracking-wide text-[#6B7370]">{c.titulo}</p></div>
                            <p className="text-xl font-medium" style={{ color: c.cor }}>{c.valor}</p>
                            {c.sub && <p className="mt-1 text-[10px] text-[#8A918E]">{c.sub}</p>}
                        </div>
                    ))}
                </div>

                {/* Gráfico de receita */}
                <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                    <p className="mb-4 text-sm font-medium text-[#1E2D30]">Receita — Últimos 6 meses</p>
                    {props.receita_6_meses.some((r) => r.valor > 0) ? (
                        <div className="flex items-end gap-3 h-32">
                            {props.receita_6_meses.map((r, i) => (
                                <div key={i} className="flex-1 flex flex-col items-center gap-1">
                                    <span className="text-[9px] font-mono text-[#8A918E]">{r.valor > 0 ? formataMoeda(r.valor) : ''}</span>
                                    <div className="w-full rounded-t-sm bg-[#C9A84C]" style={{ height: `${Math.max((r.valor / maxReceita) * 100, 2)}%` }} />
                                    <span className="text-[9px] text-[#8A918E]">{r.mes}</span>
                                </div>
                            ))}
                        </div>
                    ) : <p className="text-sm text-[#8A918E]">Dados insuficientes para o gráfico.</p>}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    {/* Assinantes recentes */}
                    <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                        <div className="mb-3 flex items-center justify-between">
                            <p className="text-sm font-medium text-[#1E2D30]">Assinantes recentes</p>
                            <Link href="/admin/assinantes" className="text-xs text-[#0A4F5C] hover:underline">Ver todos →</Link>
                        </div>
                        <div className="space-y-2">
                            {props.assinantes_recentes.map((a: any) => (
                                <div key={a.id} className="flex items-center justify-between rounded-md border border-[#EEF0EF] px-3 py-2">
                                    <div><p className="text-sm text-[#1E2D30]">{a.nome}</p><p className="text-[10px] text-[#8A918E]">{a.plano_assinatura?.nome ?? '—'}</p></div>
                                    <StatusBadge status={a.status} tipo="contrato" />
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Faturas pendentes */}
                    <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                        <div className="mb-3 flex items-center justify-between">
                            <p className="text-sm font-medium text-[#1E2D30]">Faturas pendentes</p>
                            <Link href="/admin/faturamento?status=atrasado" className="text-xs text-[#0A4F5C] hover:underline">Ver inadimplentes →</Link>
                        </div>
                        <div className="space-y-2">
                            {props.faturas_pendentes.map((f: any) => (
                                <div key={f.id} className="flex items-center justify-between rounded-md border border-[#EEF0EF] px-3 py-2">
                                    <div><p className="text-sm text-[#1E2D30]">{f.tenant?.nome}</p><p className="text-[10px] text-[#8A918E]">{f.referencia} — Venc: {formatDate(f.data_vencimento)}</p></div>
                                    <div className="flex items-center gap-2"><span className="font-mono text-sm">{formataMoeda(f.valor)}</span><StatusBadge status={f.status} tipo="cobranca" /></div>
                                </div>
                            ))}
                            {props.faturas_pendentes.length === 0 && <p className="text-sm text-[#8A918E]">Nenhuma fatura pendente.</p>}
                        </div>
                    </div>
                </div>

                {/* Ações rápidas */}
                <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                    <p className="mb-3 text-sm font-medium text-[#1E2D30]">Ações rápidas</p>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" className="border-[#D8DCDA]" asChild><Link href="/admin/faturamento"><Wand2 className="mr-1 h-3.5 w-3.5" />Gerar faturas</Link></Button>
                        <Button variant="outline" size="sm" className="border-[#D8DCDA]" asChild><Link href="/admin/faturamento?status=atrasado"><AlertTriangle className="mr-1 h-3.5 w-3.5" />Ver inadimplentes</Link></Button>
                        <Button variant="outline" size="sm" className="border-[#D8DCDA]" onClick={executarInadimplencia} disabled={executando}>
                            {executando ? '...' : '🔄'} Verificar inadimplência
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
