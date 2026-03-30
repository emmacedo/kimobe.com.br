import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle, Clock, Mail, Search, Send, X, XCircle } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

type LogData = { id: number; destinatario_email: string; destinatario_nome: string | null; assunto: string; chave_template: string; status: string; aberto: boolean; aberturas_count: number; enviado_em: string | null; aberto_em: string | null; erro: string | null; variaveis_usadas: any; template: { nome: string; modulo: string } | null; created_at: string };
type PaginatedData = { data: LogData[]; total: number; from: number | null; to: number | null; last_page: number; links: any[] };
type Resumo = { enviados_hoje: number; taxa_abertura: number; falhas_hoje: number; na_fila: number };
type Props = { logs: PaginatedData; resumo: Resumo; filtros: { busca: string; status: string; chave_template: string; aberto: string } };

function formatDateTime(d: string | null) { return d ? new Date(d).toLocaleString('pt-BR') : '—'; }
function getCsrfToken() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''; }

export default function EmailsIndex({ logs, resumo, filtros }: Props) {
    const { flash } = usePage().props as any;
    const [busca, setBusca] = useState(filtros.busca);
    const [detalhe, setDetalhe] = useState<any>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => { if (flash?.success) toast.success(flash.success); }, [flash?.success]);

    function aplicarFiltros(o: Record<string, string | undefined>) {
        const p = { ...filtros, ...o }; Object.keys(p).forEach((k) => !(p as any)[k] && delete (p as any)[k]);
        router.get('/admin/emails', p, { preserveState: true, preserveScroll: true, replace: true });
    }

    const debounceBusca = useCallback((v: string) => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => aplicarFiltros({ busca: v || undefined }), 300);
    }, [filtros]);

    async function verDetalhe(id: number) {
        try {
            const resp = await fetch(`/admin/emails/${id}`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() } });
            setDetalhe(await resp.json());
        } catch { toast.error('Erro.'); }
    }

    async function reenviar(id: number) {
        try {
            await fetch(`/admin/emails/${id}/reenviar`, { method: 'POST', headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' } });
            toast.success('Email reenviado.'); setDetalhe(null); router.reload();
        } catch { toast.error('Erro.'); }
    }

    const cards = [
        { titulo: 'Enviados hoje', valor: resumo.enviados_hoje, cor: '#1B6B3A', icon: Send },
        { titulo: 'Taxa de abertura', valor: `${resumo.taxa_abertura}%`, cor: '#0A4F5C', icon: Mail },
        { titulo: 'Falhas hoje', valor: resumo.falhas_hoje, cor: resumo.falhas_hoje > 0 ? '#A83232' : '#6B7370', icon: XCircle },
        { titulo: 'Na fila', valor: resumo.na_fila, cor: '#8C5A10', icon: Clock },
    ];

    return (
        <>
            <Head title="Admin — Emails enviados" />
            <div className="space-y-4">
                <PageHeader titulo="Emails enviados" />

                <div className="grid gap-3 sm:grid-cols-4">
                    {cards.map((c) => (
                        <div key={c.titulo} className="rounded-[10px] border border-[#D8DCDA] bg-white p-4">
                            <div className="mb-1.5 flex items-center gap-2"><c.icon className="h-3.5 w-3.5" style={{ color: c.cor }} /><p className="text-[10px] font-medium uppercase tracking-wide text-[#6B7370]">{c.titulo}</p></div>
                            <p className="text-xl font-medium" style={{ color: c.cor }}>{c.valor}</p>
                        </div>
                    ))}
                </div>

                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="relative flex-1"><Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A918E]" /><Input placeholder="Buscar por email ou assunto..." value={busca} onChange={(e) => { setBusca(e.target.value); debounceBusca(e.target.value); }} className="pl-9 bg-white border-[#D8DCDA]" /></div>
                    <Select value={filtros.status || 'todos'} onValueChange={(v) => aplicarFiltros({ status: v === 'todos' ? undefined : v })}><SelectTrigger className="w-full sm:w-36 bg-white border-[#D8DCDA]"><SelectValue /></SelectTrigger><SelectContent><SelectItem value="todos">Todos</SelectItem><SelectItem value="enviado">Enviado</SelectItem><SelectItem value="falha">Falha</SelectItem><SelectItem value="pendente">Pendente</SelectItem></SelectContent></Select>
                    {(filtros.busca || filtros.status) && <Button variant="ghost" size="sm" onClick={() => { setBusca(''); aplicarFiltros({ busca: undefined, status: undefined }); }} className="text-[#6B7370]"><X className="mr-1 h-3.5 w-3.5" />Limpar</Button>}
                </div>

                {logs.data.length === 0 ? (
                    <EmptyState icone={Mail} titulo="Nenhum email encontrado" descricao="Os logs de envio aparecerão aqui." />
                ) : (
                    <div className="overflow-hidden rounded-[10px] border border-[#D8DCDA] bg-white">
                        <Table>
                            <TableHeader><TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                                <TableHead className="text-[10px] uppercase text-[#8A918E]">Destinatário</TableHead>
                                <TableHead className="text-[10px] uppercase text-[#8A918E]">Template</TableHead>
                                <TableHead className="text-[10px] uppercase text-[#8A918E]">Assunto</TableHead>
                                <TableHead className="text-[10px] uppercase text-[#8A918E]">Enviado</TableHead>
                                <TableHead className="text-[10px] uppercase text-[#8A918E]">Status</TableHead>
                                <TableHead className="text-[10px] uppercase text-[#8A918E]">Aberto</TableHead>
                            </TableRow></TableHeader>
                            <TableBody>
                                {logs.data.map((l) => (
                                    <TableRow key={l.id} className="cursor-pointer border-b border-[#F7F8F7] hover:bg-[#FAFBFA]" onClick={() => verDetalhe(l.id)}>
                                        <TableCell><p className="text-sm text-[#1E2D30]">{l.destinatario_nome ?? l.destinatario_email}</p><p className="text-[10px] text-[#8A918E]">{l.destinatario_email}</p></TableCell>
                                        <TableCell><span className="text-xs text-[#3A4240]">{l.template?.nome ?? l.chave_template}</span></TableCell>
                                        <TableCell className="max-w-[200px] truncate text-xs text-[#6B7370]">{l.assunto}</TableCell>
                                        <TableCell className="text-[10px] text-[#8A918E]">{formatDateTime(l.enviado_em)}</TableCell>
                                        <TableCell>
                                            <Badge className={l.status === 'enviado' ? 'bg-[#E7F7ED] text-[#1B6B3A]' : l.status === 'falha' ? 'bg-[#FDECEC] text-[#A83232]' : 'bg-[#FFF4E5] text-[#8C5A10]'} variant="secondary">
                                                {l.status === 'enviado' ? 'Enviado' : l.status === 'falha' ? 'Falha' : 'Pendente'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            {l.aberto ? <Badge className="bg-[#E7F7ED] text-[#1B6B3A]" variant="secondary">Sim{l.aberturas_count > 1 ? ` (${l.aberturas_count}×)` : ''}</Badge> : <span className="text-[10px] text-[#8A918E]">Não</span>}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        {logs.last_page > 1 && <div className="flex items-center justify-between border-t border-[#EEF0EF] px-4 py-3"><p className="text-xs text-[#8A918E]">Mostrando {logs.from}–{logs.to} de {logs.total}</p></div>}
                    </div>
                )}
            </div>

            <Dialog open={!!detalhe} onOpenChange={(o) => !o && setDetalhe(null)}>
                <DialogContent className="sm:max-w-lg max-h-[80vh] overflow-y-auto">
                    <DialogHeader><DialogTitle>Detalhes do email</DialogTitle></DialogHeader>
                    {detalhe && (
                        <div className="space-y-3 text-sm">
                            <div className="grid gap-2 sm:grid-cols-2">
                                <div><span className="text-[#8A918E]">Para:</span> {detalhe.destinatario_nome} ({detalhe.destinatario_email})</div>
                                <div><span className="text-[#8A918E]">Template:</span> {detalhe.chave_template}</div>
                                <div><span className="text-[#8A918E]">Status:</span> {detalhe.status}</div>
                                <div><span className="text-[#8A918E]">Enviado:</span> {formatDateTime(detalhe.enviado_em)}</div>
                                {detalhe.aberto && <div><span className="text-[#8A918E]">Aberto:</span> {formatDateTime(detalhe.aberto_em)} ({detalhe.aberturas_count}×)</div>}
                                {detalhe.erro && <div className="sm:col-span-2 rounded bg-[#FDECEC] p-2 text-xs text-[#A83232]">{detalhe.erro}</div>}
                            </div>
                            <div><span className="text-[#8A918E]">Assunto:</span> <strong>{detalhe.assunto}</strong></div>
                            {detalhe.variaveis_usadas && (
                                <details className="text-xs"><summary className="cursor-pointer text-[#8A918E]">Variáveis usadas</summary><pre className="mt-1 rounded bg-[#F7F8F7] p-2 overflow-x-auto">{JSON.stringify(detalhe.variaveis_usadas, null, 2)}</pre></details>
                            )}
                        </div>
                    )}
                    <DialogFooter>
                        {detalhe && <Button variant="outline" size="sm" onClick={() => reenviar(detalhe.id)} className="border-[#D8DCDA]"><Send className="mr-1 h-3 w-3" />Reenviar</Button>}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
