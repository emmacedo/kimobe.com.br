import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle, Clock, MoreHorizontal, Search, Wallet, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { usePermissions } from '@/hooks/use-permissions';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formataMoeda } from '@/lib/utils';

type RepasseData = {
    id: number;
    valor_aluguel_bruto: string;
    taxa_administracao_valor: string;
    taxa_seguro_inadimplencia_valor: string | null;
    valor_liquido: string;
    data_prevista: string;
    data_realizada: string | null;
    status: string;
    titularidade: {
        percentual: string;
        vinculo: { user: { name: string } };
    };
    cobranca: {
        id: number;
        referencia: string;
        contrato: {
            id: number;
            modelo_repasse: string;
            imovel: { logradouro: string; numero: string; complemento: string | null };
        };
    };
};

type PaginatedData = {
    data: RepasseData[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

type Resumo = {
    pendentes_count: number;
    pendentes_valor: number;
    realizados_count: number;
    realizados_valor: number;
    total_liquido: number;
};

type Props = {
    repasses: PaginatedData;
    resumo: Resumo;
    filtros: { mes: string; busca: string; status: string };
};

function formatDate(d: string): string { return new Date(d).toLocaleDateString('pt-BR'); }
function enderecoResumo(r: RepasseData): string {
    const im = r.cobranca.contrato.imovel;
    return im.complemento || `${im.logradouro}, ${im.numero}`;
}
function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function gerarOpcoesMes(): { value: string; label: string }[] {
    const opcoes = [];
    const agora = new Date();
    for (let i = -6; i <= 3; i++) {
        const d = new Date(agora.getFullYear(), agora.getMonth() + i, 1);
        const value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        const label = d.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
        opcoes.push({ value, label: label.charAt(0).toUpperCase() + label.slice(1) });
    }
    return opcoes;
}

export default function RepassesIndex({ repasses, resumo, filtros }: Props) {
    const { flash } = usePage().props as any;
    const { can } = usePermissions();
    const [busca, setBusca] = useState(filtros.busca);
    const [selecionados, setSelecionados] = useState<Set<number>>(new Set());
    const [loteOpen, setLoteOpen] = useState(false);
    const [loteData, setLoteData] = useState(new Date().toISOString().split('T')[0]);
    const [loteObs, setLoteObs] = useState('');
    const [loteSaving, setLoteSaving] = useState(false);
    const [confirmarTarget, setConfirmarTarget] = useState<RepasseData | null>(null);
    const [confirmarData, setConfirmarData] = useState(new Date().toISOString().split('T')[0]);
    const [confirmarSaving, setConfirmarSaving] = useState(false);
    const [cancelTarget, setCancelTarget] = useState<RepasseData | null>(null);
    const [cancelLoading, setCancelLoading] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    const temFiltros = filtros.busca || filtros.status;
    const opcoesMes = gerarOpcoesMes();
    const pendentes = repasses.data.filter((r) => r.status === 'pendente');
    const todosSelecionados = pendentes.length > 0 && pendentes.every((r) => selecionados.has(r.id));
    const selecionadosArr = repasses.data.filter((r) => selecionados.has(r.id));
    const totalSelecionado = selecionadosArr.reduce((a, r) => a + parseFloat(r.valor_liquido), 0);

    useEffect(() => { if (flash?.success) toast.success(flash.success); }, [flash?.success]);

    function aplicarFiltros(override: Record<string, string | undefined>) {
        const params: Record<string, string | undefined> = {
            mes: filtros.mes, busca: filtros.busca || undefined, status: filtros.status || undefined, ...override,
        };
        Object.keys(params).forEach((k) => params[k] === undefined && delete params[k]);
        router.get('/financeiro/repasses', params, { preserveState: true, preserveScroll: true, replace: true });
    }

    const debounceBusca = useCallback((valor: string) => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => aplicarFiltros({ busca: valor || undefined }), 300);
    }, [filtros]);

    function toggleSelect(id: number) {
        setSelecionados((prev) => { const s = new Set(prev); s.has(id) ? s.delete(id) : s.add(id); return s; });
    }

    function toggleAll() {
        if (todosSelecionados) { setSelecionados(new Set()); }
        else { setSelecionados(new Set(pendentes.map((r) => r.id))); }
    }

    async function handleConfirmarLote() {
        setLoteSaving(true);
        try {
            const response = await fetch('/financeiro/repasses/confirmar-lote', {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
                body: JSON.stringify({ repasse_ids: [...selecionados], data_realizada: loteData, observacoes: loteObs || null }),
            });
            const data = await response.json();
            toast.success(`${data.quantidade} repasse(s) confirmado(s) — Total: ${formataMoeda(data.valor_total)}`);
            setLoteOpen(false);
            setSelecionados(new Set());
            router.reload();
        } catch { toast.error('Erro ao confirmar repasses.'); }
        finally { setLoteSaving(false); }
    }

    function handleConfirmarIndividual() {
        if (!confirmarTarget) return;
        setConfirmarSaving(true);
        router.patch(`/financeiro/repasses/${confirmarTarget.id}/confirmar`, {
            data_realizada: confirmarData, observacoes: '',
        }, {
            onFinish: () => { setConfirmarSaving(false); setConfirmarTarget(null); },
        });
    }

    function handleCancelar() {
        if (!cancelTarget) return;
        setCancelLoading(true);
        router.patch(`/financeiro/repasses/${cancelTarget.id}/cancelar`, {}, {
            onFinish: () => { setCancelLoading(false); setCancelTarget(null); },
        });
    }

    const cards = [
        { titulo: 'Pendentes', valor: resumo.pendentes_valor, count: resumo.pendentes_count, cor: '#8C5A10', icon: Clock },
        { titulo: 'Realizados no mês', valor: resumo.realizados_valor, count: resumo.realizados_count, cor: '#1B6B3A', icon: CheckCircle },
        { titulo: 'Total líquido no mês', valor: resumo.total_liquido, count: null, cor: '#0A4F5C', icon: Wallet },
    ];

    return (
        <>
            <Head title="Repasses" />
            <div className="space-y-4">
                <PageHeader titulo="Repasses" subtitulo={repasses.total > 0 ? `${repasses.total} repasse(s)` : undefined}>
                    {can.manage_repasses && selecionados.size > 0 && (
                        <Button className="bg-[#C9A84C] text-white hover:bg-[#B8993F]" size="sm" onClick={() => setLoteOpen(true)}>
                            Confirmar {selecionados.size} selecionado(s)
                        </Button>
                    )}
                </PageHeader>

                {/* Cards resumo */}
                <div className="grid gap-3 sm:grid-cols-3">
                    {cards.map((card) => (
                        <div key={card.titulo} className="rounded-[10px] border border-[#D8DCDA] bg-white p-4">
                            <div className="mb-1.5 flex items-center gap-2">
                                <card.icon className="h-3.5 w-3.5" style={{ color: card.cor }} />
                                <p className="text-[10px] font-medium uppercase tracking-wide text-[#6B7370]">{card.titulo}</p>
                            </div>
                            <p className="font-mono text-xl font-medium" style={{ color: card.cor }}>{formataMoeda(card.valor)}</p>
                            {card.count !== null && <p className="mt-1 text-[10px] text-[#8A918E]">{card.count} repasse(s)</p>}
                        </div>
                    ))}
                </div>

                {/* Período */}
                <Select value={filtros.mes} onValueChange={(v) => aplicarFiltros({ mes: v })}>
                    <SelectTrigger className="w-48 bg-white border-[#D8DCDA]"><SelectValue /></SelectTrigger>
                    <SelectContent>{opcoesMes.map((m) => <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>)}</SelectContent>
                </Select>

                {/* Filtros */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A918E]" />
                        <Input placeholder="Buscar por proprietário ou imóvel..." value={busca}
                            onChange={(e) => { setBusca(e.target.value); debounceBusca(e.target.value); }}
                            className="pl-9 bg-white border-[#D8DCDA]" />
                    </div>
                    <Select value={filtros.status || 'todos'} onValueChange={(v) => aplicarFiltros({ status: v === 'todos' ? undefined : v })}>
                        <SelectTrigger className="w-full sm:w-36 bg-white border-[#D8DCDA]"><SelectValue placeholder="Status" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="todos">Todos</SelectItem>
                            <SelectItem value="pendente">Pendente</SelectItem>
                            <SelectItem value="realizado">Realizado</SelectItem>
                            <SelectItem value="cancelado">Cancelado</SelectItem>
                        </SelectContent>
                    </Select>
                    {temFiltros && <Button variant="ghost" size="sm" onClick={() => { setBusca(''); aplicarFiltros({ busca: undefined, status: undefined }); }} className="text-[#6B7370]"><X className="mr-1 h-3.5 w-3.5" />Limpar</Button>}
                </div>

                {/* Tabela */}
                {repasses.data.length === 0 ? (
                    <EmptyState icone={Wallet} titulo="Nenhum repasse encontrado"
                        descricao="Os repasses são gerados automaticamente quando cobranças são pagas ou criadas (modelo garantido)." />
                ) : (
                    <div className="overflow-hidden rounded-[10px] border border-[#D8DCDA] bg-white">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                                    {can.manage_repasses && (
                                        <TableHead className="w-10">
                                            <Checkbox checked={todosSelecionados} onCheckedChange={toggleAll} aria-label="Selecionar todos" />
                                        </TableHead>
                                    )}
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Ref.</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Titular</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Imóvel</TableHead>
                                    <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">%</TableHead>
                                    <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Líquido</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Previsto</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Status</TableHead>
                                    <TableHead className="w-10" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {repasses.data.map((r) => (
                                    <TableRow key={r.id}
                                        className={`cursor-pointer border-b border-[#F7F8F7] text-[#3A4240] hover:bg-[#FAFBFA] ${r.status === 'pendente' && new Date(r.data_prevista) < new Date() ? 'bg-[#FFF4E5]/30' : ''}`}
                                        onClick={() => router.visit(`/financeiro/repasses/${r.id}`)}>
                                        {can.manage_repasses && (
                                            <TableCell onClick={(e) => e.stopPropagation()}>
                                                {r.status === 'pendente' && (
                                                    <Checkbox checked={selecionados.has(r.id)} onCheckedChange={() => toggleSelect(r.id)} />
                                                )}
                                            </TableCell>
                                        )}
                                        <TableCell className="font-mono text-sm">{r.cobranca.referencia}</TableCell>
                                        <TableCell className="text-sm font-medium text-[#1E2D30]">{r.titularidade.vinculo.user.name}</TableCell>
                                        <TableCell className="max-w-[150px] truncate text-sm">{enderecoResumo(r)}</TableCell>
                                        <TableCell className="text-right text-sm text-[#6B7370]">{parseFloat(r.titularidade.percentual).toFixed(0)}%</TableCell>
                                        <TableCell className="text-right font-mono text-sm font-medium text-[#0A4F5C]">{formataMoeda(r.valor_liquido)}</TableCell>
                                        <TableCell className="text-xs text-[#6B7370]">{formatDate(r.data_prevista)}</TableCell>
                                        <TableCell><StatusBadge status={r.status} tipo="repasse" /></TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="h-8 w-8" onClick={(e) => e.stopPropagation()}><MoreHorizontal className="h-4 w-4" /></Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild><Link href={`/financeiro/repasses/${r.id}`}>Ver detalhes</Link></DropdownMenuItem>
                                                    {can.manage_repasses && r.status === 'pendente' && (
                                                        <>
                                                            <DropdownMenuItem onClick={(e) => { e.stopPropagation(); setConfirmarTarget(r); }}>Confirmar repasse</DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem className="text-[#A83232]" onClick={(e) => { e.stopPropagation(); setCancelTarget(r); }}>Cancelar</DropdownMenuItem>
                                                        </>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {repasses.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-[#EEF0EF] px-4 py-3">
                                <p className="text-xs text-[#8A918E]">Mostrando {repasses.from}–{repasses.to} de {repasses.total}</p>
                                <div className="flex items-center gap-1">
                                    {repasses.links.map((link, i) => {
                                        if (i === 0 || i === repasses.links.length - 1) return (
                                            <Button key={i} variant="ghost" size="sm" disabled={!link.url}
                                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })} className="text-xs text-[#6B7370]">
                                                {i === 0 ? '← Anterior' : 'Próxima →'}
                                            </Button>
                                        );
                                        return (
                                            <Button key={i} variant={link.active ? 'default' : 'ghost'} size="sm"
                                                className={link.active ? 'bg-[#0A4F5C] text-white hover:bg-[#073B45] h-8 w-8' : 'text-[#6B7370] h-8 w-8'}
                                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })} disabled={!link.url}>{link.label}</Button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>
                )}

                {/* Barra de seleção fixa */}
                {can.manage_repasses && selecionados.size > 0 && (
                    <div className="fixed bottom-0 left-0 right-0 z-40 border-t border-[#D8DCDA] bg-white px-6 py-3 shadow-lg">
                        <div className="mx-auto flex max-w-7xl items-center justify-between">
                            <p className="text-sm text-[#3A4240]">
                                <strong>{selecionados.size}</strong> repasse(s) selecionado(s) — Total: <strong className="font-mono text-[#0A4F5C]">{formataMoeda(totalSelecionado)}</strong>
                            </p>
                            <div className="flex gap-2">
                                <Button variant="ghost" size="sm" onClick={() => setSelecionados(new Set())} className="text-[#6B7370]">Cancelar seleção</Button>
                                <Button size="sm" className="bg-[#C9A84C] text-white hover:bg-[#B8993F]" onClick={() => setLoteOpen(true)}>Confirmar selecionados</Button>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* Dialog confirmação em lote */}
            <Dialog open={loteOpen} onOpenChange={setLoteOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader><DialogTitle>Confirmar {selecionados.size} repasse(s)</DialogTitle></DialogHeader>
                    <div className="max-h-48 overflow-y-auto rounded-md border border-[#EEF0EF]">
                        {selecionadosArr.map((r) => (
                            <div key={r.id} className="flex items-center justify-between border-b border-[#EEF0EF] px-3 py-2 last:border-0 text-xs">
                                <div><p className="font-medium text-[#1E2D30]">{r.titularidade.vinculo.user.name}</p><p className="text-[#8A918E]">{enderecoResumo(r)}</p></div>
                                <span className="font-mono text-[#0A4F5C]">{formataMoeda(r.valor_liquido)}</span>
                            </div>
                        ))}
                    </div>
                    <p className="text-sm font-medium text-[#1E2D30]">Total: <span className="font-mono text-[#0A4F5C]">{formataMoeda(totalSelecionado)}</span></p>
                    <div><Label>Data da transferência</Label><Input type="date" value={loteData} onChange={(e) => setLoteData(e.target.value)} className="bg-white border-[#D8DCDA]" /></div>
                    <div><Label>Observações <span className="text-[#8A918E]">(opcional)</span></Label>
                        <textarea value={loteObs} onChange={(e) => setLoteObs(e.target.value)} rows={2} className="w-full rounded-md border border-[#D8DCDA] bg-white px-3 py-2 text-sm focus:border-[#0A4F5C] focus:outline-none focus:ring-1 focus:ring-[#0A4F5C]" />
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setLoteOpen(false)} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button onClick={handleConfirmarLote} disabled={loteSaving} className="bg-[#C9A84C] text-white hover:bg-[#B8993F]">{loteSaving && <Spinner />}Confirmar todos</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Dialog confirmação individual */}
            <Dialog open={!!confirmarTarget} onOpenChange={(open) => !open && setConfirmarTarget(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader><DialogTitle>Confirmar repasse</DialogTitle></DialogHeader>
                    {confirmarTarget && (
                        <div className="space-y-3">
                            <div className="rounded-md bg-[#F7F8F7] p-3 text-sm">
                                <p className="font-medium text-[#1E2D30]">{confirmarTarget.titularidade.vinculo.user.name}</p>
                                <p className="text-xs text-[#8A918E]">{enderecoResumo(confirmarTarget)}</p>
                                <p className="mt-1 font-mono text-lg font-medium text-[#0A4F5C]">{formataMoeda(confirmarTarget.valor_liquido)}</p>
                            </div>
                            <div><Label>Data da transferência</Label><Input type="date" value={confirmarData} onChange={(e) => setConfirmarData(e.target.value)} className="bg-white border-[#D8DCDA]" /></div>
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmarTarget(null)} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button onClick={handleConfirmarIndividual} disabled={confirmarSaving} className="bg-[#C9A84C] text-white hover:bg-[#B8993F]">{confirmarSaving && <Spinner />}Confirmar</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Dialog cancelamento */}
            <ConfirmDialog open={!!cancelTarget} onOpenChange={(open) => !open && setCancelTarget(null)}
                titulo="Cancelar repasse" descricao={cancelTarget ? `Cancelar o repasse de ${formataMoeda(cancelTarget.valor_liquido)} para ${cancelTarget.titularidade.vinculo.user.name}?` : ''}
                textoConfirmar="Cancelar repasse" textoCancelar="Voltar" variante="destructive" loading={cancelLoading} onConfirm={handleCancelar} />
        </>
    );
}
