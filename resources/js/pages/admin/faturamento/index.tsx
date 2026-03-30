import { Head, router, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle, Clock, Gift, Loader2, MoreHorizontal, Plus, Search, Wand2, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formataMoeda } from '@/lib/utils';

type FaturaData = { id: number; referencia: string; valor: string; data_vencimento: string; data_pagamento: string | null; status: string; metodo_pagamento: string | null; tenant: { id: number; nome: string; tipo: string }; plano: { nome: string } | null };
type PaginatedData = { data: FaturaData[]; current_page: number; last_page: number; total: number; from: number | null; to: number | null; links: Array<{ url: string | null; label: string; active: boolean }> };
type Resumo = { a_receber: number; a_receber_count: number; recebido: number; recebido_count: number; inadimplentes: number; cortesias: number };
type Props = { faturas: PaginatedData; resumo: Resumo; filtros: { mes: string; busca: string; status: string } };

function formatDate(d: string) { return new Date(d).toLocaleDateString('pt-BR'); }
function getCsrfToken() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''; }
function gerarOpcoesMes() { const o: { value: string; label: string }[] = []; const n = new Date(); for (let i = -6; i <= 3; i++) { const d = new Date(n.getFullYear(), n.getMonth() + i, 1); o.push({ value: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`, label: d.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' }).replace(/^./, (c) => c.toUpperCase()) }); } return o; }

export default function FaturamentoIndex({ faturas, resumo, filtros }: Props) {
    const { flash } = usePage().props as any;
    const [busca, setBusca] = useState(filtros.busca);
    const [geracaoOpen, setGeracaoOpen] = useState(false);
    const [geracaoRef, setGeracaoRef] = useState('');
    const [geracaoPreview, setGeracaoPreview] = useState<any[]>([]);
    const [geracaoLoading, setGeracaoLoading] = useState(false);
    const [geracaoSaving, setGeracaoSaving] = useState(false);
    const [pagamentoTarget, setPagamentoTarget] = useState<FaturaData | null>(null);
    const [pgtoData, setPgtoData] = useState(new Date().toISOString().split('T')[0]);
    const [pgtoMetodo, setPgtoMetodo] = useState('');
    const [pgtoSaving, setPgtoSaving] = useState(false);
    const [cancelTarget, setCancelTarget] = useState<FaturaData | null>(null);
    const [cancelLoading, setCancelLoading] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    const temFiltros = filtros.busca || filtros.status;
    const opcoesMes = gerarOpcoesMes();

    useEffect(() => { if (flash?.success) toast.success(flash.success); }, [flash?.success]);

    function aplicarFiltros(override: Record<string, string | undefined>) {
        const p: Record<string, string | undefined> = { mes: filtros.mes, busca: filtros.busca || undefined, status: filtros.status || undefined, ...override };
        Object.keys(p).forEach((k) => !p[k] && delete p[k]);
        router.get('/admin/faturamento', p, { preserveState: true, preserveScroll: true, replace: true });
    }

    const debounceBusca = useCallback((v: string) => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => aplicarFiltros({ busca: v || undefined }), 300);
    }, [filtros]);

    async function abrirGeracao() {
        const proximo = new Date(); proximo.setMonth(proximo.getMonth() + 1);
        const ref = `${String(proximo.getMonth() + 1).padStart(2, '0')}/${proximo.getFullYear()}`;
        setGeracaoRef(ref); setGeracaoOpen(true); setGeracaoLoading(true);
        try {
            const resp = await fetch(`/admin/faturamento/preview?referencia=${encodeURIComponent(ref)}`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() } });
            const data = await resp.json(); setGeracaoPreview(data.tenants ?? []);
        } catch { setGeracaoPreview([]); } finally { setGeracaoLoading(false); }
    }

    async function handleGerar() {
        setGeracaoSaving(true);
        try {
            const resp = await fetch('/admin/faturamento/gerar', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' }, body: JSON.stringify({ referencia: geracaoRef }) });
            const data = await resp.json();
            toast.success(`${data.quantidade} fatura(s) gerada(s). Total: ${formataMoeda(data.valor_total)}`);
            setGeracaoOpen(false); router.reload();
        } catch { toast.error('Erro ao gerar faturas.'); } finally { setGeracaoSaving(false); }
    }

    function handlePagamento() {
        if (!pagamentoTarget || !pgtoMetodo) return;
        setPgtoSaving(true);
        router.patch(`/admin/faturamento/${pagamentoTarget.id}/pagamento`, { data_pagamento: pgtoData, metodo_pagamento: pgtoMetodo }, { onFinish: () => { setPgtoSaving(false); setPagamentoTarget(null); } });
    }

    function handleCancelar() {
        if (!cancelTarget) return;
        setCancelLoading(true);
        router.patch(`/admin/faturamento/${cancelTarget.id}/cancelar`, {}, { onFinish: () => { setCancelLoading(false); setCancelTarget(null); } });
    }

    const cards = [
        { titulo: 'A receber', valor: resumo.a_receber, count: resumo.a_receber_count, cor: '#0A4F5C', icon: Clock },
        { titulo: 'Recebido', valor: resumo.recebido, count: resumo.recebido_count, cor: '#1B6B3A', icon: CheckCircle },
        { titulo: 'Inadimplentes', valor: resumo.inadimplentes, count: null, cor: '#A83232', icon: AlertCircle },
        { titulo: 'Cortesias', valor: resumo.cortesias, count: null, cor: '#C9A84C', icon: Gift },
    ];

    return (
        <>
            <Head title="Admin — Faturamento" />
            <div className="space-y-4">
                <PageHeader titulo="Faturamento">
                    <Button className="bg-[#C9A84C] text-[#2E2410] hover:bg-[#B8993F]" size="sm" onClick={abrirGeracao}><Wand2 className="h-4 w-4" />Gerar faturas do mês</Button>
                </PageHeader>

                <div className="grid gap-3 sm:grid-cols-4">
                    {cards.map((c) => (
                        <div key={c.titulo} className="rounded-[10px] border border-[#D8DCDA] bg-white p-4">
                            <div className="mb-1.5 flex items-center gap-2"><c.icon className="h-3.5 w-3.5" style={{ color: c.cor }} /><p className="text-[10px] font-medium uppercase tracking-wide text-[#6B7370]">{c.titulo}</p></div>
                            <p className="font-mono text-xl font-medium" style={{ color: c.cor }}>{typeof c.valor === 'number' && c.valor > 100 ? formataMoeda(c.valor) : c.valor}</p>
                            {c.count !== null && <p className="mt-1 text-[10px] text-[#8A918E]">{c.count} fatura(s)</p>}
                        </div>
                    ))}
                </div>

                <Select value={filtros.mes} onValueChange={(v) => aplicarFiltros({ mes: v })}><SelectTrigger className="w-48 bg-white border-[#D8DCDA]"><SelectValue /></SelectTrigger><SelectContent>{opcoesMes.map((m) => <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>)}</SelectContent></Select>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1"><Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A918E]" /><Input placeholder="Buscar assinante..." value={busca} onChange={(e) => { setBusca(e.target.value); debounceBusca(e.target.value); }} className="pl-9 bg-white border-[#D8DCDA]" /></div>
                    <Select value={filtros.status || 'todos'} onValueChange={(v) => aplicarFiltros({ status: v === 'todos' ? undefined : v })}><SelectTrigger className="w-full sm:w-36 bg-white border-[#D8DCDA]"><SelectValue /></SelectTrigger><SelectContent><SelectItem value="todos">Todos</SelectItem><SelectItem value="pendente">Pendente</SelectItem><SelectItem value="pago">Pago</SelectItem><SelectItem value="atrasado">Atrasado</SelectItem><SelectItem value="cancelado">Cancelado</SelectItem></SelectContent></Select>
                    {temFiltros && <Button variant="ghost" size="sm" onClick={() => { setBusca(''); aplicarFiltros({ busca: undefined, status: undefined }); }} className="text-[#6B7370]"><X className="mr-1 h-3.5 w-3.5" />Limpar</Button>}
                </div>

                {faturas.data.length === 0 ? (
                    <EmptyState icone={Clock} titulo="Nenhuma fatura encontrada" descricao="Gere as faturas do mês ou ajuste os filtros." />
                ) : (
                    <div className="overflow-hidden rounded-[10px] border border-[#D8DCDA] bg-white">
                        <Table>
                            <TableHeader><TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Assinante</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Plano</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Ref.</TableHead>
                                <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Valor</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Vencimento</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Status</TableHead>
                                <TableHead className="w-10" />
                            </TableRow></TableHeader>
                            <TableBody>
                                {faturas.data.map((f) => (
                                    <TableRow key={f.id} className={`border-b border-[#F7F8F7] hover:bg-[#FAFBFA] ${f.status === 'atrasado' ? 'bg-[#FDECEC]/30' : ''}`}>
                                        <TableCell><p className="text-sm font-medium text-[#1E2D30]">{f.tenant.nome}</p><Badge variant="outline" className="text-[9px]">{f.tenant.tipo === 'imobiliaria' ? 'Imobiliária' : 'Proprietário'}</Badge></TableCell>
                                        <TableCell className="text-xs text-[#6B7370]">{f.plano?.nome ?? '—'}</TableCell>
                                        <TableCell className="font-mono text-sm">{f.referencia}</TableCell>
                                        <TableCell className="text-right font-mono text-sm font-medium text-[#1E2D30]">{formataMoeda(f.valor)}</TableCell>
                                        <TableCell className={`text-xs ${f.status === 'atrasado' ? 'text-[#A83232] font-medium' : 'text-[#6B7370]'}`}>{formatDate(f.data_vencimento)}</TableCell>
                                        <TableCell><StatusBadge status={f.status} tipo="cobranca" /></TableCell>
                                        <TableCell>
                                            <DropdownMenu><DropdownMenuTrigger asChild><Button variant="ghost" size="icon" className="h-8 w-8"><MoreHorizontal className="h-4 w-4" /></Button></DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    {['pendente', 'atrasado'].includes(f.status) && <><DropdownMenuItem onClick={() => setPagamentoTarget(f)}>Registrar pagamento</DropdownMenuItem><DropdownMenuSeparator /><DropdownMenuItem className="text-[#A83232]" onClick={() => setCancelTarget(f)}>Cancelar fatura</DropdownMenuItem></>}
                                                    <DropdownMenuItem onClick={() => router.visit(`/admin/assinantes/${f.tenant.id}`)}>Ver assinante</DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        {faturas.last_page > 1 && <div className="flex items-center justify-between border-t border-[#EEF0EF] px-4 py-3"><p className="text-xs text-[#8A918E]">Mostrando {faturas.from}–{faturas.to} de {faturas.total}</p></div>}
                    </div>
                )}
            </div>

            {/* Dialog geração */}
            <Dialog open={geracaoOpen} onOpenChange={setGeracaoOpen}>
                <DialogContent className="sm:max-w-lg"><DialogHeader><DialogTitle>Gerar faturas</DialogTitle><DialogDescription>Referência: {geracaoRef}</DialogDescription></DialogHeader>
                    {geracaoLoading ? <div className="flex justify-center py-4"><Loader2 className="h-5 w-5 animate-spin" /></div> : geracaoPreview.length > 0 ? (
                        <><div className="max-h-48 overflow-y-auto rounded-md border border-[#EEF0EF]">{geracaoPreview.map((t: any) => (<div key={t.id} className="flex items-center justify-between border-b border-[#EEF0EF] px-3 py-2 last:border-0 text-xs"><span className="text-[#1E2D30]">{t.nome} ({t.plano})</span><span className="font-mono">{formataMoeda(t.valor)}</span></div>))}</div><p className="text-xs text-[#6B7370]">Total: {formataMoeda(geracaoPreview.reduce((a: number, t: any) => a + parseFloat(t.valor), 0))}</p></>
                    ) : <p className="text-sm text-[#8A918E]">Todos os assinantes já possuem fatura neste mês.</p>}
                    <DialogFooter><Button variant="outline" onClick={() => setGeracaoOpen(false)} className="border-[#D8DCDA]">Cancelar</Button><Button onClick={handleGerar} disabled={geracaoSaving || geracaoPreview.length === 0} className="bg-[#C9A84C] text-[#2E2410] hover:bg-[#B8993F]">{geracaoSaving && <Spinner />}Gerar {geracaoPreview.length} fatura(s)</Button></DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Dialog pagamento */}
            <Dialog open={!!pagamentoTarget} onOpenChange={(o) => !o && setPagamentoTarget(null)}>
                <DialogContent className="sm:max-w-md"><DialogHeader><DialogTitle>Registrar pagamento</DialogTitle></DialogHeader>
                    {pagamentoTarget && <div className="rounded-md bg-[#F7F8F7] p-3 text-sm"><p className="font-medium text-[#1E2D30]">{pagamentoTarget.tenant.nome}</p><p className="mt-1 font-mono text-lg text-[#0A4F5C]">{formataMoeda(pagamentoTarget.valor)}</p></div>}
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div><Label>Data</Label><Input type="date" value={pgtoData} onChange={(e) => setPgtoData(e.target.value)} className="bg-white border-[#D8DCDA]" /></div>
                        <div><Label>Método</Label><Select value={pgtoMetodo} onValueChange={setPgtoMetodo}><SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue placeholder="Selecione" /></SelectTrigger><SelectContent><SelectItem value="pix">PIX</SelectItem><SelectItem value="boleto">Boleto</SelectItem><SelectItem value="cartao">Cartão</SelectItem><SelectItem value="transferencia">Transferência</SelectItem></SelectContent></Select></div>
                    </div>
                    <DialogFooter><Button variant="outline" onClick={() => setPagamentoTarget(null)} className="border-[#D8DCDA]">Cancelar</Button><Button onClick={handlePagamento} disabled={pgtoSaving || !pgtoMetodo} className="bg-[#1B6B3A] text-white hover:bg-[#155A2F]">{pgtoSaving && <Spinner />}Confirmar</Button></DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog open={!!cancelTarget} onOpenChange={(o) => !o && setCancelTarget(null)} titulo="Cancelar fatura" descricao={cancelTarget ? `Cancelar fatura ${cancelTarget.referencia} de ${cancelTarget.tenant.nome}?` : ''} textoConfirmar="Cancelar fatura" variante="destructive" loading={cancelLoading} onConfirm={handleCancelar} />
        </>
    );
}
