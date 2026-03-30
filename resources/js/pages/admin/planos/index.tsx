import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { InputMoeda } from '@/components/input-moeda';
import { formataMoeda } from '@/lib/utils';

type PlanoData = {
    id: number; nome: string; descricao: string | null; limite_imoveis: number;
    valor_mensal: string; status: string; ordem: number; tenants_count: number;
};

type Props = { planos: PlanoData[] };

export default function PlanosIndex({ planos }: Props) {
    const { flash, errors } = usePage().props as any;
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editando, setEditando] = useState<PlanoData | null>(null);
    const [nome, setNome] = useState(''); const [descricao, setDescricao] = useState('');
    const [limiteImoveis, setLimiteImoveis] = useState<number | null>(null);
    const [ilimitado, setIlimitado] = useState(false);
    const [valorMensal, setValorMensal] = useState<number | null>(null);
    const [ordem, setOrdem] = useState<number>(0);
    const [saving, setSaving] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<PlanoData | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);

    useEffect(() => { if (flash?.success) toast.success(flash.success); }, [flash?.success]);

    function abrirCriar() {
        setEditando(null); setNome(''); setDescricao(''); setLimiteImoveis(null); setIlimitado(false); setValorMensal(null); setOrdem(0);
        setDialogOpen(true);
    }

    function abrirEditar(p: PlanoData) {
        setEditando(p); setNome(p.nome); setDescricao(p.descricao ?? '');
        setLimiteImoveis(p.limite_imoveis === 0 ? null : p.limite_imoveis);
        setIlimitado(p.limite_imoveis === 0); setValorMensal(parseFloat(p.valor_mensal)); setOrdem(p.ordem);
        setDialogOpen(true);
    }

    function handleSalvar() {
        setSaving(true);
        const dados = { nome, descricao: descricao || null, limite_imoveis: ilimitado ? 0 : (limiteImoveis ?? 0), valor_mensal: valorMensal, ordem };
        if (editando) {
            router.put(`/admin/planos/${editando.id}`, dados, { onFinish: () => { setSaving(false); setDialogOpen(false); }, onError: () => setSaving(false) });
        } else {
            router.post('/admin/planos', dados, { onFinish: () => { setSaving(false); setDialogOpen(false); }, onError: () => setSaving(false) });
        }
    }

    function handleToggleStatus(p: PlanoData) {
        router.patch(`/admin/planos/${p.id}/toggle-status`);
    }

    function handleDelete() {
        if (!deleteTarget) return;
        setDeleteLoading(true);
        router.delete(`/admin/planos/${deleteTarget.id}`, { onFinish: () => { setDeleteLoading(false); setDeleteTarget(null); }, onError: () => { setDeleteLoading(false); toast.error('Não é possível excluir plano com assinantes.'); setDeleteTarget(null); } });
    }

    return (
        <>
            <Head title="Admin — Planos" />
            <div className="space-y-4">
                <PageHeader titulo="Planos">
                    <Button className="bg-[#0A4F5C] text-white hover:bg-[#073B45]" size="sm" onClick={abrirCriar}>
                        <Plus className="h-4 w-4" />Novo plano
                    </Button>
                </PageHeader>

                <div className="grid gap-4 sm:grid-cols-2">
                    {planos.map((p) => (
                        <div key={p.id} className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <div className="mb-3 flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <h3 className="text-base font-medium text-[#1E2D30]">{p.nome}</h3>
                                    <Badge variant="secondary" className={p.status === 'ativo' ? 'bg-[#E7F7ED] text-[#1B6B3A]' : 'bg-[#F7F8F7] text-[#6B7370]'}>
                                        {p.status === 'ativo' ? 'Ativo' : 'Inativo'}
                                    </Badge>
                                </div>
                                <div className="flex gap-1">
                                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => abrirEditar(p)}><Pencil className="h-3.5 w-3.5 text-[#6B7370]" /></Button>
                                    {p.tenants_count === 0 && (
                                        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setDeleteTarget(p)}><Trash2 className="h-3.5 w-3.5 text-[#A83232]" /></Button>
                                    )}
                                </div>
                            </div>
                            <p className="font-mono text-2xl font-medium text-[#0A4F5C]">{formataMoeda(p.valor_mensal)}<span className="text-xs text-[#8A918E]">/mês</span></p>
                            <p className="mt-2 text-sm text-[#6B7370]">{p.limite_imoveis === 0 ? 'Imóveis ilimitados' : `Até ${p.limite_imoveis} imóveis`}</p>
                            <p className="mt-1 text-xs text-[#8A918E]">{p.tenants_count} assinante(s)</p>
                            {p.descricao && <p className="mt-2 line-clamp-2 text-xs text-[#8A918E]">{p.descricao}</p>}
                            <div className="mt-3">
                                <Button variant="outline" size="sm" onClick={() => handleToggleStatus(p)} className="border-[#D8DCDA] text-xs">
                                    {p.status === 'ativo' ? 'Desativar' : 'Reativar'}
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader><DialogTitle>{editando ? 'Editar plano' : 'Novo plano'}</DialogTitle></DialogHeader>
                    <div className="space-y-3">
                        <div><Label>Nome</Label><Input value={nome} onChange={(e) => setNome(e.target.value)} className="bg-white border-[#D8DCDA]" /></div>
                        <div><Label>Descrição</Label><textarea value={descricao} onChange={(e) => setDescricao(e.target.value)} rows={2} className="w-full rounded-md border border-[#D8DCDA] bg-white px-3 py-2 text-sm" /></div>
                        <div>
                            <Label>Limite de imóveis</Label>
                            <div className="flex items-center gap-3">
                                <Input type="number" min={1} value={ilimitado ? '' : (limiteImoveis ?? '')} onChange={(e) => setLimiteImoveis(e.target.value ? parseInt(e.target.value) : null)} disabled={ilimitado} className="bg-white border-[#D8DCDA]" />
                                <div className="flex items-center gap-1.5"><Checkbox checked={ilimitado} onCheckedChange={(c) => setIlimitado(!!c)} /><span className="text-xs text-[#6B7370]">Ilimitado</span></div>
                            </div>
                        </div>
                        <div><Label>Valor mensal</Label><InputMoeda value={valorMensal} onChange={setValorMensal} /></div>
                        <div><Label>Ordem</Label><Input type="number" min={0} value={ordem} onChange={(e) => setOrdem(parseInt(e.target.value) || 0)} className="bg-white border-[#D8DCDA]" /><p className="mt-1 text-[10px] text-[#8A918E]">Menor número aparece primeiro</p></div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDialogOpen(false)} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button onClick={handleSalvar} disabled={saving || !nome || !valorMensal} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">{saving && <Spinner />}{editando ? 'Salvar' : 'Criar'}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog open={!!deleteTarget} onOpenChange={(o) => !o && setDeleteTarget(null)}
                titulo="Excluir plano" descricao={`Excluir o plano "${deleteTarget?.nome}"? Esta ação não pode ser desfeita.`}
                textoConfirmar="Excluir" variante="destructive" loading={deleteLoading} onConfirm={handleDelete} />
        </>
    );
}
