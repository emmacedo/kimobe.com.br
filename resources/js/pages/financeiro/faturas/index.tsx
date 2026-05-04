import { Head, Link, router, usePage } from '@inertiajs/react';
import { Loader2, Plus, Receipt, Search, Wand2, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import { MonthNavigator } from '@/components/month-navigator';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { usePermissions } from '@/hooks/use-permissions';
import { formataData, formataMoeda } from '@/lib/utils';

type FaturaLinha = {
    contrato_id: number;
    fatura_id: number | null;
    imovel: string;
    inquilino: string;
    mes_referencia: string;
    valor: number;
    status: 'pendente' | 'pago' | 'atrasado' | 'cancelado' | 'preview';
    data_vencimento: string | null;
    data_pagamento: string | null;
    is_preview: boolean;
};

type Filtros = {
    mes: string;
    busca: string;
};

type Props = {
    linhas: FaturaLinha[];
    filtros: Filtros;
};

export default function FaturasIndex({ linhas, filtros }: Props) {
    const { flash } = usePage().props as any;
    const { can, isInquilino } = usePermissions();
    const [busca, setBusca] = useState(filtros.busca);
    const [geracaoOpen, setGeracaoOpen] = useState(false);
    const [geracaoRef, setGeracaoRef] = useState('');
    const [geracaoPreview, setGeracaoPreview] = useState<any[]>([]);
    const [geracaoLoading, setGeracaoLoading] = useState(false);
    const [geracaoSaving, setGeracaoSaving] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    const aplicarFiltros = useCallback((override: Partial<Filtros>) => {
        const params: Record<string, string | undefined> = {
            mes: filtros.mes,
            busca: filtros.busca || undefined,
            ...override,
        };
        Object.keys(params).forEach((k) => params[k] === undefined && delete params[k]);
        router.get('/financeiro/faturas', params, { preserveState: true, preserveScroll: true, replace: true });
    }, [filtros]);

    function handleBuscaChange(valor: string) {
        setBusca(valor);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            aplicarFiltros({ busca: valor || undefined });
        }, 300);
    }

    function limparBusca() {
        setBusca('');
        aplicarFiltros({ busca: undefined });
    }

    async function abrirGeracao() {
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
            const response = await fetch(`/financeiro/faturas/preview-mensais?referencia=${encodeURIComponent(ref)}`, {
                headers: { Accept: 'application/json', 'X-CSRF-TOKEN': token },
            });
            const data = await response.json();
            setGeracaoPreview(data.contratos ?? []);
        } catch {
            setGeracaoPreview([]);
        } finally {
            setGeracaoLoading(false);
        }
    }

    async function handleGerarMensais() {
        setGeracaoSaving(true);
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const response = await fetch('/financeiro/faturas/gerar-mensais', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                body: JSON.stringify({ referencia: geracaoRef }),
            });
            const data = await response.json();
            toast.success(`${data.quantidade} fatura(s) gerada(s). Total: ${formataMoeda(data.valor_total)}`);
            setGeracaoOpen(false);
            router.reload();
        } catch {
            toast.error('Erro ao gerar faturas.');
        } finally {
            setGeracaoSaving(false);
        }
    }

    return (
        <>
            <Head title="Faturas" />
            <div className="space-y-4">
                <PageHeader titulo={isInquilino ? 'Minhas faturas' : 'Faturas'} subtitulo={linhas.length > 0 ? `${linhas.length} contrato(s) ativo(s)` : undefined}>
                    {can.manage_faturas && (
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" onClick={abrirGeracao} className="border-[#D8DCDA]">
                                <Wand2 className="h-4 w-4" />
                                Gerar do mês
                            </Button>
                            <Button className="bg-[#0A4F5C] text-white hover:bg-[#073B45]" size="sm" asChild>
                                <Link href="/financeiro/faturas/criar">
                                    <Plus className="h-4 w-4" />
                                    Nova fatura
                                </Link>
                            </Button>
                        </div>
                    )}
                </PageHeader>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <MonthNavigator mes={filtros.mes} onChange={(novoMes) => aplicarFiltros({ mes: novoMes })} />
                    <div className="relative w-full sm:max-w-xs">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A918E]" />
                        <Input
                            placeholder="Buscar por imóvel ou inquilino..."
                            value={busca}
                            onChange={(e) => handleBuscaChange(e.target.value)}
                            className="border-[#D8DCDA] bg-white pl-9"
                        />
                        {busca && (
                            <button
                                type="button"
                                onClick={limparBusca}
                                className="absolute right-2 top-1/2 -translate-y-1/2 rounded p-1 text-[#8A918E] hover:bg-[#F7F8F7]"
                                aria-label="Limpar busca"
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>
                </div>

                {linhas.length === 0 ? (
                    <EmptyState
                        icone={Receipt}
                        titulo={filtros.busca ? 'Nenhum contrato encontrado' : 'Nenhum contrato ativo'}
                        descricao={filtros.busca ? 'Tente ajustar a busca.' : 'A lista exibe contratos ativos com fatura ou prévia do mês selecionado.'}
                    />
                ) : (
                    <div className="overflow-hidden rounded-[10px] border border-[#D8DCDA] bg-white">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Inquilino principal</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Imóvel</TableHead>
                                    <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Valor</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Vencimento</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {linhas.map((linha) => {
                                    const clicavel = !linha.is_preview && linha.fatura_id !== null;
                                    return (
                                        <TableRow
                                            key={linha.contrato_id}
                                            className={`border-b border-[#F7F8F7] text-[#3A4240] ${clicavel ? 'cursor-pointer hover:bg-[#FAFBFA]' : ''} ${linha.status === 'atrasado' ? 'bg-[#FDECEC]/30' : ''}`}
                                            onClick={() => clicavel && router.visit(`/financeiro/faturas/${linha.fatura_id}`)}
                                        >
                                            <TableCell className="text-sm font-medium text-[#1E2D30]">{linha.inquilino}</TableCell>
                                            <TableCell className="max-w-[260px] truncate text-sm text-[#6B7370]">{linha.imovel}</TableCell>
                                            <TableCell className={`text-right font-mono text-sm font-medium ${linha.is_preview ? 'text-[#6B7370]' : 'text-[#1E2D30]'}`}>
                                                {formataMoeda(linha.valor)}
                                            </TableCell>
                                            <TableCell className={`text-xs ${linha.status === 'atrasado' ? 'font-medium text-[#A83232]' : 'text-[#6B7370]'}`}>
                                                {formataData(linha.data_vencimento)}
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge status={linha.status} tipo="cobranca" />
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

            {/* Dialog geração automática */}
            <Dialog open={geracaoOpen} onOpenChange={setGeracaoOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Gerar faturas do mês</DialogTitle>
                        <DialogDescription>Selecione o mês de referência para gerar as faturas automaticamente.</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3">
                        <div>
                            <Label>Referência (MM/YYYY)</Label>
                            <Input
                                value={geracaoRef}
                                onChange={(e) => {
                                    setGeracaoRef(e.target.value);
                                    if (e.target.value.length === 7) carregarPreview(e.target.value);
                                }}
                                placeholder="04/2026"
                                maxLength={7}
                                className="border-[#D8DCDA] bg-white"
                            />
                        </div>
                        {geracaoLoading ? (
                            <div className="flex items-center justify-center py-4">
                                <Loader2 className="h-5 w-5 animate-spin text-[#8A918E]" />
                            </div>
                        ) : geracaoPreview.length > 0 ? (
                            <div className="max-h-48 overflow-y-auto rounded-md border border-[#EEF0EF]">
                                {geracaoPreview.map((c: any) => (
                                    <div key={c.id} className="flex items-center justify-between border-b border-[#EEF0EF] px-3 py-2 text-xs last:border-0">
                                        <div>
                                            <p className="font-medium text-[#1E2D30]">{c.imovel}</p>
                                            <p className="text-[#8A918E]">{c.inquilino}</p>
                                        </div>
                                        <span className="font-mono text-[#1E2D30]">{formataMoeda(c.valor_aluguel)}</span>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-[#8A918E]">Todos os contratos já possuem fatura neste mês.</p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setGeracaoOpen(false)} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button onClick={handleGerarMensais} disabled={geracaoSaving || geracaoPreview.length === 0} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                            {geracaoSaving && <Spinner />}
                            Gerar {geracaoPreview.length} fatura(s)
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
