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

type AdministradoraRow = {
    id: number;
    nome: string;
    cpf_cnpj: string | null;
    telefone: string | null;
    email: string | null;
    cidade: string | null;
    uf: string | null;
    condominios_count: number;
};

type PaginatedData = {
    data: AdministradoraRow[];
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
    administradoras: PaginatedData;
    filtros: { busca: string };
};

export default function AdministradorasIndex({ administradoras, filtros }: Props) {
    const { flash } = usePage().props as any;
    const [busca, setBusca] = useState(filtros.busca);
    const [deleteTarget, setDeleteTarget] = useState<AdministradoraRow | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    const debounceBusca = useCallback((valor: string) => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            router.get(
                '/administradoras',
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
        router.get('/administradoras', {}, { preserveState: true, preserveScroll: true, replace: true });
    }

    function handleDelete() {
        if (!deleteTarget) return;
        setDeleteLoading(true);
        router.delete(`/administradoras/${deleteTarget.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteTarget(null);
                setDeleteLoading(false);
            },
            onError: () => {
                setDeleteLoading(false);
                toast.error('Erro ao excluir administradora.');
                setDeleteTarget(null);
            },
        });
    }

    return (
        <>
            <Head title="Administradoras" />
            <div className="space-y-4">
                <PageHeader
                    titulo="Administradoras"
                    subtitulo={administradoras.total > 0 ? `${administradoras.total} administradora(s) cadastrada(s)` : undefined}
                >
                    <Button className="bg-[#0A4F5C] text-white hover:bg-[#073B45]" size="sm" asChild>
                        <Link href="/administradoras/criar">
                            <Plus className="h-4 w-4" />
                            Nova administradora
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

                {administradoras.data.length === 0 ? (
                    filtros.busca ? (
                        <EmptyState
                            icone={Search}
                            titulo="Nenhuma administradora encontrada"
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
                            titulo="Nenhuma administradora cadastrada"
                            descricao="Cadastre administradoras para vincular aos condomínios dos seus imóveis."
                            acao={
                                <Button className="bg-[#0A4F5C] text-white hover:bg-[#073B45]" size="sm" asChild>
                                    <Link href="/administradoras/criar">
                                        <Plus className="mr-1 h-4 w-4" />
                                        Cadastrar primeira administradora
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
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">CPF/CNPJ</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Telefone</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Cidade</TableHead>
                                    <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Imóveis</TableHead>
                                    <TableHead className="w-10" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {administradoras.data.map((adm) => (
                                    <TableRow
                                        key={adm.id}
                                        className="cursor-pointer border-b border-[#F7F8F7] text-[#3A4240] hover:bg-[#FAFBFA]"
                                        onClick={() => router.visit(`/administradoras/${adm.id}/editar`)}
                                    >
                                        <TableCell>
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-[#EEF0EF]">
                                                    <Building2 className="h-4 w-4 text-[#8A918E]" />
                                                </div>
                                                <div className="min-w-0">
                                                    <p className="truncate text-sm font-medium text-[#1E2D30]">{adm.nome}</p>
                                                    {adm.email && (
                                                        <p className="truncate text-xs text-[#8A918E]">{adm.email}</p>
                                                    )}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-[#6B7370]">
                                            {formataCpfCnpj(adm.cpf_cnpj) || '—'}
                                        </TableCell>
                                        <TableCell className="font-mono text-xs text-[#6B7370]">
                                            {formataTelefone(adm.telefone) || '—'}
                                        </TableCell>
                                        <TableCell className="text-sm text-[#6B7370]">
                                            {adm.cidade ? `${adm.cidade}${adm.uf ? `/${adm.uf}` : ''}` : '—'}
                                        </TableCell>
                                        <TableCell className="text-right text-sm text-[#6B7370]">
                                            {adm.condominios_count}
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
                                                            href={`/administradoras/${adm.id}/editar`}
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
                                                            setDeleteTarget(adm);
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

                        {administradoras.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-[#EEF0EF] px-4 py-3">
                                <p className="text-xs text-[#8A918E]">
                                    Mostrando {administradoras.from}–{administradoras.to} de {administradoras.total}
                                </p>
                                <div className="flex items-center gap-1">
                                    {administradoras.links.map((link, i) => {
                                        if (i === 0 || i === administradoras.links.length - 1) {
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
                titulo="Excluir administradora"
                descricao={
                    deleteTarget ? (
                        <div className="space-y-2">
                            <p>
                                Tem certeza que deseja excluir <strong>{deleteTarget.nome}</strong>?
                            </p>
                            {deleteTarget.condominios_count > 0 && (
                                <p className="rounded-md bg-[#FFF4E5] p-3 text-sm text-[#8C5A10]">
                                    Esta administradora está vinculada a {deleteTarget.condominios_count} imóvel(is).
                                    Os imóveis ficarão sem administradora cadastrada.
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
