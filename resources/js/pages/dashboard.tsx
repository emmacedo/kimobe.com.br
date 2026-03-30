import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertCircle, Building2, CheckCircle, Clock, TrendingUp } from 'lucide-react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { StatusBadge } from '@/components/status-badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formataMoeda, saudacao } from '@/lib/utils';

function formatDate(d: string): string {
    return new Date(d).toLocaleDateString('pt-BR');
}

export default function Dashboard(props: any) {
    const { auth, flash } = usePage().props as any;
    const tipo = props.dashboard_tipo ?? 'admin';

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    return (
        <>
            <Head title="Dashboard" />
            <div className="space-y-5">
                <h1 className="text-lg font-medium text-[#1E2D30]">
                    {saudacao(auth.user?.name ?? 'usuário')}
                </h1>

                {tipo === 'admin' && <DashboardAdmin {...props} />}
                {tipo === 'proprietario' && <DashboardProprietario {...props} />}
                {tipo === 'inquilino' && <DashboardInquilino {...props} />}
            </div>
        </>
    );
}

function DashboardAdmin(props: any) {
    const cards = [
        { titulo: 'Receita mensal', valor: formataMoeda(props.receita_mensal ?? 0), cor: '#0A4F5C', icon: TrendingUp, sub: '' },
        { titulo: 'Taxa de ocupação', valor: `${props.taxa_ocupacao ?? 0}%`, cor: '#0A4F5C', icon: Building2, sub: `${props.imoveis_alugados ?? 0} de ${props.total_imoveis ?? 0} imóveis` },
        { titulo: 'Inadimplência', valor: `${props.inadimplencia ?? 0}%`, cor: (props.inadimplencia ?? 0) > 0 ? '#A83232' : '#1B6B3A', icon: AlertCircle, sub: `${props.cobrancas_atrasadas ?? 0} atrasada(s)` },
        { titulo: 'Contratos ativos', valor: String(props.contratos_ativos ?? 0), cor: '#1E2D30', icon: CheckCircle, sub: '' },
    ];

    return (
        <>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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

            {props.rendimento_proprietario_mes !== undefined && (
                <div className="rounded-[10px] border border-[#C9A84C]/30 bg-[#FBF6E8] p-4">
                    <p className="text-[10px] font-medium uppercase tracking-wide text-[#6B5420]">Meus rendimentos como proprietário</p>
                    <p className="mt-1 font-mono text-lg font-medium text-[#6B5420]">{formataMoeda(props.rendimento_proprietario_mes)}</p>
                </div>
            )}

            <div className="overflow-hidden rounded-[10px] border border-[#D8DCDA] bg-white">
                <div className="border-b border-[#EEF0EF] px-4 py-3">
                    <p className="text-sm font-medium text-[#1E2D30]">Últimas movimentações</p>
                </div>
                <Table>
                    <TableHeader>
                        <TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                            <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Imóvel</TableHead>
                            <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Inquilino</TableHead>
                            <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Valor</TableHead>
                            <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Status</TableHead>
                            <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Vencimento</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {(props.ultimas_movimentacoes ?? []).map((cob: any) => (
                            <TableRow key={cob.id} className="cursor-pointer border-b border-[#F7F8F7] hover:bg-[#FAFBFA]"
                                onClick={() => router.visit(`/financeiro/cobrancas/${cob.id}`)}>
                                <TableCell className="text-sm">{cob.contrato?.imovel?.complemento || `${cob.contrato?.imovel?.logradouro}, ${cob.contrato?.imovel?.numero}`}</TableCell>
                                <TableCell className="text-sm text-[#3A4240]">{cob.contrato?.inquilino?.user?.name}</TableCell>
                                <TableCell className="text-right font-mono text-sm font-medium">{formataMoeda(cob.valor_total)}</TableCell>
                                <TableCell><StatusBadge status={cob.status} tipo="cobranca" /></TableCell>
                                <TableCell className="text-xs text-[#6B7370]">{formatDate(cob.data_vencimento)}</TableCell>
                            </TableRow>
                        ))}
                        {(props.ultimas_movimentacoes ?? []).length === 0 && (
                            <TableRow><TableCell colSpan={5} className="py-8 text-center text-sm text-[#8A918E]">Nenhuma movimentação</TableCell></TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
        </>
    );
}

function DashboardProprietario(props: any) {
    return (
        <>
            <div className="grid gap-3 sm:grid-cols-3">
                <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-4">
                    <div className="mb-1.5 flex items-center gap-2"><TrendingUp className="h-3.5 w-3.5 text-[#1B6B3A]" /><p className="text-[10px] font-medium uppercase tracking-wide text-[#6B7370]">Receita do mês</p></div>
                    <p className="font-mono text-xl font-medium text-[#1B6B3A]">{formataMoeda(props.receita_mes ?? 0)}</p>
                </div>
                <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-4">
                    <div className="mb-1.5 flex items-center gap-2"><Clock className="h-3.5 w-3.5 text-[#8C5A10]" /><p className="text-[10px] font-medium uppercase tracking-wide text-[#6B7370]">Repasses pendentes</p></div>
                    <p className="font-mono text-xl font-medium text-[#8C5A10]">{formataMoeda(props.pendentes_valor ?? 0)}</p>
                    <p className="mt-1 text-[10px] text-[#8A918E]">{props.pendentes_count ?? 0} repasse(s)</p>
                </div>
                <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-4">
                    <div className="mb-1.5 flex items-center gap-2"><Building2 className="h-3.5 w-3.5 text-[#0A4F5C]" /><p className="text-[10px] font-medium uppercase tracking-wide text-[#6B7370]">Meus imóveis</p></div>
                    <p className="text-xl font-medium text-[#0A4F5C]">{props.meus_imoveis ?? 0}</p>
                </div>
            </div>

            <div className="overflow-hidden rounded-[10px] border border-[#D8DCDA] bg-white">
                <div className="border-b border-[#EEF0EF] px-4 py-3"><p className="text-sm font-medium text-[#1E2D30]">Últimos repasses</p></div>
                <Table>
                    <TableHeader>
                        <TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                            <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Ref.</TableHead>
                            <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Imóvel</TableHead>
                            <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Líquido</TableHead>
                            <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Status</TableHead>
                            <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Previsto</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {(props.ultimos_repasses ?? []).map((r: any) => (
                            <TableRow key={r.id} className="cursor-pointer border-b border-[#F7F8F7] hover:bg-[#FAFBFA]"
                                onClick={() => router.visit(`/financeiro/repasses/${r.id}`)}>
                                <TableCell className="font-mono text-sm">{r.cobranca?.referencia}</TableCell>
                                <TableCell className="text-sm">{r.cobranca?.contrato?.imovel?.complemento || '—'}</TableCell>
                                <TableCell className="text-right font-mono text-sm font-medium text-[#0A4F5C]">{formataMoeda(r.valor_liquido)}</TableCell>
                                <TableCell><StatusBadge status={r.status} tipo="repasse" /></TableCell>
                                <TableCell className="text-xs text-[#6B7370]">{formatDate(r.data_prevista)}</TableCell>
                            </TableRow>
                        ))}
                        {(props.ultimos_repasses ?? []).length === 0 && (
                            <TableRow><TableCell colSpan={5} className="py-8 text-center text-sm text-[#8A918E]">Nenhum repasse</TableCell></TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
        </>
    );
}

function DashboardInquilino(props: any) {
    const proxima = props.proxima_cobranca;
    const diasRestantes = proxima ? Math.ceil((new Date(proxima.data_vencimento).getTime() - Date.now()) / (86400000)) : null;

    return (
        <>
            {proxima ? (
                <div className={`rounded-[10px] border p-5 ${diasRestantes !== null && diasRestantes < 0 ? 'border-[#A83232]/30 bg-[#FDECEC]' : 'border-[#D8DCDA] bg-white'}`}>
                    <p className="text-[10px] font-medium uppercase tracking-wide text-[#6B7370]">Próximo vencimento</p>
                    <div className="mt-2 flex items-end justify-between">
                        <div>
                            <p className="font-mono text-2xl font-medium text-[#0A4F5C]">{formataMoeda(proxima.valor_total)}</p>
                            <p className="mt-1 text-sm text-[#6B7370]">Vencimento: {formatDate(proxima.data_vencimento)}</p>
                            {diasRestantes !== null && diasRestantes < 0 && <p className="mt-1 text-xs font-medium text-[#A83232]">Vencido há {Math.abs(diasRestantes)} dia(s)</p>}
                            {diasRestantes !== null && diasRestantes >= 0 && <p className="mt-1 text-xs text-[#1B6B3A]">{diasRestantes} dia(s) restante(s)</p>}
                        </div>
                        <Link href={`/financeiro/cobrancas/${proxima.id}`} className="rounded-md bg-[#0A4F5C] px-4 py-2 text-sm text-white hover:bg-[#073B45]">Ver detalhes</Link>
                    </div>
                </div>
            ) : (
                <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5"><p className="text-sm text-[#8A918E]">Nenhuma cobrança pendente.</p></div>
            )}

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-4">
                    <p className="text-[10px] font-medium uppercase tracking-wide text-[#6B7370]">Total pago no ano</p>
                    <p className="mt-1 font-mono text-xl font-medium text-[#0A4F5C]">{formataMoeda(props.total_pago_ano ?? 0)}</p>
                </div>
                <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-4">
                    <p className="text-[10px] font-medium uppercase tracking-wide text-[#6B7370]">Cobranças em dia</p>
                    <p className="mt-1 text-xl font-medium text-[#1B6B3A]">{props.perc_em_dia ?? 100}%</p>
                </div>
            </div>

            <div className="overflow-hidden rounded-[10px] border border-[#D8DCDA] bg-white">
                <div className="border-b border-[#EEF0EF] px-4 py-3"><p className="text-sm font-medium text-[#1E2D30]">Minhas cobranças</p></div>
                <Table>
                    <TableHeader>
                        <TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                            <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Ref.</TableHead>
                            <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Valor</TableHead>
                            <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Status</TableHead>
                            <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Vencimento</TableHead>
                            <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Pagamento</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {(props.ultimas_cobrancas ?? []).map((cob: any) => (
                            <TableRow key={cob.id} className="cursor-pointer border-b border-[#F7F8F7] hover:bg-[#FAFBFA]"
                                onClick={() => router.visit(`/financeiro/cobrancas/${cob.id}`)}>
                                <TableCell className="font-mono text-sm">{cob.referencia}</TableCell>
                                <TableCell className="text-right font-mono text-sm font-medium">{formataMoeda(cob.valor_total)}</TableCell>
                                <TableCell><StatusBadge status={cob.status} tipo="cobranca" /></TableCell>
                                <TableCell className="text-xs text-[#6B7370]">{formatDate(cob.data_vencimento)}</TableCell>
                                <TableCell className="text-xs text-[#6B7370]">{cob.data_pagamento ? formatDate(cob.data_pagamento) : '—'}</TableCell>
                            </TableRow>
                        ))}
                        {(props.ultimas_cobrancas ?? []).length === 0 && (
                            <TableRow><TableCell colSpan={5} className="py-8 text-center text-sm text-[#8A918E]">Nenhuma cobrança</TableCell></TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
        </>
    );
}
