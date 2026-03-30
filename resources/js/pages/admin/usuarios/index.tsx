import { Head, router, usePage } from '@inertiajs/react';
import { Search, Users, X } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

type UserData = {
    id: number; name: string; email: string; created_at: string;
    vinculos: Array<{ id: number; papel: string; status: string; tenant: { id: number; nome: string } }>;
};
type PaginatedData = { data: UserData[]; current_page: number; last_page: number; total: number; from: number | null; to: number | null; links: Array<{ url: string | null; label: string; active: boolean }> };
type Props = { usuarios: PaginatedData; filtros: { busca: string; tenant_id: string } };

function formatDate(d: string) { return new Date(d).toLocaleDateString('pt-BR'); }
function getCsrfToken() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''; }

export default function UsuariosIndex({ usuarios, filtros }: Props) {
    const { flash } = usePage().props as any;
    const [busca, setBusca] = useState(filtros.busca);
    const [detalheUser, setDetalheUser] = useState<any>(null);
    const [detalheLoading, setDetalheLoading] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => { if (flash?.success) toast.success(flash.success); }, [flash?.success]);

    const debounceBusca = useCallback((v: string) => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            router.get('/admin/usuarios', { busca: v || undefined }, { preserveState: true, preserveScroll: true, replace: true });
        }, 300);
    }, []);

    async function verDetalhes(userId: number) {
        setDetalheLoading(true);
        try {
            const resp = await fetch(`/admin/usuarios/${userId}`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() } });
            const data = await resp.json();
            setDetalheUser(data);
        } catch { toast.error('Erro ao carregar detalhes.'); }
        finally { setDetalheLoading(false); }
    }

    return (
        <>
            <Head title="Admin — Usuários" />
            <div className="space-y-4">
                <PageHeader titulo="Usuários da plataforma" subtitulo={`${usuarios.total} usuário(s)`} />

                <div className="flex gap-3">
                    <div className="relative flex-1"><Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A918E]" /><Input placeholder="Buscar por nome ou email..." value={busca} onChange={(e) => { setBusca(e.target.value); debounceBusca(e.target.value); }} className="pl-9 bg-white border-[#D8DCDA]" /></div>
                    {filtros.busca && <Button variant="ghost" size="sm" onClick={() => { setBusca(''); router.get('/admin/usuarios', {}, { preserveState: true, replace: true }); }} className="text-[#6B7370]"><X className="mr-1 h-3.5 w-3.5" />Limpar</Button>}
                </div>

                {usuarios.data.length === 0 ? (
                    <EmptyState icone={Users} titulo="Nenhum usuário encontrado" descricao="Ajuste a busca." />
                ) : (
                    <div className="overflow-hidden rounded-[10px] border border-[#D8DCDA] bg-white">
                        <Table>
                            <TableHeader><TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Nome</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Email</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Vínculos</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Cadastro</TableHead>
                            </TableRow></TableHeader>
                            <TableBody>
                                {usuarios.data.map((u) => (
                                    <TableRow key={u.id} className="cursor-pointer border-b border-[#F7F8F7] hover:bg-[#FAFBFA]" onClick={() => verDetalhes(u.id)}>
                                        <TableCell className="text-sm font-medium text-[#1E2D30]">{u.name}</TableCell>
                                        <TableCell className="text-xs text-[#6B7370]">{u.email}</TableCell>
                                        <TableCell>
                                            <div className="flex flex-wrap gap-1">
                                                {u.vinculos.map((v) => (
                                                    <Badge key={v.id} variant="secondary" className="text-[9px]">{v.tenant.nome} — {v.papel}</Badge>
                                                ))}
                                                {u.vinculos.length === 0 && <span className="text-xs text-[#8A918E]">Sem vínculos</span>}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-xs text-[#8A918E]">{formatDate(u.created_at)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        {usuarios.last_page > 1 && (
                            <div className="flex items-center justify-between border-t border-[#EEF0EF] px-4 py-3">
                                <p className="text-xs text-[#8A918E]">Mostrando {usuarios.from}–{usuarios.to} de {usuarios.total}</p>
                                <div className="flex gap-1">{usuarios.links.map((l, i) => { if (i === 0 || i === usuarios.links.length - 1) return <Button key={i} variant="ghost" size="sm" disabled={!l.url} onClick={() => l.url && router.get(l.url, {}, { preserveState: true })} className="text-xs text-[#6B7370]">{i === 0 ? '←' : '→'}</Button>; return <Button key={i} variant={l.active ? 'default' : 'ghost'} size="sm" className={l.active ? 'bg-[#0A4F5C] text-white h-8 w-8' : 'text-[#6B7370] h-8 w-8'} onClick={() => l.url && router.get(l.url, {}, { preserveState: true })} disabled={!l.url}>{l.label}</Button>; })}</div>
                            </div>
                        )}
                    </div>
                )}
            </div>

            {/* Dialog detalhes do usuário */}
            <Dialog open={!!detalheUser} onOpenChange={(o) => !o && setDetalheUser(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader><DialogTitle>Detalhes do usuário</DialogTitle></DialogHeader>
                    {detalheUser && (
                        <div className="space-y-4">
                            <div className="grid gap-2 sm:grid-cols-2">
                                <div><p className="text-[10px] uppercase text-[#8A918E]">Nome</p><p className="text-sm text-[#1E2D30]">{detalheUser.name}</p></div>
                                <div><p className="text-[10px] uppercase text-[#8A918E]">Email</p><p className="text-sm text-[#6B7370]">{detalheUser.email}</p></div>
                            </div>
                            <div>
                                <p className="mb-2 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Vínculos</p>
                                {detalheUser.vinculos?.length > 0 ? (
                                    <div className="space-y-2">
                                        {detalheUser.vinculos.map((v: any) => (
                                            <div key={v.id} className="flex items-center justify-between rounded-md border border-[#EEF0EF] px-3 py-2">
                                                <span className="text-sm text-[#1E2D30]">{v.tenant?.nome ?? '—'}</span>
                                                <div className="flex gap-1">
                                                    <Badge variant="secondary" className="text-[10px]">{v.papel}</Badge>
                                                    <Badge variant={v.status === 'ativo' ? 'secondary' : 'outline'} className="text-[10px]">{v.status}</Badge>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : <p className="text-sm text-[#8A918E]">Sem vínculos.</p>}
                            </div>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
