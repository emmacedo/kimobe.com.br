import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle, Clock, Loader2, MoreHorizontal, Plus, Receipt, Search, Wand2, X, XCircle } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { usePermissions } from '@/hooks/use-permissions';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DialogPagamento } from '@/components/dialog-pagamento';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formataMoeda } from '@/lib/utils';

type CobrancaData = {
    id: number;
    referencia: string;
    valor_total: string;
    valor_pago: string | null;
    data_vencimento: string;
    data_pagamento: string | null;
    status: string;
    metodo_pagamento: string | null;
    contrato: {
        id: number;
        imovel: { id: number; logradouro: string; numero: string; complemento: string | null };
        inquilino: { user: { name: string } };
    };
};

type PaginatedData = {
    data: CobrancaData[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

type Resumo = {
    a_receber: number;
    a_receber_count: number;
    recebido: number;
    recebido_count: number;
    em_atraso: number;
    em_atraso_count: number;
    canceladas: number;
    canceladas_count: number;
};

type Filtros = {
    mes: string;
    busca: string;
    status: string;
    metodo_pagamento: string;
};

type Props = {
    cobrancas: PaginatedData;
    resumo: Resumo;
    filtros: Filtros;
};

function enderecoResumo(c: CobrancaData): string {
    return c.contrato.imovel.complemento || `${c.contrato.imovel.logradouro}, ${c.contrato.imovel.numero}`;
}

function formatDate(d: string): string {
    return new Date(d).toLocaleDateString('pt-BR');
}

// Gerar opções de mês (6 meses para trás, 3 para frente)
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

export default function CobrancasIndex({ cobrancas, resumo, filtros }: Props) {
    const { flash } = usePage().props as any;
    const { can, isInquilino } = usePermissions();
    const [busca, setBusca] = useState(filtros.busca);
    const [cancelTarget, setCancelTarget] = useState<CobrancaData | null>(null);
    const [cancelLoading, setCancelLoading] = useState(false);
    const [pagamentoTarget, setPagamentoTarget] = useState<CobrancaData | null>(null);
    const [geracaoOpen, setGeracaoOpen] = useState(false);
    const [geracaoRef, setGeracaoRef] = useState('');
    const [geracaoPreview, setGeracaoPreview] = useState<any[]>([]);
    const [geracaoLoading, setGeracaoLoading] = useState(false);
    const [geracaoSaving, setGeracaoSaving] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    const temFiltros = filtros.busca || filtros.status || filtros.metodo_pagamento;
    const opcoesMes = gerarOpcoesMes();

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    const debounceBusca = useCallback(
        (valor: string) => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
            debounceRef.current = setTimeout(() => {
                aplicarFiltros({ busca: valor || undefined });
            }, 300);
        },
        [filtros],
    );

    function aplicarFiltros(override: Record<string, string | undefined>) {
        const params: Record<string, string | undefined> = {
            mes: filtros.mes,
            busca: filtros.busca || undefined,
            status: filtros.status || undefined,
            metodo_pagamento: filtros.metodo_pagamento || undefined,
            ...override,
        };
        // Remover undefined
        Object.keys(params).forEach((k) => params[k] === undefined && delete params[k]);
        router.get('/financeiro/cobrancas', params, { preserveState: true, preserveScroll: true, replace: true });
    }

    function handleBuscaChange(valor: string) {
        setBusca(valor);
        debounceBusca(valor);
    }

    function handleFiltro(campo: string, valor: string) {
        aplicarFiltros({ [campo]: valor === 'todos' ? undefined : valor });
    }

    function limparFiltros() {
        setBusca('');
        aplicarFiltros({ busca: undefined, status: undefined, metodo_pagamento: undefined });
    }

    async function abrirGeracao() {
        // Default: próximo mês
        const agora = new Date();
        const proximo = new Date(agora.getFullYear(), agora.getMonth() + 1, 1);
        const ref = `${String(proximo.getMonth() + 1).padStart(2, '0')}/${proximo.getFullYear()}`;
        setGeracaoRef(ref);
        setGeracaoOpen(true);
        await carregarPreview(ref);
    }

    async function carregarPreview(ref: string) {
        setGeracaoLoading(true);
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const response = await fetch(`/financeiro/cobrancas/preview-mensais?referencia=${encodeURIComponent(ref)}`, {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
            });
            const data = await response.json();
            setGeracaoPreview(data.contratos ?? []);
        } catch { setGeracaoPreview([]); }
        finally { setGeracaoLoading(false); }
    }

    async function handleGerarMensais() {
        setGeracaoSaving(true);
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const response = await fetch('/financeiro/cobrancas/gerar-mensais', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                body: JSON.stringify({ referencia: geracaoRef }),
            });
            const data = await response.json();
            toast.success(`${data.quantidade} cobrança(s) gerada(s). Total: ${formataMoeda(data.valor_total)}`);
            setGeracaoOpen(false);
            router.reload();
        } catch { toast.error('Erro ao gerar cobranças.'); }
        finally { setGeracaoSaving(false); }
    }

    function handleCancelar() {
        if (!cancelTarget) return;
        setCancelLoading(true);
        router.patch(`/financeiro/cobrancas/${cancelTarget.id}/cancelar`, {}, {
            onFinish: () => { setCancelLoading(false); setCancelTarget(null); },
        });
    }

    const cards = [
        { titulo: isInquilino ? 'A pagar' : 'A receber', valor: resumo.a_receber, count: resumo.a_receber_count, cor: '#0A4F5C', icon: Clock },
        { titulo: 'Recebido', valor: resumo.recebido, count: resumo.recebido_count, cor: '#1B6B3A', icon: CheckCircle },
        { titulo: 'Em atraso', valor: resumo.em_atraso, count: resumo.em_atraso_count, cor: '#A83232', icon: AlertCircle },
        { titulo: 'Canceladas', valor: resumo.canceladas, count: resumo.canceladas_count, cor: '#6B7370', icon: XCircle },
    ];

    return (
        <>
            <Head title="Cobranças" />
            <div className="space-y-4">
                <PageHeader titulo={isInquilino ? 'Minhas cobranças' : 'Cobranças'} subtitulo={cobrancas.total > 0 ? `${cobrancas.total} cobrança(s)` : undefined}>
                    {can.manage_cobrancas && (
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" onClick={abrirGeracao} className="border-[#D8DCDA]">
                                <Wand2 className="h-4 w-4" />
                                Gerar do mês
                            </Button>
                            <Button className="bg-[#0A4F5C] text-white hover:bg-[#073B45]" size="sm" asChild>
                                <Link href="/financeiro/cobrancas/criar">
                                    <Plus className="h-4 w-4" />
                                    Nova cobrança
                                </Link>
                            </Button>
                        </div>
                    )}
                </PageHeader>

                {/* Cards de resumo */}
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {cards.map((card) => (
                        <div key={card.titulo} className="rounded-[10px] border border-[#D8DCDA] bg-white p-4">
                            <div className="mb-1.5 flex items-center gap-2">
                                <card.icon className="h-3.5 w-3.5" style={{ color: card.cor }} />
                                <p className="text-[10px] font-medium uppercase tracking-wide text-[#6B7370]">{card.titulo}</p>
                            </div>
                            <p className="font-mono text-xl font-medium" style={{ color: card.cor }}>
                                {formataMoeda(card.valor)}
                            </p>
                            <p className="mt-1 text-[10px] text-[#8A918E]">{card.count} cobrança(s)</p>
                        </div>
                    ))}
                </div>

                {/* Filtro de período */}
                <div className="flex items-center gap-2">
                    <Select value={filtros.mes} onValueChange={(v) => aplicarFiltros({ mes: v })}>
                        <SelectTrigger className="w-48 bg-white border-[#D8DCDA]">
                            <SelectValue placeholder="Período" />
                        </SelectTrigger>
                        <SelectContent>
                            {opcoesMes.map((m) => (
                                <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                {/* Filtros */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A918E]" />
                        <Input placeholder="Buscar por imóvel ou inquilino..." value={busca} onChange={(e) => handleBuscaChange(e.target.value)} className="pl-9 bg-white border-[#D8DCDA]" />
                    </div>
                    <Select value={filtros.status || 'todos'} onValueChange={(v) => handleFiltro('status', v)}>
                        <SelectTrigger className="w-full sm:w-36 bg-white border-[#D8DCDA]"><SelectValue placeholder="Status" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="todos">Todos</SelectItem>
                            <SelectItem value="pendente">Pendente</SelectItem>
                            <SelectItem value="pago">Pago</SelectItem>
                            <SelectItem value="atrasado">Atrasado</SelectItem>
                            <SelectItem value="cancelado">Cancelado</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select value={filtros.metodo_pagamento || 'todos'} onValueChange={(v) => handleFiltro('metodo_pagamento', v)}>
                        <SelectTrigger className="w-full sm:w-40 bg-white border-[#D8DCDA]"><SelectValue placeholder="Pagamento" /></SelectTrigger>
                        <SelectContent>
                            <SelectItem value="todos">Todos</SelectItem>
                            <SelectItem value="boleto">Boleto</SelectItem>
                            <SelectItem value="pix">PIX</SelectItem>
                            <SelectItem value="transferencia">Transferência</SelectItem>
                            <SelectItem value="dinheiro">Dinheiro</SelectItem>
                        </SelectContent>
                    </Select>
                    {temFiltros && (
                        <Button variant="ghost" size="sm" onClick={limparFiltros} className="text-[#6B7370]">
                            <X className="mr-1 h-3.5 w-3.5" />Limpar
                        </Button>
                    )}
                </div>

                {/* Tabela */}
                {cobrancas.data.length === 0 ? (
                    temFiltros ? (
                        <EmptyState icone={Search} titulo="Nenhuma cobrança encontrada" descricao="Tente ajustar os filtros ou o período selecionado."
                            acao={<Button variant="outline" size="sm" onClick={limparFiltros}>Limpar filtros</Button>} />
                    ) : (
                        <EmptyState icone={Receipt} titulo="Nenhuma cobrança neste período" descricao="As cobranças são geradas automaticamente a partir dos contratos ativos." />
                    )
                ) : (
                    <div className="overflow-hidden rounded-[10px] border border-[#D8DCDA] bg-white">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Ref.</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Imóvel</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Inquilino</TableHead>
                                    <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Valor</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Vencimento</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Pagamento</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Status</TableHead>
                                    <TableHead className="w-10" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {cobrancas.data.map((c) => (
                                    <TableRow
                                        key={c.id}
                                        className={`cursor-pointer border-b border-[#F7F8F7] text-[#3A4240] hover:bg-[#FAFBFA] ${c.status === 'atrasado' ? 'bg-[#FDECEC]/30' : ''}`}
                                        onClick={() => router.visit(`/financeiro/cobrancas/${c.id}`)}
                                    >
                                        <TableCell className="font-mono text-sm font-medium text-[#1E2D30]">{c.referencia}</TableCell>
                                        <TableCell className="max-w-[180px] truncate text-sm">{enderecoResumo(c)}</TableCell>
                                        <TableCell className="text-sm">{c.contrato.inquilino.user.name}</TableCell>
                                        <TableCell className="text-right font-mono text-sm font-medium text-[#1E2D30]">
                                            {formataMoeda(c.status === 'pago' && c.valor_pago ? c.valor_pago : c.valor_total)}
                                        </TableCell>
                                        <TableCell className={`text-xs ${c.status === 'atrasado' ? 'text-[#A83232] font-medium' : 'text-[#6B7370]'}`}>
                                            {formatDate(c.data_vencimento)}
                                        </TableCell>
                                        <TableCell className="text-xs text-[#6B7370]">{c.data_pagamento ? formatDate(c.data_pagamento) : '—'}</TableCell>
                                        <TableCell><StatusBadge status={c.status} tipo="cobranca" /></TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="h-8 w-8" onClick={(e) => e.stopPropagation()} aria-label="Ações">
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link href={`/financeiro/cobrancas/${c.id}`}>Ver detalhes</Link>
                                                    </DropdownMenuItem>
                                                    {can.manage_cobrancas && ['pendente', 'atrasado'].includes(c.status) && (
                                                        <>
                                                            <DropdownMenuItem onClick={(e) => { e.stopPropagation(); setPagamentoTarget(c); }}>
                                                                Registrar pagamento
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem className="text-[#A83232] focus:text-[#A83232]"
                                                                onClick={(e) => { e.stopPropagation(); setCancelTarget(c); }}>
                                                                Cancelar cobrança
                                                            </DropdownMenuItem>
                                                        </>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {cobrancas.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-[#EEF0EF] px-4 py-3">
                                <p className="text-xs text-[#8A918E]">Mostrando {cobrancas.from}–{cobrancas.to} de {cobrancas.total}</p>
                                <div className="flex items-center gap-1">
                                    {cobrancas.links.map((link, i) => {
                                        if (i === 0 || i === cobrancas.links.length - 1) {
                                            return (
                                                <Button key={i} variant="ghost" size="sm" disabled={!link.url}
                                                    onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                                    className="text-xs text-[#6B7370]">
                                                    {i === 0 ? '← Anterior' : 'Próxima →'}
                                                </Button>
                                            );
                                        }
                                        return (
                                            <Button key={i} variant={link.active ? 'default' : 'ghost'} size="sm"
                                                className={link.active ? 'bg-[#0A4F5C] text-white hover:bg-[#073B45] h-8 w-8' : 'text-[#6B7370] h-8 w-8'}
                                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })} disabled={!link.url}>
                                                {link.label}
                                            </Button>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>

            <ConfirmDialog
                open={!!cancelTarget}
                onOpenChange={(open) => !open && setCancelTarget(null)}
                titulo="Cancelar cobrança"
                descricao={cancelTarget ? `Tem certeza que deseja cancelar a cobrança de ${cancelTarget.referencia} do imóvel ${enderecoResumo(cancelTarget)}? Se houver repasses vinculados, eles também serão cancelados.` : ''}
                textoConfirmar="Cancelar cobrança"
                textoCancelar="Voltar"
                variante="destructive"
                loading={cancelLoading}
                onConfirm={handleCancelar}
            />

            {/* Dialog registrar pagamento */}
            {pagamentoTarget && (
                <DialogPagamento
                    open={!!pagamentoTarget}
                    onOpenChange={(open) => !open && setPagamentoTarget(null)}
                    cobranca={pagamentoTarget as any}
                />
            )}

            {/* Dialog geração automática */}
            <Dialog open={geracaoOpen} onOpenChange={setGeracaoOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Gerar cobranças do mês</DialogTitle>
                        <DialogDescription>Selecione o mês de referência para gerar as cobranças automaticamente.</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3">
                        <div>
                            <Label>Referência (MM/YYYY)</Label>
                            <Input value={geracaoRef} onChange={(e) => { setGeracaoRef(e.target.value); if (e.target.value.length === 7) carregarPreview(e.target.value); }}
                                placeholder="04/2026" maxLength={7} className="bg-white border-[#D8DCDA]" />
                        </div>
                        {geracaoLoading ? (
                            <div className="flex items-center justify-center py-4"><Loader2 className="h-5 w-5 animate-spin text-[#8A918E]" /></div>
                        ) : geracaoPreview.length > 0 ? (
                            <div className="max-h-48 overflow-y-auto rounded-md border border-[#EEF0EF]">
                                {geracaoPreview.map((c: any) => (
                                    <div key={c.id} className="flex items-center justify-between border-b border-[#EEF0EF] px-3 py-2 last:border-0 text-xs">
                                        <div>
                                            <p className="font-medium text-[#1E2D30]">{c.imovel}</p>
                                            <p className="text-[#8A918E]">{c.inquilino}</p>
                                        </div>
                                        <span className="font-mono text-[#1E2D30]">{formataMoeda(c.total_estimado)}</span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-[#8A918E]">Todos os contratos já possuem cobrança neste mês.</p>
                        )}
                        {geracaoPreview.length > 0 && (
                            <p className="text-xs text-[#6B7370]">
                                Serão geradas {geracaoPreview.length} cobrança(s) no valor total de {formataMoeda(geracaoPreview.reduce((a: number, c: any) => a + c.total_estimado, 0))}
                            </p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setGeracaoOpen(false)} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button onClick={handleGerarMensais} disabled={geracaoSaving || geracaoPreview.length === 0} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                            {geracaoSaving && <Spinner />}
                            Gerar {geracaoPreview.length} cobrança(s)
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
