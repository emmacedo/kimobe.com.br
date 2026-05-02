import { Head, Link, router, usePage } from '@inertiajs/react';
import { MoreHorizontal, Plus, Search, UserCircle, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
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
import { formataCpfCnpj, formataTelefone } from '@/lib/utils';

type Row = {
    id: number;
    user_id: number;
    status: 'ativo' | 'inativo' | 'pendente';
    contratos_como_inquilino_count: number;
    user: {
        id: number;
        name: string;
        email: string | null;
        telefone: string | null;
        tipo_pessoa: 'pf' | 'pj';
        documento: string | null;
        email_placeholder?: boolean;
    };
};

type PaginatedData = {
    data: Row[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

type Filtros = {
    busca: string;
    tipo_pessoa: string;
    incluir_inativos: boolean;
};

type Props = {
    inquilinos: PaginatedData;
    filtros: Filtros;
};

export default function InquilinosIndex({ inquilinos, filtros }: Props) {
    const { flash } = usePage().props as any;
    const [busca, setBusca] = useState(filtros.busca);
    const [deleteTarget, setDeleteTarget] = useState<Row | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    const debounceBusca = useCallback((valor: string) => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            router.get('/inquilinos', {
                busca: valor || undefined,
                tipo_pessoa: filtros.tipo_pessoa || undefined,
                incluir_inativos: filtros.incluir_inativos ? '1' : undefined,
            }, { preserveState: true, preserveScroll: true, replace: true });
        }, 300);
    }, [filtros.tipo_pessoa, filtros.incluir_inativos]);

    function handleBuscaChange(valor: string) {
        setBusca(valor);
        debounceBusca(valor);
    }

    function handleFiltro(campo: 'tipo_pessoa' | 'incluir_inativos', valor: string | boolean) {
        const params: Record<string, string | undefined> = {
            busca: filtros.busca || undefined,
            tipo_pessoa: filtros.tipo_pessoa || undefined,
            incluir_inativos: filtros.incluir_inativos ? '1' : undefined,
        };

        if (campo === 'tipo_pessoa') {
            params.tipo_pessoa = valor === 'todos' || !valor ? undefined : (valor as string);
        } else {
            params.incluir_inativos = valor ? '1' : undefined;
        }

        router.get('/inquilinos', params, { preserveState: true, preserveScroll: true, replace: true });
    }

    function limparFiltros() {
        setBusca('');
        router.get('/inquilinos', {}, { preserveState: true, preserveScroll: true, replace: true });
    }

    function handleDelete() {
        if (!deleteTarget) return;
        setDeleteLoading(true);
        router.delete(`/inquilinos/${deleteTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                setDeleteLoading(false);
            },
            onError: (errors) => {
                setDeleteLoading(false);
                if (errors.inquilino) toast.error(errors.inquilino);
                setDeleteTarget(null);
            },
        });
    }

    const temFiltros = filtros.busca || filtros.tipo_pessoa || filtros.incluir_inativos;

    return (
        <>
            <Head title="Inquilinos" />
            <div className="space-y-4">
                <PageHeader
                    titulo="Inquilinos"
                    subtitulo={inquilinos.total > 0 ? `${inquilinos.total} inquilino(s) cadastrado(s)` : undefined}
                >
                    <Button className="bg-[#0A4F5C] text-white hover:bg-[#073B45]" size="sm" asChild>
                        <Link href="/inquilinos/criar">
                            <Plus className="h-4 w-4" />
                            Novo inquilino
                        </Link>
                    </Button>
                </PageHeader>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A918E]" />
                        <Input
                            placeholder="Buscar por nome, CPF/CNPJ ou email..."
                            value={busca}
                            onChange={(e) => handleBuscaChange(e.target.value)}
                            className="pl-9 bg-white border-[#D8DCDA]"
                        />
                    </div>
                    <Select
                        value={filtros.tipo_pessoa || 'todos'}
                        onValueChange={(v) => handleFiltro('tipo_pessoa', v)}
                    >
                        <SelectTrigger className="w-full sm:w-40 bg-white border-[#D8DCDA]">
                            <SelectValue placeholder="Tipo" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="todos">Todos os tipos</SelectItem>
                            <SelectItem value="pf">Pessoa física</SelectItem>
                            <SelectItem value="pj">Pessoa jurídica</SelectItem>
                        </SelectContent>
                    </Select>
                    <label className="flex items-center gap-2 text-sm text-[#6B7370]">
                        <input
                            type="checkbox"
                            checked={filtros.incluir_inativos}
                            onChange={(e) => handleFiltro('incluir_inativos', e.target.checked)}
                            className="h-4 w-4 rounded border-[#D8DCDA] text-[#0A4F5C]"
                        />
                        Incluir inativos
                    </label>
                    {temFiltros && (
                        <Button variant="ghost" size="sm" onClick={limparFiltros} className="text-[#6B7370]">
                            <X className="mr-1 h-3.5 w-3.5" />
                            Limpar
                        </Button>
                    )}
                </div>

                {inquilinos.data.length === 0 ? (
                    temFiltros ? (
                        <EmptyState
                            icone={Search}
                            titulo="Nenhum inquilino encontrado"
                            descricao="Tente ajustar os filtros ou termo de busca."
                            acao={
                                <Button variant="outline" size="sm" onClick={limparFiltros}>
                                    Limpar filtros
                                </Button>
                            }
                        />
                    ) : (
                        <EmptyState
                            icone={UserCircle}
                            titulo="Nenhum inquilino cadastrado"
                            descricao="Cadastre inquilinos para vincular como locatários nos seus contratos."
                            acao={
                                <Button className="bg-[#0A4F5C] text-white hover:bg-[#073B45]" size="sm" asChild>
                                    <Link href="/inquilinos/criar">
                                        <Plus className="mr-1 h-4 w-4" />
                                        Cadastrar primeiro inquilino
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
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Nome</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Tipo</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Documento</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Telefone</TableHead>
                                    <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Contratos</TableHead>
                                    <TableHead className="w-10" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {inquilinos.data.map((row) => (
                                    <TableRow
                                        key={row.id}
                                        className="cursor-pointer border-b border-[#F7F8F7] text-[#3A4240] hover:bg-[#FAFBFA]"
                                        onClick={() => router.visit(`/inquilinos/${row.id}/editar`)}
                                    >
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-[#EEF0EF]">
                                                    <UserCircle className="h-4 w-4 text-[#8A918E]" />
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="flex items-center gap-2 truncate text-sm font-medium text-[#1E2D30]">
                                                        {row.user.name}
                                                        {row.status === 'inativo' && (
                                                            <span className="inline-flex items-center rounded-full bg-[#F7F8F7] px-2 py-0.5 text-[10px] text-[#8A918E]">
                                                                Inativo
                                                            </span>
                                                        )}
                                                    </p>
                                                    {row.user.email && (
                                                        <p className="truncate text-xs text-[#8A918E]">{row.user.email}</p>
                                                    )}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <span className="inline-flex items-center rounded-full bg-[#F7F8F7] px-2.5 py-0.5 text-xs font-medium text-[#3A4240]">
                                                {row.user.tipo_pessoa === 'pj' ? 'PJ' : 'PF'}
                                            </span>
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-[#6B7370]">
                                            {formataCpfCnpj(row.user.documento) || '—'}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-[#6B7370]">
                                            {formataTelefone(row.user.telefone) || '—'}
                                        </TableCell>
                                        <TableCell className="text-right text-sm text-[#6B7370]">
                                            {row.contratos_como_inquilino_count}
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8"
                                                        onClick={(e) => e.stopPropagation()}
                                                        aria-label="Ações"
                                                    >
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={`/inquilinos/${row.id}/editar`}
                                                            onClick={(e) => e.stopPropagation()}
                                                        >
                                                            Editar
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    {row.status === 'ativo' && (
                                                        <>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                className="text-[#A83232] focus:text-[#A83232]"
                                                                onClick={(e) => {
                                                                    e.stopPropagation();
                                                                    setDeleteTarget(row);
                                                                }}
                                                            >
                                                                Inativar
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

                        {inquilinos.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-[#EEF0EF] px-4 py-3">
                                <p className="text-xs text-[#8A918E]">
                                    Mostrando {inquilinos.from}–{inquilinos.to} de {inquilinos.total}
                                </p>
                                <div className="flex items-center gap-1">
                                    {inquilinos.links.map((link, i) => {
                                        if (i === 0 || i === inquilinos.links.length - 1) {
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
                                                className={link.active ? 'h-8 w-8 bg-[#0A4F5C] text-white hover:bg-[#073B45]' : 'h-8 w-8 text-[#6B7370]'}
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

            <ConfirmDialog
                open={!!deleteTarget}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                titulo="Inativar inquilino"
                descricao={
                    deleteTarget ? (
                        <div className="space-y-2">
                            <p>
                                Tem certeza que deseja inativar <strong>{deleteTarget.user.name}</strong>?
                                Ele deixará de aparecer na busca de inquilinos.
                            </p>
                            {deleteTarget.contratos_como_inquilino_count > 0 && (
                                <p className="rounded-md bg-[#FFF4E5] p-3 text-sm text-[#8C5A10]">
                                    Este inquilino está em {deleteTarget.contratos_como_inquilino_count} contrato(s). Encerre os contratos primeiro.
                                </p>
                            )}
                        </div>
                    ) : ''
                }
                textoConfirmar="Inativar"
                variante="destructive"
                loading={deleteLoading}
                onConfirm={handleDelete}
            />
        </>
    );
}
