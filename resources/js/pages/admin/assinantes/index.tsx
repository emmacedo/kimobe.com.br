import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, Gift, Lock, MoreHorizontal, Search, X, XCircle } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formataMoeda } from '@/lib/utils';

type Assinante = {
    id: number; nome: string; tipo: string; documento: string; status: string;
    cortesia: boolean; motivo_cortesia: string | null; created_at: string;
    plano_assinatura: { nome: string; limite_imoveis: number } | null;
    vinculos_count: number; imoveis_count: number;
};
type PaginatedData = { data: Assinante[]; current_page: number; last_page: number; total: number; from: number | null; to: number | null; links: Array<{ url: string | null; label: string; active: boolean }> };
type Resumo = { ativos: number; cortesias: number; bloqueados: number; cancelados: number };
type PlanoOpt = { id: number; nome: string };
type Filtros = { busca: string; status: string; plano_id: string; cortesia: string };
type Props = { assinantes: PaginatedData; resumo: Resumo; planos: PlanoOpt[]; filtros: Filtros };

function formatDate(d: string) { return new Date(d).toLocaleDateString('pt-BR'); }

export default function AssinantesIndex({ assinantes, resumo, planos, filtros }: Props) {
    const { flash } = usePage().props as any;
    const [busca, setBusca] = useState(filtros.busca);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);
    const temFiltros = filtros.busca || filtros.status || filtros.plano_id || filtros.cortesia;

    useEffect(() => { if (flash?.success) toast.success(flash.success); }, [flash?.success]);

    function aplicarFiltros(override: Record<string, string | undefined>) {
        const params: Record<string, string | undefined> = { busca: filtros.busca || undefined, status: filtros.status || undefined, plano_id: filtros.plano_id || undefined, cortesia: filtros.cortesia || undefined, ...override };
        Object.keys(params).forEach((k) => !params[k] && delete params[k]);
        router.get('/admin/assinantes', params, { preserveState: true, preserveScroll: true, replace: true });
    }

    const debounceBusca = useCallback((v: string) => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => aplicarFiltros({ busca: v || undefined }), 300);
    }, [filtros]);

    const cards = [
        { titulo: 'Ativos', valor: resumo.ativos, icon: Building2, cor: '#1B6B3A' },
        { titulo: 'Cortesias', valor: resumo.cortesias, icon: Gift, cor: '#C9A84C' },
        { titulo: 'Bloqueados', valor: resumo.bloqueados, icon: Lock, cor: '#A83232' },
        { titulo: 'Cancelados', valor: resumo.cancelados, icon: XCircle, cor: '#6B7370' },
    ];

    return (
        <>
            <Head title="Admin — Assinantes" />
            <div className="space-y-4">
                <PageHeader titulo="Assinantes" subtitulo={`${assinantes.total} assinante(s)`} />

                <div className="grid gap-3 sm:grid-cols-4">
                    {cards.map((c) => (
                        <div key={c.titulo} className="rounded-[10px] border border-[#D8DCDA] bg-white p-4">
                            <div className="mb-1 flex items-center gap-2"><c.icon className="h-3.5 w-3.5" style={{ color: c.cor }} /><p className="text-[10px] font-medium uppercase tracking-wide text-[#6B7370]">{c.titulo}</p></div>
                            <p className="text-xl font-medium" style={{ color: c.cor }}>{c.valor}</p>
                        </div>
                    ))}
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1"><Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A918E]" /><Input placeholder="Buscar por nome ou documento..." value={busca} onChange={(e) => { setBusca(e.target.value); debounceBusca(e.target.value); }} className="pl-9 bg-white border-[#D8DCDA]" /></div>
                    <Select value={filtros.status || 'todos'} onValueChange={(v) => aplicarFiltros({ status: v === 'todos' ? undefined : v })}><SelectTrigger className="w-full sm:w-36 bg-white border-[#D8DCDA]"><SelectValue /></SelectTrigger><SelectContent><SelectItem value="todos">Todos</SelectItem><SelectItem value="ativo">Ativo</SelectItem><SelectItem value="suspenso">Suspenso</SelectItem><SelectItem value="bloqueado">Bloqueado</SelectItem><SelectItem value="cancelado">Cancelado</SelectItem></SelectContent></Select>
                    <Select value={filtros.plano_id || 'todos'} onValueChange={(v) => aplicarFiltros({ plano_id: v === 'todos' ? undefined : v })}><SelectTrigger className="w-full sm:w-40 bg-white border-[#D8DCDA]"><SelectValue placeholder="Plano" /></SelectTrigger><SelectContent><SelectItem value="todos">Todos os planos</SelectItem>{planos.map((p) => <SelectItem key={p.id} value={p.id.toString()}>{p.nome}</SelectItem>)}</SelectContent></Select>
                    {temFiltros && <Button variant="ghost" size="sm" onClick={() => { setBusca(''); aplicarFiltros({ busca: undefined, status: undefined, plano_id: undefined, cortesia: undefined }); }} className="text-[#6B7370]"><X className="mr-1 h-3.5 w-3.5" />Limpar</Button>}
                </div>

                {assinantes.data.length === 0 ? (
                    <EmptyState icone={Building2} titulo="Nenhum assinante encontrado" descricao="Ajuste os filtros ou aguarde novos cadastros." />
                ) : (
                    <div className="overflow-hidden rounded-[10px] border border-[#D8DCDA] bg-white">
                        <Table>
                            <TableHeader><TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Assinante</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Documento</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Plano</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Imóveis</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Status</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Desde</TableHead>
                                <TableHead className="w-10" />
                            </TableRow></TableHeader>
                            <TableBody>
                                {assinantes.data.map((a) => (
                                    <TableRow key={a.id} className="cursor-pointer border-b border-[#F7F8F7] hover:bg-[#FAFBFA]" onClick={() => router.visit(`/admin/assinantes/${a.id}`)}>
                                        <TableCell>
                                            <p className="text-sm font-medium text-[#1E2D30]">{a.nome}</p>
                                            <Badge variant="outline" className="mt-0.5 text-[9px]">{a.tipo === 'imobiliaria' ? 'Imobiliária' : 'Proprietário direto'}</Badge>
                                        </TableCell>
                                        <TableCell className="text-xs text-[#6B7370] font-mono">{a.documento}</TableCell>
                                        <TableCell>
                                            <span className="text-xs text-[#3A4240]">{a.plano_assinatura?.nome ?? '—'}</span>
                                            {a.cortesia && <Badge className="ml-1 bg-[#FBF6E8] text-[#6B5420] text-[9px]">Cortesia</Badge>}
                                        </TableCell>
                                        <TableCell className="text-xs text-[#6B7370]">
                                            {a.imoveis_count} / {a.plano_assinatura?.limite_imoveis === 0 ? '∞' : (a.plano_assinatura?.limite_imoveis ?? '—')}
                                        </TableCell>
                                        <TableCell><StatusBadge status={a.status} tipo="contrato" /></TableCell>
                                        <TableCell className="text-xs text-[#8A918E]">{formatDate(a.created_at)}</TableCell>
                                        <TableCell>
                                            <DropdownMenu><DropdownMenuTrigger asChild><Button variant="ghost" size="icon" className="h-8 w-8" onClick={(e) => e.stopPropagation()}><MoreHorizontal className="h-4 w-4" /></Button></DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild><Link href={`/admin/assinantes/${a.id}`}>Ver detalhes</Link></DropdownMenuItem>
                                                    {['ativo', 'suspenso'].includes(a.status) && (
                                                        <><DropdownMenuSeparator />
                                                        {a.status === 'ativo' && <DropdownMenuItem onClick={(e) => { e.stopPropagation(); router.patch(`/admin/assinantes/${a.id}/suspender`); }}>Suspender</DropdownMenuItem>}
                                                        {a.status === 'suspenso' && <DropdownMenuItem onClick={(e) => { e.stopPropagation(); router.patch(`/admin/assinantes/${a.id}/reativar`); }}>Reativar</DropdownMenuItem>}
                                                        </>
                                                    )}
                                                    {a.status === 'bloqueado' && <DropdownMenuItem onClick={(e) => { e.stopPropagation(); router.patch(`/admin/assinantes/${a.id}/desbloquear`); }}>Desbloquear</DropdownMenuItem>}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        {assinantes.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-[#EEF0EF] px-4 py-3">
                                <p className="text-xs text-[#8A918E]">Mostrando {assinantes.from}–{assinantes.to} de {assinantes.total}</p>
                                <div className="flex gap-1">{assinantes.links.map((l, i) => { if (i === 0 || i === assinantes.links.length - 1) return <Button key={i} variant="ghost" size="sm" disabled={!l.url} onClick={() => l.url && router.get(l.url, {}, { preserveState: true })} className="text-xs text-[#6B7370]">{i === 0 ? '←' : '→'}</Button>; return <Button key={i} variant={l.active ? 'default' : 'ghost'} size="sm" className={l.active ? 'bg-[#0A4F5C] text-white h-8 w-8' : 'text-[#6B7370] h-8 w-8'} onClick={() => l.url && router.get(l.url, {}, { preserveState: true })} disabled={!l.url}>{l.label}</Button>; })}</div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </>
    );
}
