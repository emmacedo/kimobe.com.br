import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, MoreHorizontal, Plus, Search, X } from 'lucide-react';
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

// Labels legíveis para tipos de imóvel
const tipoLabels: Record<string, string> = {
    apartamento: 'Apartamento',
    casa: 'Casa',
    sala: 'Sala',
    loja: 'Loja',
    galpao: 'Galpão',
};

type Titular = {
    id: number;
    vinculo: {
        user: {
            name: string;
        };
    };
};

type FotoPrincipal = {
    id: number;
    caminho: string;
    legenda: string | null;
};

type ImovelData = {
    id: number;
    logradouro: string;
    numero: string;
    complemento: string | null;
    bairro: string;
    cidade: string;
    uf: string;
    tipo: string;
    status: string;
    valor_aluguel_sugerido: string | null;
    titularidades: Titular[];
    foto_principal: FotoPrincipal | null;
};

type PaginatedData = {
    data: ImovelData[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
};

type Filtros = {
    busca: string;
    tipo: string;
    status: string;
};

type Props = {
    imoveis: PaginatedData;
    filtros: Filtros;
    contagens: Record<string, number>;
    pode_adicionar_imovel?: boolean;
};

export default function ImoveisIndex({ imoveis, filtros, contagens, pode_adicionar_imovel = true }: Props) {
    const { flash } = usePage().props as any;
    const { can } = usePermissions();
    const [busca, setBusca] = useState(filtros.busca);
    const [deleteTarget, setDeleteTarget] = useState<ImovelData | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    // Total geral de imóveis (soma de todas as contagens)
    const totalImoveis = Object.values(contagens).reduce((a: number, b: number) => a + b, 0);

    // Verifica se há filtros ativos
    const temFiltros = filtros.busca || filtros.tipo || filtros.status;

    // Toast de sucesso vindo do backend
    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
    }, [flash?.success]);

    // Debounce da busca
    const debounceBusca = useCallback(
        (valor: string) => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
            debounceRef.current = setTimeout(() => {
                router.get(
                    '/imoveis',
                    { busca: valor || undefined, tipo: filtros.tipo || undefined, status: filtros.status || undefined },
                    { preserveState: true, preserveScroll: true, replace: true },
                );
            }, 300);
        },
        [filtros.tipo, filtros.status],
    );

    function handleBuscaChange(valor: string) {
        setBusca(valor);
        debounceBusca(valor);
    }

    function handleFiltro(campo: string, valor: string) {
        const params: Record<string, string | undefined> = {
            busca: filtros.busca || undefined,
            tipo: filtros.tipo || undefined,
            status: filtros.status || undefined,
        };
        params[campo] = valor === 'todos' ? undefined : valor;
        router.get('/imoveis', params, { preserveState: true, preserveScroll: true, replace: true });
    }

    function limparFiltros() {
        setBusca('');
        router.get('/imoveis', {}, { preserveState: true, preserveScroll: true, replace: true });
    }

    function handleDelete() {
        if (!deleteTarget) return;

        // Verificar se tem contratos ativos
        setDeleteLoading(true);
        router.delete(`/imoveis/${deleteTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                setDeleteLoading(false);
            },
            onError: (errors) => {
                setDeleteLoading(false);
                if (errors.imovel) {
                    toast.error(errors.imovel);
                }
                setDeleteTarget(null);
            },
        });
    }

    // Verifica se o imóvel alvo de exclusão tem contratos ativos pelo status "alugado"
    // (heurística do frontend — o backend valida de verdade)
    const deleteTargetAlugado = deleteTarget?.status === 'alugado';

    // Endereço formatado para exibição
    function enderecoFormatado(imovel: ImovelData): string {
        const partes = [imovel.logradouro, imovel.numero];
        return partes.join(', ');
    }

    // Nomes dos titulares
    function nomesTitulares(imovel: ImovelData): string {
        return imovel.titularidades.map((t) => t.vinculo.user.name).join(', ');
    }

    return (
        <>
            <Head title="Imóveis" />
            <div className="space-y-4">
                {/* Header */}
                <PageHeader
                    titulo="Imóveis"
                    subtitulo={totalImoveis > 0 ? `${totalImoveis} imóvel(is) cadastrado(s)` : undefined}
                >
                    {can.manage_imoveis && (
                        pode_adicionar_imovel ? (
                            <Button className="bg-[#0A4F5C] text-white hover:bg-[#073B45]" size="sm" asChild>
                                <Link href="/imoveis/criar">
                                    <Plus className="h-4 w-4" />
                                    Novo imóvel
                                </Link>
                            </Button>
                        ) : (
                            <Button className="bg-[#0A4F5C] text-white" size="sm" disabled title="Limite do plano atingido">
                                <Plus className="h-4 w-4" />
                                Novo imóvel
                            </Button>
                        )
                    )}
                </PageHeader>

                {/* Filtros */}
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A918E]" />
                        <Input
                            placeholder="Buscar por endereço, bairro ou cidade..."
                            value={busca}
                            onChange={(e) => handleBuscaChange(e.target.value)}
                            className="pl-9 bg-white border-[#D8DCDA]"
                        />
                    </div>
                    <Select
                        value={filtros.tipo || 'todos'}
                        onValueChange={(v) => handleFiltro('tipo', v)}
                    >
                        <SelectTrigger className="w-full sm:w-40 bg-white border-[#D8DCDA]">
                            <SelectValue placeholder="Tipo" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="todos">Todos os tipos</SelectItem>
                            <SelectItem value="apartamento">Apartamento</SelectItem>
                            <SelectItem value="casa">Casa</SelectItem>
                            <SelectItem value="sala">Sala</SelectItem>
                            <SelectItem value="loja">Loja</SelectItem>
                            <SelectItem value="galpao">Galpão</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select
                        value={filtros.status || 'todos'}
                        onValueChange={(v) => handleFiltro('status', v)}
                    >
                        <SelectTrigger className="w-full sm:w-40 bg-white border-[#D8DCDA]">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="todos">Todos os status</SelectItem>
                            <SelectItem value="disponivel">Disponível</SelectItem>
                            <SelectItem value="alugado">Alugado</SelectItem>
                            <SelectItem value="manutencao">Manutenção</SelectItem>
                            <SelectItem value="inativo">Inativo</SelectItem>
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
                {imoveis.data.length === 0 ? (
                    temFiltros ? (
                        <EmptyState
                            icone={Search}
                            titulo="Nenhum imóvel encontrado"
                            descricao="Tente ajustar os filtros ou termo de busca."
                            acao={
                                <Button variant="outline" size="sm" onClick={limparFiltros}>
                                    Limpar filtros
                                </Button>
                            }
                        />
                    ) : (
                        <EmptyState
                            icone={Building2}
                            titulo="Nenhum imóvel cadastrado"
                            descricao="Comece cadastrando seu primeiro imóvel para gerenciar aluguéis."
                            acao={
                                <Button className="bg-[#0A4F5C] text-white hover:bg-[#073B45]" size="sm" asChild>
                                    <Link href="/imoveis/criar">
                                        <Plus className="mr-1 h-4 w-4" />
                                        Cadastrar primeiro imóvel
                                    </Link>
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
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Tipo</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Status</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Titulares</TableHead>
                                    <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Valor sugerido</TableHead>
                                    <TableHead className="w-10" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {imoveis.data.map((imovel) => (
                                    <TableRow
                                        key={imovel.id}
                                        className="cursor-pointer border-b border-[#F7F8F7] text-[#3A4240] hover:bg-[#FAFBFA]"
                                        onClick={() => router.visit(`/imoveis/${imovel.id}`)}
                                    >
                                        <TableCell className="max-w-[280px]">
                                            <div className="flex items-center gap-3">
                                                {/* Miniatura da foto */}
                                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-[#EEF0EF]">
                                                    <Building2 className="h-4 w-4 text-[#8A918E]" />
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium text-[#1E2D30]">
                                                        {imovel.complemento || enderecoFormatado(imovel)}
                                                    </p>
                                                    <p className="truncate text-xs text-[#8A918E]">
                                                        {imovel.bairro}, {imovel.cidade}/{imovel.uf}
                                                    </p>
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            <span className="inline-flex items-center rounded-full bg-[#F7F8F7] px-2.5 py-0.5 text-xs font-medium text-[#3A4240]">
                                                {tipoLabels[imovel.tipo] ?? imovel.tipo}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={imovel.status} tipo="imovel" />
                                        </TableCell>
                                        <TableCell className="max-w-[150px] truncate text-sm text-[#6B7370]">
                                            {nomesTitulares(imovel) || '—'}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-sm font-medium text-[#1E2D30]">
                                            {imovel.valor_aluguel_sugerido
                                                ? formataMoeda(imovel.valor_aluguel_sugerido)
                                                : '—'}
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8"
                                                        onClick={(e) => e.stopPropagation()}
                                                        aria-label="Ações do imóvel"
                                                    >
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={`/imoveis/${imovel.id}`}
                                                            onClick={(e) => e.stopPropagation()}
                                                        >
                                                            Ver detalhes
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    {can.manage_imoveis && (
                                                        <>
                                                            <DropdownMenuItem asChild>
                                                                <Link
                                                                    href={`/imoveis/${imovel.id}/editar`}
                                                                    onClick={(e) => e.stopPropagation()}
                                                                >
                                                                    Editar
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                className="text-[#A83232] focus:text-[#A83232]"
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    setDeleteTarget(imovel);
                                                                }}
                                                            >
                                                                Excluir
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

                        {/* Paginação */}
                        {imoveis.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-[#EEF0EF] px-4 py-3">
                                <p className="text-xs text-[#8A918E]">
                                    Mostrando {imoveis.from}–{imoveis.to} de {imoveis.total}
                                </p>
                                <div className="flex items-center gap-1">
                                    {imoveis.links.map((link, i) => {
                                        if (i === 0 || i === imoveis.links.length - 1) {
                                            // Prev/Next
                                            return (
                                                <Button
                                                    key={i}
                                                    variant="ghost"
                                                    size="sm"
                                                    disabled={!link.url}
                                                    onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                                    className="text-xs text-[#6B7370]"
                                                >
                                                    {i === 0 ? '← Anterior' : 'Próxima →'}
                                                </Button>
                                            );
                                        }
                                        return (
                                            <Button
                                                key={i}
                                                variant={link.active ? 'default' : 'ghost'}
                                                size="sm"
                                                className={link.active ? 'bg-[#0A4F5C] text-white hover:bg-[#073B45] h-8 w-8' : 'text-[#6B7370] h-8 w-8'}
                                                onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                                                disabled={!link.url}
                                            >
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

            {/* Modal de exclusão */}
            <ConfirmDialog
                open={!!deleteTarget}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                titulo="Excluir imóvel"
                descricao={
                    deleteTarget ? (
                        <div className="space-y-2">
                            <p>
                                Tem certeza que deseja excluir o imóvel{' '}
                                <strong>{deleteTarget.complemento || enderecoFormatado(deleteTarget)}</strong>?
                                Esta ação não pode ser desfeita.
                            </p>
                            {deleteTargetAlugado && (
                                <p className="rounded-md bg-[#FFF4E5] p-3 text-sm text-[#8C5A10]">
                                    Este imóvel possui contrato(s) ativo(s). Não é possível excluí-lo enquanto houver contratos vinculados.
                                </p>
                            )}
                        </div>
                    ) : ''
                }
                textoConfirmar="Excluir"
                variante="destructive"
                loading={deleteLoading}
                disabled={deleteTargetAlugado}
                onConfirm={handleDelete}
            />
        </>
    );
}

function enderecoFormatado(imovel: ImovelData): string {
    return `${imovel.logradouro}, ${imovel.numero}`;
}
