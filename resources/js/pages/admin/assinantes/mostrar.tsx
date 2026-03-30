import { Head, router, usePage } from '@inertiajs/react';
import { Calendar } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formataMoeda } from '@/lib/utils';

function formatDate(d: string) { return new Date(d).toLocaleDateString('pt-BR'); }

type Props = { tenant: any; imoveis_count: number; contratos_ativos: number; faturas: any[]; planos: any[] };

export default function AssinanteMostrar({ tenant, imoveis_count, contratos_ativos, faturas, planos }: Props) {
    const { flash } = usePage().props as any;
    const [planoDialog, setPlanoDialog] = useState(false);
    const [novoPlanoId, setNovoPlanoId] = useState('');
    const [planoSaving, setPlanoSaving] = useState(false);
    const [cortesiaDialog, setCortesiaDialog] = useState(false);
    const [motivoCortesia, setMotivoCortesia] = useState('');
    const [cortesiaSaving, setCortesiaSaving] = useState(false);
    const [cancelDialog, setCancelDialog] = useState(false);
    const [motivoCancel, setMotivoCancel] = useState('');
    const [cancelSaving, setCancelSaving] = useState(false);
    const [suspenderDialog, setSuspenderDialog] = useState(false);

    useEffect(() => { if (flash?.success) toast.success(flash.success); }, [flash?.success]);

    const planoAtual = tenant.plano_assinatura;
    const limite = planoAtual?.limite_imoveis ?? 0;
    const uso = limite > 0 ? Math.round((imoveis_count / limite) * 100) : 0;

    function handleAlterarPlano() {
        setPlanoSaving(true);
        router.patch(`/admin/assinantes/${tenant.id}/plano`, { plano_id: parseInt(novoPlanoId) }, {
            onFinish: () => { setPlanoSaving(false); setPlanoDialog(false); },
        });
    }

    function handleToggleCortesia() {
        setCortesiaSaving(true);
        router.patch(`/admin/assinantes/${tenant.id}/cortesia`, { motivo_cortesia: motivoCortesia || undefined }, {
            onFinish: () => { setCortesiaSaving(false); setCortesiaDialog(false); },
        });
    }

    function handleCancelar() {
        setCancelSaving(true);
        router.patch(`/admin/assinantes/${tenant.id}/cancelar`, { motivo: motivoCancel }, {
            onFinish: () => { setCancelSaving(false); setCancelDialog(false); },
        });
    }

    return (
        <>
            <Head title={`Admin — ${tenant.nome}`} />
            <div className="space-y-4">
                <PageHeader titulo={tenant.nome}>
                    <div className="flex items-center gap-2">
                        <StatusBadge status={tenant.status} tipo="contrato" />
                        <Badge variant="outline">{tenant.tipo === 'imobiliaria' ? 'Imobiliária' : 'Proprietário direto'}</Badge>
                        {tenant.cortesia && <Badge className="bg-[#FBF6E8] text-[#6B5420]">Cortesia</Badge>}
                    </div>
                </PageHeader>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="space-y-4 lg:col-span-2">
                        {/* Dados */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Dados do assinante</p>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div><p className="text-[10px] uppercase text-[#8A918E]">Nome</p><p className="text-sm text-[#1E2D30]">{tenant.nome}</p></div>
                                <div><p className="text-[10px] uppercase text-[#8A918E]">Documento</p><p className="text-sm font-mono text-[#1E2D30]">{tenant.documento}</p></div>
                                <div><p className="text-[10px] uppercase text-[#8A918E]">Cadastrado em</p><p className="text-sm text-[#6B7370]">{formatDate(tenant.created_at)}</p></div>
                                {tenant.cortesia && <div><p className="text-[10px] uppercase text-[#8A918E]">Motivo cortesia</p><p className="text-sm text-[#C9A84C]">{tenant.motivo_cortesia}</p></div>}
                                {tenant.status === 'bloqueado' && <div><p className="text-[10px] uppercase text-[#8A918E]">Bloqueado em</p><p className="text-sm text-[#A83232]">{tenant.bloqueado_em ? formatDate(tenant.bloqueado_em) : '—'}</p></div>}
                            </div>
                        </div>

                        {/* Plano */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <div className="mb-3 flex items-center justify-between">
                                <p className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Plano atual</p>
                                <Button variant="outline" size="sm" onClick={() => { setNovoPlanoId(''); setPlanoDialog(true); }} className="border-[#D8DCDA] text-xs">Alterar plano</Button>
                            </div>
                            <p className="text-lg font-medium text-[#1E2D30]">{planoAtual?.nome ?? 'Sem plano'}</p>
                            {planoAtual && <p className="text-sm text-[#6B7370]">{tenant.cortesia ? <><s>{formataMoeda(planoAtual.valor_mensal)}</s> <Badge className="bg-[#FBF6E8] text-[#6B5420] text-[9px]">Isento</Badge></> : <>{formataMoeda(planoAtual.valor_mensal)}/mês</>}</p>}
                            <p className="mt-2 text-sm text-[#6B7370]">{imoveis_count} de {limite === 0 ? '∞' : limite} imóveis</p>
                            {limite > 0 && (
                                <div className="mt-2 h-2 overflow-hidden rounded-full bg-[#EEF0EF]">
                                    <div className={`h-full rounded-full ${uso >= 100 ? 'bg-[#A83232]' : uso >= 80 ? 'bg-[#C9A84C]' : 'bg-[#1B6B3A]'}`} style={{ width: `${Math.min(uso, 100)}%` }} />
                                </div>
                            )}
                        </div>

                        {/* Vínculos */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Usuários vinculados</p>
                            <Table>
                                <TableHeader><TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Nome</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Email</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Papel</TableHead>
                                    <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Status</TableHead>
                                </TableRow></TableHeader>
                                <TableBody>
                                    {(tenant.vinculos ?? []).map((v: any) => (
                                        <TableRow key={v.id} className="border-b border-[#F7F8F7]">
                                            <TableCell className="text-sm">{v.user.name}</TableCell>
                                            <TableCell className="text-xs text-[#6B7370]">{v.user.email}</TableCell>
                                            <TableCell><Badge variant="secondary" className="text-[10px]">{v.papel}</Badge></TableCell>
                                            <TableCell><Badge variant={v.status === 'ativo' ? 'secondary' : 'outline'} className="text-[10px]">{v.status}</Badge></TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        {/* Faturas */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Últimas faturas</p>
                            {tenant.cortesia ? (
                                <p className="text-sm text-[#C9A84C]">Assinante isento de cobrança (cortesia)</p>
                            ) : faturas.length > 0 ? (
                                <Table>
                                    <TableHeader><TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                                        <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Ref.</TableHead>
                                        <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Valor</TableHead>
                                        <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Vencimento</TableHead>
                                        <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Status</TableHead>
                                    </TableRow></TableHeader>
                                    <TableBody>
                                        {faturas.map((f: any) => (
                                            <TableRow key={f.id} className="border-b border-[#F7F8F7]">
                                                <TableCell className="font-mono text-sm">{f.referencia}</TableCell>
                                                <TableCell className="text-right font-mono text-sm">{formataMoeda(f.valor)}</TableCell>
                                                <TableCell className="text-xs text-[#6B7370]">{formatDate(f.data_vencimento)}</TableCell>
                                                <TableCell><StatusBadge status={f.status} tipo="cobranca" /></TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : <p className="text-sm text-[#8A918E]">Nenhuma fatura gerada.</p>}
                        </div>
                    </div>

                    {/* Lateral */}
                    <div className="space-y-4">
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Estatísticas</p>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between"><span className="text-[#6B7370]">Imóveis</span><span className="font-medium text-[#1E2D30]">{imoveis_count}</span></div>
                                <div className="flex justify-between"><span className="text-[#6B7370]">Contratos ativos</span><span className="font-medium text-[#1E2D30]">{contratos_ativos}</span></div>
                                <div className="flex justify-between"><span className="text-[#6B7370]">Usuários</span><span className="font-medium text-[#1E2D30]">{(tenant.vinculos ?? []).length}</span></div>
                            </div>
                        </div>

                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Ações</p>
                            <div className="space-y-2">
                                <Button variant="outline" size="sm" className="w-full border-[#D8DCDA] text-xs justify-start" onClick={() => { setNovoPlanoId(''); setPlanoDialog(true); }}>Alterar plano</Button>
                                <Button variant="outline" size="sm" className="w-full border-[#D8DCDA] text-xs justify-start" onClick={() => { setMotivoCortesia(''); setCortesiaDialog(true); }}>{tenant.cortesia ? 'Remover cortesia' : 'Marcar como cortesia'}</Button>
                                {tenant.status === 'ativo' && <Button variant="outline" size="sm" className="w-full border-[#D8DCDA] text-xs justify-start text-[#A83232]" onClick={() => setSuspenderDialog(true)}>Suspender</Button>}
                                {['suspenso', 'bloqueado'].includes(tenant.status) && <Button variant="outline" size="sm" className="w-full border-[#D8DCDA] text-xs justify-start text-[#1B6B3A]" onClick={() => router.patch(`/admin/assinantes/${tenant.id}/reativar`)}>Reativar</Button>}
                                {['ativo', 'suspenso'].includes(tenant.status) && <Button variant="outline" size="sm" className="w-full border-[#D8DCDA] text-xs justify-start text-[#A83232]" onClick={() => { setMotivoCancel(''); setCancelDialog(true); }}>Cancelar assinatura</Button>}
                            </div>
                        </div>

                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Informações</p>
                            <div className="space-y-2 text-sm text-[#6B7370]">
                                <div className="flex items-center gap-2"><Calendar className="h-3.5 w-3.5" />Criado em {formatDate(tenant.created_at)}</div>
                                <div className="flex items-center gap-2"><Calendar className="h-3.5 w-3.5" />Atualizado em {formatDate(tenant.updated_at)}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Dialogs */}
            <Dialog open={planoDialog} onOpenChange={setPlanoDialog}>
                <DialogContent className="sm:max-w-md"><DialogHeader><DialogTitle>Alterar plano</DialogTitle></DialogHeader>
                    <Select value={novoPlanoId} onValueChange={setNovoPlanoId}><SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue placeholder="Selecione o plano" /></SelectTrigger>
                        <SelectContent>{planos.filter((p: any) => p.id !== tenant.plano_id).map((p: any) => <SelectItem key={p.id} value={p.id.toString()}>{p.nome} — {formataMoeda(p.valor_mensal)}</SelectItem>)}</SelectContent>
                    </Select>
                    <DialogFooter><Button variant="outline" onClick={() => setPlanoDialog(false)} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button onClick={handleAlterarPlano} disabled={planoSaving || !novoPlanoId} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">{planoSaving && <Spinner />}Alterar</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={cortesiaDialog} onOpenChange={setCortesiaDialog}>
                <DialogContent className="sm:max-w-md"><DialogHeader><DialogTitle>{tenant.cortesia ? 'Remover cortesia' : 'Marcar como cortesia'}</DialogTitle></DialogHeader>
                    {!tenant.cortesia && <div><Label>Motivo da cortesia</Label><Input value={motivoCortesia} onChange={(e) => setMotivoCortesia(e.target.value)} placeholder="Ex: Parceiro fundador" className="bg-white border-[#D8DCDA]" /></div>}
                    {tenant.cortesia && <p className="text-sm text-[#6B7370]">O assinante voltará a ser cobrado normalmente.</p>}
                    <DialogFooter><Button variant="outline" onClick={() => setCortesiaDialog(false)} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button onClick={handleToggleCortesia} disabled={cortesiaSaving || (!tenant.cortesia && !motivoCortesia)} className="bg-[#C9A84C] text-white hover:bg-[#B8993F]">{cortesiaSaving && <Spinner />}{tenant.cortesia ? 'Remover' : 'Aplicar'}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog open={suspenderDialog} onOpenChange={setSuspenderDialog} titulo="Suspender assinante" descricao="O assinante não conseguirá acessar o sistema enquanto estiver suspenso." textoConfirmar="Suspender" onConfirm={() => router.patch(`/admin/assinantes/${tenant.id}/suspender`)} />

            <Dialog open={cancelDialog} onOpenChange={setCancelDialog}>
                <DialogContent className="sm:max-w-md"><DialogHeader><DialogTitle>Cancelar assinatura</DialogTitle></DialogHeader>
                    <div className="rounded-md bg-[#FDECEC] p-3 text-xs text-[#A83232]">Esta ação é irreversível. Todos os dados serão mantidos mas o acesso será permanentemente revogado.</div>
                    <div><Label>Motivo do cancelamento</Label><Input value={motivoCancel} onChange={(e) => setMotivoCancel(e.target.value)} className="bg-white border-[#D8DCDA]" /></div>
                    <DialogFooter><Button variant="outline" onClick={() => setCancelDialog(false)} className="border-[#D8DCDA]">Voltar</Button>
                        <Button onClick={handleCancelar} disabled={cancelSaving || !motivoCancel} className="bg-[#A83232] text-white hover:bg-[#8B2929]">{cancelSaving && <Spinner />}Confirmar cancelamento</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
