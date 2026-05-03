import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, MoreHorizontal, Plus, Search, X } from 'lucide-react';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formataCpfCnpj, formataTelefone } from '@/lib/utils';
import type { TipoEntidadeExterna } from '@/types/models';

type EntidadeRow = {
    id: number;
    nome: string;
    tipo: TipoEntidadeExterna;
    cpf_cnpj: string | null;
    telefone: string | null;
    email: string | null;
    cidade: string | null;
    uf: string | null;
    condominios_count: number;
};

type PaginatedData = {
    data: EntidadeRow[];
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

type Props = {
    entidades: PaginatedData;
    filtros: { busca: string; tipo: string };
};

const TIPO_LABEL: Record<TipoEntidadeExterna, string> = {
    administradora_condominio: 'Administradora',
    sindico: 'Síndico',
    prefeitura: 'Prefeitura',
    seguradora: 'Seguradora',
    prestador_servico: 'Prestador',
    empresa: 'Empresa',
    pessoa_fisica: 'Pessoa física',
    outro: 'Outro',
};

export default function EntidadesExternasIndex({ entidades, filtros }: Props) {
    const { flash } = usePage().props as any;
    const [busca, setBusca] = useState(filtros.busca);
    const [deleteTarget, setDeleteTarget] = useState<EntidadeRow | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    const debounceBusca = useCallback((valor: string) => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            router.get(
                '/entidades-externas',
                { busca: valor || undefined },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);
    }, []);

    function handleBuscaChange(valor: string) {
        setBusca(valor);
        debounceBusca(valor);
    }

    function limparFiltros() {
        setBusca('');
        router.get('/entidades-externas', {}, { preserveState: true, preserveScroll: true, replace: true });
    }

    function handleDelete() {
        if (!deleteTarget) return;
        setDeleteLoading(true);
        router.delete(`/entidades-externas/${deleteTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                setDeleteLoading(false);
            },
            onError: () => {
                setDeleteLoading(false);
                toast.error('Erro ao excluir entidade externa.');
                setDeleteTarget(null);
            },
        });
    }

    return (
        <>
            <Head title="Entidades externas" />
            <div className="space-y-4">
                <PageHeader
                    titulo="Entidades externas"
                    subtitulo={entidades.total > 0 ? `${entidades.total} cadastrada(s)` : undefined}
                >
                    <Button className="bg-[#0A4F5C] text-white hover:bg-[#073B45]" size="sm" asChild>
                        <Link href="/entidades-externas/criar">
                            <Plus className="h-4 w-4" />
                            Nova entidade
                        </Link>
                    </Button>
                </PageHeader>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A918E]" />
                        <Input
                            placeholder="Buscar por nome, CPF/CNPJ ou cidade..."
                            value={busca}
                            onChange={(e) => handleBuscaChange(e.target.value)}
                            className="pl-9 bg-white border-[#D8DCDA]"
                        />
                    </div>
                    {filtros.busca && (
                        <Button variant="ghost" size="sm" onClick={limparFiltros} className="text-[#6B7370]">
                            <X className="mr-1 h-3.5 w-3.5" />
                            Limpar
                        </Button>
                    )}
                </div>

                {entidades.data.length === 0 ? (
                    filtros.busca ? (
                        <EmptyState
                            icone={Search}
                            titulo="Nenhuma entidade encontrada"
                            descricao="Tente ajustar o termo de busca."
                            acao={
                                <Button variant="outline" size="sm" onClick={limparFiltros}>
                                    Limpar busca
                                </Button>
                            }
                        />
                    ) : (
                        <EmptyState
                            icone={Building2}
                            titulo="Nenhuma entidade cadastrada"
                            descricao="Cadastre administradoras de condomínio, síndicos, seguradoras, prefeituras ou prestadores de serviço para vincular às operações."
                            acao={
                                <Button className="bg-[#0A4F5C] text-white hover:bg-[#073B45]" size="sm" asChild>
                                    <Link href="/entidades-externas/criar">
                                        <Plus className="mr-1 h-4 w-4" />
                                        Cadastrar primeira entidade
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
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">CPF/CNPJ</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Telefone</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Cidade</TableHead>
                                    <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Imóveis</TableHead>
                                    <TableHead className="w-10" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {entidades.data.map((ent) => (
                                    <TableRow
                                        key={ent.id}
                                        className="cursor-pointer border-b border-[#F7F8F7] text-[#3A4240] hover:bg-[#FAFBFA]"
                                        onClick={() => router.visit(`/entidades-externas/${ent.id}/editar`)}
                                    >
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-[#EEF0EF]">
                                                    <Building2 className="h-4 w-4 text-[#8A918E]" />
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium text-[#1E2D30]">{ent.nome}</p>
                                                    {ent.email && (
                                                        <p className="truncate text-xs text-[#8A918E]">{ent.email}</p>
                                                    )}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-xs text-[#6B7370]">
                                            {TIPO_LABEL[ent.tipo]}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-[#6B7370]">
                                            {formataCpfCnpj(ent.cpf_cnpj) || '—'}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-[#6B7370]">
                                            {formataTelefone(ent.telefone) || '—'}
                                        </TableCell>
                                        <TableCell className="text-sm text-[#6B7370]">
                                            {ent.cidade ? `${ent.cidade}${ent.uf ? `/${ent.uf}` : ''}` : '—'}
                                        </TableCell>
                                        <TableCell className="text-right text-sm text-[#6B7370]">
                                            {ent.condominios_count}
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
                                                            href={`/entidades-externas/${ent.id}/editar`}
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
                                                            setDeleteTarget(ent);
                                                        }}
                                                    >
                                                        Excluir
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        {entidades.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-[#EEF0EF] px-4 py-3">
                                <p className="text-xs text-[#8A918E]">
                                    Mostrando {entidades.from}–{entidades.to} de {entidades.total}
                                </p>
                                <div className="flex items-center gap-1">
                                    {entidades.links.map((link, i) => {
                                        if (i === 0 || i === entidades.links.length - 1) {
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
                titulo="Excluir entidade externa"
                descricao={
                    deleteTarget ? (
                        <div className="space-y-2">
                            <p>
                                Tem certeza que deseja excluir <strong>{deleteTarget.nome}</strong>?
                            </p>
                            {deleteTarget.condominios_count > 0 && (
                                <p className="rounded-md bg-[#FFF4E5] p-3 text-sm text-[#8C5A10]">
                                    Esta entidade está vinculada a {deleteTarget.condominios_count} imóvel(is).
                                    Os imóveis ficarão sem entidade externa cadastrada.
                                </p>
                            )}
                        </div>
                    ) : ''
                }
                textoConfirmar="Excluir"
                variante="destructive"
                loading={deleteLoading}
                onConfirm={handleDelete}
            />
        </>
    );
}
