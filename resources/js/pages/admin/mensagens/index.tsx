import { Head, router, usePage } from '@inertiajs/react';
import { Mail, MailOpen } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

function formatDate(d: string) { return new Date(d).toLocaleDateString('pt-BR'); }
function getCsrfToken() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''; }

type Mensagem = { id: number; nome: string; email: string; telefone: string | null; assunto: string; mensagem: string; lida: boolean; respondida: boolean; created_at: string };
type PaginatedData = { data: Mensagem[]; total: number; from: number | null; to: number | null; last_page: number; links: any[] };
type Props = { mensagens: PaginatedData; nao_lidas: number };

export default function MensagensIndex({ mensagens, nao_lidas }: Props) {
    const { flash } = usePage().props as any;
    const [detalhe, setDetalhe] = useState<Mensagem | null>(null);
    const [detalheLoading, setDetalheLoading] = useState(false);

    useEffect(() => { if (flash?.success) toast.success(flash.success); }, [flash?.success]);

    async function verMensagem(id: number) {
        setDetalheLoading(true);
        try {
            const resp = await fetch(`/admin/mensagens/${id}`, { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() } });
            setDetalhe(await resp.json());
        } catch { toast.error('Erro.'); } finally { setDetalheLoading(false); }
    }

    return (
        <>
            <Head title="Admin — Mensagens" />
            <div className="space-y-4">
                <PageHeader titulo="Mensagens de contato" subtitulo={nao_lidas > 0 ? `${nao_lidas} não lida(s)` : 'Todas lidas'} />

                {mensagens.data.length === 0 ? (
                    <EmptyState icone={Mail} titulo="Nenhuma mensagem" descricao="As mensagens enviadas pelo formulário de contato aparecerão aqui." />
                ) : (
                    <div className="overflow-hidden rounded-[10px] border border-[#D8DCDA] bg-white">
                        <Table>
                            <TableHeader><TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Nome</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Email</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Assunto</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Data</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Status</TableHead>
                            </TableRow></TableHeader>
                            <TableBody>
                                {mensagens.data.map((m) => (
                                    <TableRow key={m.id} className={`cursor-pointer border-b border-[#F7F8F7] hover:bg-[#FAFBFA] ${!m.lida ? 'bg-[#FBF6E8]/30 font-medium' : ''}`} onClick={() => verMensagem(m.id)}>
                                        <TableCell className="text-sm text-[#1E2D30]">{m.nome}</TableCell>
                                        <TableCell className="text-xs text-[#6B7370]">{m.email}</TableCell>
                                        <TableCell className="text-xs text-[#3A4240]">{m.assunto}</TableCell>
                                        <TableCell className="text-xs text-[#8A918E]">{formatDate(m.created_at)}</TableCell>
                                        <TableCell>
                                            <div className="flex gap-1">
                                                {!m.lida && <Badge className="bg-[#FFF4E5] text-[#8C5A10] text-[9px]">Não lida</Badge>}
                                                {m.respondida && <Badge className="bg-[#E7F7ED] text-[#1B6B3A] text-[9px]">Respondida</Badge>}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}
            </div>

            <Dialog open={!!detalhe} onOpenChange={(o) => !o && setDetalhe(null)}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader><DialogTitle>Mensagem de {detalhe?.nome}</DialogTitle></DialogHeader>
                    {detalhe && (
                        <div className="space-y-3">
                            <div className="grid gap-2 sm:grid-cols-2 text-sm">
                                <div><span className="text-[#8A918E]">Email:</span> <span className="text-[#1E2D30]">{detalhe.email}</span></div>
                                {detalhe.telefone && <div><span className="text-[#8A918E]">Tel:</span> <span className="text-[#1E2D30]">{detalhe.telefone}</span></div>}
                                <div><span className="text-[#8A918E]">Assunto:</span> <span className="text-[#1E2D30]">{detalhe.assunto}</span></div>
                                <div><span className="text-[#8A918E]">Data:</span> <span className="text-[#1E2D30]">{formatDate(detalhe.created_at)}</span></div>
                            </div>
                            <div className="rounded-md bg-[#F7F8F7] p-4 text-sm text-[#3A4240] whitespace-pre-line">{detalhe.mensagem}</div>
                        </div>
                    )}
                    <DialogFooter>
                        {detalhe && !detalhe.lida && <Button variant="outline" onClick={() => { router.patch(`/admin/mensagens/${detalhe.id}/lida`); setDetalhe(null); }} className="border-[#D8DCDA]"><MailOpen className="mr-1 h-3.5 w-3.5" />Marcar como lida</Button>}
                        {detalhe && !detalhe.respondida && <Button onClick={() => { router.patch(`/admin/mensagens/${detalhe.id}/respondida`); setDetalhe(null); }} className="bg-[#1B6B3A] text-white hover:bg-[#155A2F]">Marcar como respondida</Button>}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
