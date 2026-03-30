import { Head, Link, router, usePage } from '@inertiajs/react';
import { FileText, MoreHorizontal, Plus, Search, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { usePermissions } from '@/hooks/use-permissions';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
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

type ContratoData = {
    id: number;
    valor_aluguel: string;
    modelo_repasse: string;
    status: string;
    data_inicio: string;
    data_fim: string;
    imovel: {
        id: number;
        logradouro: string;
        numero: string;
        complemento: string | null;
    };
    inquilino: {
        user: { name: string };
    };
};

type PaginatedData = {
    data: ContratoData[];
    current_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

type Filtros = {
    busca: string;
    status: string;
    modelo_repasse: string;
    indice_reajuste: string;
};

type Props = {
    contratos: PaginatedData;
    filtros: Filtros;
};

const modeloLabels: Record<string, string> = {
    por_recebimento: 'Por recebimento',
    garantido: 'Garantido',
};

function enderecoResumo(c: ContratoData): string {
    return c.imovel.complemento || `${c.imovel.logradouro}, ${c.imovel.numero}`;
}

function formatDate(d: string): string {
    return new Date(d).toLocaleDateString('pt-BR');
}

export default function ContratosIndex({ contratos, filtros }: Props) {
    const { flash } = usePage().props as any;
    const { can, isInquilino } = usePermissions();
    const [busca, setBusca] = useState(filtros.busca);
    const [actionTarget, setActionTarget] = useState<{ contrato: ContratoData; tipo: 'encerrar' | 'cancelar' } | null>(null);
    const [actionLoading, setActionLoading] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    const temFiltros = filtros.busca || filtros.status || filtros.modelo_repasse || filtros.indice_reajuste;

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    const debounceBusca = useCallback(
        (valor: string) => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
            debounceRef.current = setTimeout(() => {
                const params: Record<string, string | undefined> = {
                    busca: valor || undefined,
                    status: filtros.status || undefined,
                    modelo_repasse: filtros.modelo_repasse || undefined,
                    indice_reajuste: filtros.indice_reajuste || undefined,
                };
                router.get('/contratos', params, { preserveState: true, preserveScroll: true, replace: true });
            }, 300);
        },
        [filtros.status, filtros.modelo_repasse, filtros.indice_reajuste],
    );

    function handleBuscaChange(valor: string) {
        setBusca(valor);
        debounceBusca(valor);
    }

    function handleFiltro(campo: string, valor: string) {
        const params: Record<string, string | undefined> = {
            busca: filtros.busca || undefined,
            status: filtros.status || undefined,
            modelo_repasse: filtros.modelo_repasse || undefined,
            indice_reajuste: filtros.indice_reajuste || undefined,
        };
        params[campo] = valor === 'todos' ? undefined : valor;
        router.get('/contratos', params, { preserveState: true, preserveScroll: true, replace: true });
    }

    function limparFiltros() {
        setBusca('');
        router.get('/contratos', {}, { preserveState: true, preserveScroll: true, replace: true });
    }

    function handleAction() {
        if (!actionTarget) return;
        setActionLoading(true);
        const rota = actionTarget.tipo === 'encerrar' ? 'encerrar' : 'cancelar';
        router.patch(`/contratos/${actionTarget.contrato.id}/${rota}`, {}, {
            onFinish: () => {
                setActionLoading(false);
                setActionTarget(null);
            },
        });
    }

    return (
        <>
            <Head title="Contratos" />
            <div className="space-y-4">
                <PageHeader
                    titulo={isInquilino ? 'Meus contratos' : 'Contratos'}
                    subtitulo={contratos.total > 0 ? `${contratos.total} contrato(s)` : undefined}
                >
                    {can.manage_contratos && (
                        <Button className="bg-[#0A4F5C] text-white hover:bg-[#073B45]" size="sm" asChild>
                            <Link href="/contratos/criar">
                                <Plus className="h-4 w-4" />
                                Novo contrato
                            </Link>
                        </Button>
                    )}
                </PageHeader>

                {/* Filtros */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A918E]" />
                        <Input
                            placeholder="Buscar por imóvel, inquilino ou proprietário..."
                            value={busca}
                            onChange={(e) => handleBuscaChange(e.target.value)}
                            className="pl-9 bg-white border-[#D8DCDA]"
                        />
                    </div>
                    <Select value={filtros.status || 'todos'} onValueChange={(v) => handleFiltro('status', v)}>
                        <SelectTrigger className="w-full sm:w-36 bg-white border-[#D8DCDA]">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="todos">Todos os status</SelectItem>
                            <SelectItem value="ativo">Ativo</SelectItem>
                            <SelectItem value="encerrado">Encerrado</SelectItem>
                            <SelectItem value="renovacao">Renovação</SelectItem>
                            <SelectItem value="cancelado">Cancelado</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select value={filtros.modelo_repasse || 'todos'} onValueChange={(v) => handleFiltro('modelo_repasse', v)}>
                        <SelectTrigger className="w-full sm:w-44 bg-white border-[#D8DCDA]">
                            <SelectValue placeholder="Modelo" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="todos">Todos os modelos</SelectItem>
                            <SelectItem value="por_recebimento">Por recebimento</SelectItem>
                            <SelectItem value="garantido">Garantido</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select value={filtros.indice_reajuste || 'todos'} onValueChange={(v) => handleFiltro('indice_reajuste', v)}>
                        <SelectTrigger className="w-full sm:w-36 bg-white border-[#D8DCDA]">
                            <SelectValue placeholder="Índice" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="todos">Todos os índices</SelectItem>
                            <SelectItem value="igpm">IGPM</SelectItem>
                            <SelectItem value="ipca">IPCA</SelectItem>
                            <SelectItem value="fixo">Fixo</SelectItem>
                        </SelectContent>
                    </Select>
                    {temFiltros && (
                        <Button variant="ghost" size="sm" onClick={limparFiltros} className="text-[#6B7370]">
                            <X className="mr-1 h-3.5 w-3.5" />
                            Limpar
                        </Button>
                    )}
                </div>

                {/* Conteúdo */}
                {contratos.data.length === 0 ? (
                    temFiltros ? (
                        <EmptyState
                            icone={Search}
                            titulo="Nenhum contrato encontrado"
                            descricao="Tente ajustar os filtros ou termo de busca."
                            acao={<Button variant="outline" size="sm" onClick={limparFiltros}>Limpar filtros</Button>}
                        />
                    ) : (
                        <EmptyState
                            icone={FileText}
                            titulo="Nenhum contrato cadastrado"
                            descricao="Crie um contrato para começar a gerenciar aluguéis."
                            acao={
                                <Button className="bg-[#0A4F5C] text-white hover:bg-[#073B45]" size="sm">
                                    <Plus className="mr-1 h-4 w-4" />
                                    Criar primeiro contrato
                                </Button>
                            }
                        />
                    )
                ) : (
                    <div className="overflow-hidden rounded-[10px] border border-[#D8DCDA] bg-white">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Imóvel</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Inquilino</TableHead>
                                    <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Aluguel</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Modelo</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Vigência</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Status</TableHead>
                                    <TableHead className="w-10" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {contratos.data.map((c) => (
                                    <TableRow
                                        key={c.id}
                                        className="cursor-pointer border-b border-[#F7F8F7] text-[#3A4240] hover:bg-[#FAFBFA]"
                                        onClick={() => router.visit(`/contratos/${c.id}`)}
                                    >
                                        <TableCell className="max-w-[200px]">
                                            <p className="truncate text-sm font-medium text-[#1E2D30]">{enderecoResumo(c)}</p>
                                        </TableCell>
                                        <TableCell className="text-sm text-[#3A4240]">{c.inquilino.user.name}</TableCell>
                                        <TableCell className="text-right font-mono text-sm font-medium text-[#1E2D30]">{formataMoeda(c.valor_aluguel)}</TableCell>
                                        <TableCell>
                                            <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${c.modelo_repasse === 'garantido' ? 'bg-[#FBF6E8] text-[#6B5420]' : 'bg-[#F7F8F7] text-[#6B7370]'}`}>
                                                {modeloLabels[c.modelo_repasse] ?? c.modelo_repasse}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-xs text-[#6B7370]">
                                            {formatDate(c.data_inicio)} — {formatDate(c.data_fim)}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={c.status} tipo="contrato" />
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="icon" className="h-8 w-8" onClick={(e) => e.stopPropagation()} aria-label="Ações">
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link href={`/contratos/${c.id}`}>Ver detalhes</Link>
                                                    </DropdownMenuItem>
                                                    {can.manage_contratos && (
                                                        <>
                                                            <DropdownMenuItem asChild>
                                                                <Link href={`/contratos/${c.id}/editar`}>Editar</Link>
                                                            </DropdownMenuItem>
                                                            {c.status === 'ativo' && (
                                                                <>
                                                                    <DropdownMenuSeparator />
                                                                    <DropdownMenuItem onClick={(e) => { e.stopPropagation(); setActionTarget({ contrato: c, tipo: 'encerrar' }); }}>
                                                                        Encerrar contrato
                                                                    </DropdownMenuItem>
                                                                    <DropdownMenuItem
                                                                        className="text-[#A83232] focus:text-[#A83232]"
                                                                        onClick={(e) => { e.stopPropagation(); setActionTarget({ contrato: c, tipo: 'cancelar' }); }}
                                                                    >
                                                                        Cancelar contrato
                                                                    </DropdownMenuItem>
                                                                </>
                                                            )}
                                                        </>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {/* Paginação */}
                        {contratos.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-[#EEF0EF] px-4 py-3">
                                <p className="text-xs text-[#8A918E]">Mostrando {contratos.from}–{contratos.to} de {contratos.total}</p>
                                <div className="flex items-center gap-1">
                                    {contratos.links.map((link, i) => {
                                        if (i === 0 || i === contratos.links.length - 1) {
                                            return (
                                                <Button key={i} variant="ghost" size="sm" disabled={!link.url}
                                                    onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                                    className="text-xs text-[#6B7370]">
                                                    {i === 0 ? '← Anterior' : 'Próxima →'}
                                                </Button>
                                            );
                                        }
                                        return (
                                            <Button key={i}
                                                variant={link.active ? 'default' : 'ghost'} size="sm"
                                                className={link.active ? 'bg-[#0A4F5C] text-white hover:bg-[#073B45] h-8 w-8' : 'text-[#6B7370] h-8 w-8'}
                                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                                disabled={!link.url}>
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

            {/* Modais de ação */}
            <ConfirmDialog
                open={actionTarget?.tipo === 'encerrar'}
                onOpenChange={(open) => !open && setActionTarget(null)}
                titulo="Encerrar contrato"
                descricao={actionTarget ? `Tem certeza que deseja encerrar o contrato do imóvel ${enderecoResumo(actionTarget.contrato)}? O status será alterado para "Encerrado" e nenhuma nova cobrança será gerada.` : ''}
                textoConfirmar="Encerrar"
                loading={actionLoading}
                onConfirm={handleAction}
            />
            <ConfirmDialog
                open={actionTarget?.tipo === 'cancelar'}
                onOpenChange={(open) => !open && setActionTarget(null)}
                titulo="Cancelar contrato"
                descricao={actionTarget ? `Tem certeza que deseja cancelar o contrato do imóvel ${enderecoResumo(actionTarget.contrato)}? Esta ação indica rescisão e pode envolver multa rescisória.` : ''}
                textoConfirmar="Confirmar cancelamento"
                variante="destructive"
                loading={actionLoading}
                onConfirm={handleAction}
            />
        </>
    );
}
