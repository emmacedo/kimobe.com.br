import { Head, router, usePage } from '@inertiajs/react';
import { Search, Wallet, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import { MonthNavigator } from '@/components/month-navigator';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Input } from '@/components/ui/input';
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

type RepasseLinha = {
    contrato_id: number;
    fatura_id: number | null;
    imovel: string;
    titular: string;
    mes_referencia: string;
    valor_liquido: number;
    status: 'pendente' | 'realizado' | 'cancelado' | 'preview';
    data_prevista: string | null;
    is_preview: boolean;
    qtd_titularidades: number;
};

type Filtros = {
    mes: string;
    busca: string;
};

type Props = {
    linhas: RepasseLinha[];
    filtros: Filtros;
};

export default function RepassesIndex({ linhas, filtros }: Props) {
    const { flash } = usePage().props as any;
    const { isProprietario, isAdmin } = usePermissions();
    const [busca, setBusca] = useState(filtros.busca);
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
        router.get('/financeiro/repasses', params, { preserveState: true, preserveScroll: true, replace: true });
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

    const titulo = isProprietario && !isAdmin ? 'Meus repasses' : 'Repasses';

    return (
        <>
            <Head title="Repasses" />
            <div className="space-y-4">
                <PageHeader titulo={titulo} subtitulo={linhas.length > 0 ? `${linhas.length} contrato(s) ativo(s)` : undefined} />

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <MonthNavigator mes={filtros.mes} onChange={(novoMes) => aplicarFiltros({ mes: novoMes })} />
                    <div className="relative w-full sm:max-w-xs">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A918E]" />
                        <Input
                            placeholder="Buscar por imóvel ou proprietário..."
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
                        icone={Wallet}
                        titulo={filtros.busca ? 'Nenhum contrato encontrado' : 'Nenhum contrato ativo'}
                        descricao={filtros.busca ? 'Tente ajustar a busca.' : 'A lista exibe contratos ativos com repasse ou prévia do mês selecionado.'}
                    />
                ) : (
                    <div className="overflow-hidden rounded-[10px] border border-[#D8DCDA] bg-white">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Proprietário responsável</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Imóvel</TableHead>
                                    <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Líquido</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Previsto</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Status</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {linhas.map((linha) => {
                                    const clicavel = !linha.is_preview && linha.fatura_id !== null;
                                    const extras = linha.qtd_titularidades > 1 ? ` +${linha.qtd_titularidades - 1}` : '';
                                    return (
                                        <TableRow
                                            key={linha.contrato_id}
                                            className={`border-b border-[#F7F8F7] text-[#3A4240] ${clicavel ? 'cursor-pointer hover:bg-[#FAFBFA]' : ''}`}
                                            onClick={() => clicavel && router.visit(`/financeiro/faturas/${linha.fatura_id}`)}
                                        >
                                            <TableCell className="text-sm font-medium text-[#1E2D30]">
                                                {linha.titular}
                                                {extras && <span className="ml-1 text-xs font-normal text-[#8A918E]">{extras}</span>}
                                            </TableCell>
                                            <TableCell className="max-w-[260px] truncate text-sm text-[#6B7370]">{linha.imovel}</TableCell>
                                            <TableCell className={`text-right font-mono text-sm font-medium ${linha.is_preview ? 'text-[#6B7370]' : 'text-[#0A4F5C]'}`}>
                                                {formataMoeda(linha.valor_liquido)}
                                            </TableCell>
                                            <TableCell className="text-xs text-[#6B7370]">{formataData(linha.data_prevista)}</TableCell>
                                            <TableCell>
                                                <StatusBadge status={linha.status} tipo="repasse" />
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>
        </>
    );
}
