import { Pencil, Plus, Receipt, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { InputMoeda } from '@/components/input-moeda';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formataMoeda } from '@/lib/utils';

type ItemExtra = {
    id: number;
    descricao: string;
    valor: string;
    observacoes: string | null;
};

type Props = {
    cobrancaId: number;
    itensExtras: ItemExtra[];
    cobrancaStatus: string;
    readOnly?: boolean;
    onTotalChanged?: () => void;
};

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export function GerenciadorItensExtras({ cobrancaId, itensExtras: itenInicial, cobrancaStatus, readOnly: readOnlyProp, onTotalChanged }: Props) {
    const [itens, setItens] = useState<ItemExtra[]>(itenInicial);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editando, setEditando] = useState<ItemExtra | null>(null);
    const [descricao, setDescricao] = useState('');
    const [valor, setValor] = useState<number | null>(null);
    const [observacoes, setObservacoes] = useState('');
    const [saving, setSaving] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<ItemExtra | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);

    const readOnly = readOnlyProp || ['pago', 'cancelado'].includes(cobrancaStatus);
    const total = itens.reduce((acc, i) => acc + parseFloat(i.valor), 0);

    function abrirAdicionar() {
        setEditando(null);
        setDescricao('');
        setValor(null);
        setObservacoes('');
        setDialogOpen(true);
    }

    function abrirEditar(item: ItemExtra) {
        setEditando(item);
        setDescricao(item.descricao);
        setValor(parseFloat(item.valor));
        setObservacoes(item.observacoes ?? '');
        setDialogOpen(true);
    }

    async function handleSalvar() {
        if (!descricao || !valor) { toast.error('Preencha descrição e valor.'); return; }
        setSaving(true);
        try {
            const url = editando
                ? `/financeiro/cobrancas/${cobrancaId}/itens-extras/${editando.id}`
                : `/financeiro/cobrancas/${cobrancaId}/itens-extras`;
            const response = await fetch(url, {
                method: editando ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
                body: JSON.stringify({ descricao, valor, observacoes: observacoes || null }),
            });
            if (!response.ok) { const d = await response.json(); toast.error(d.message || 'Erro.'); return; }
            const data = await response.json();
            if (editando) {
                setItens((prev) => prev.map((i) => (i.id === data.item.id ? data.item : i)));
                toast.success('Item atualizado.');
            } else {
                setItens((prev) => [...prev, data.item]);
                toast.success('Item adicionado.');
            }
            setDialogOpen(false);
            onTotalChanged?.();
        } catch { toast.error('Erro ao salvar.'); } finally { setSaving(false); }
    }

    async function handleExcluir() {
        if (!deleteTarget) return;
        setDeleteLoading(true);
        try {
            await fetch(`/financeiro/cobrancas/${cobrancaId}/itens-extras/${deleteTarget.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
            });
            setItens((prev) => prev.filter((i) => i.id !== deleteTarget.id));
            toast.success('Item removido.');
            onTotalChanged?.();
        } catch { toast.error('Erro ao remover.'); } finally { setDeleteLoading(false); setDeleteTarget(null); }
    }

    if (itens.length === 0 && readOnly) return null;

    return (
        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
            <div className="mb-3 flex items-center justify-between">
                <p className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Itens extras</p>
                {!readOnly && (
                    <Button variant="outline" size="sm" onClick={abrirAdicionar} className="border-[#D8DCDA]">
                        <Plus className="mr-1 h-3.5 w-3.5" />Adicionar
                    </Button>
                )}
            </div>

            {itens.length === 0 ? (
                <p className="text-sm text-[#8A918E]">Nenhum item extra adicionado.</p>
            ) : (
                <div className="space-y-2">
                    {itens.map((item) => (
                        <div key={item.id} className="flex items-center justify-between rounded-md border border-[#EEF0EF] px-3 py-2">
                            <div>
                                <p className="text-sm text-[#3A4240]">{item.descricao}</p>
                                {item.observacoes && <p className="text-[10px] text-[#8A918E]">{item.observacoes}</p>}
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="font-mono text-sm font-medium text-[#1E2D30]">{formataMoeda(item.valor)}</span>
                                {!readOnly && (
                                    <div className="flex gap-1">
                                        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => abrirEditar(item)}><Pencil className="h-3.5 w-3.5 text-[#6B7370]" /></Button>
                                        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setDeleteTarget(item)}><Trash2 className="h-3.5 w-3.5 text-[#A83232]" /></Button>
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                    <div className="flex justify-between pt-1 text-sm font-medium">
                        <span className="text-[#6B7370]">Total itens extras</span>
                        <span className="font-mono text-[#1E2D30]">{formataMoeda(total)}</span>
                    </div>
                </div>
            )}

            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader><DialogTitle>{editando ? 'Editar item' : 'Adicionar item extra'}</DialogTitle></DialogHeader>
                    <div className="space-y-3">
                        <div><Label>Descrição</Label><Input value={descricao} onChange={(e) => setDescricao(e.target.value)} placeholder="Ex: Rateio de pintura" className="bg-white border-[#D8DCDA]" /></div>
                        <div><Label>Valor</Label><InputMoeda value={valor} onChange={setValor} /></div>
                        <div><Label>Observações <span className="text-[#8A918E]">(opcional)</span></Label>
                            <textarea value={observacoes} onChange={(e) => setObservacoes(e.target.value)} rows={2} className="w-full rounded-md border border-[#D8DCDA] bg-white px-3 py-2 text-sm focus:border-[#0A4F5C] focus:outline-none focus:ring-1 focus:ring-[#0A4F5C]" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDialogOpen(false)} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button onClick={handleSalvar} disabled={saving || !descricao || !valor} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                            {saving && <Spinner />}{editando ? 'Salvar' : 'Adicionar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog open={!!deleteTarget} onOpenChange={(open) => !open && setDeleteTarget(null)}
                titulo="Remover item extra" descricao={deleteTarget ? `Remover "${deleteTarget.descricao}"?` : ''}
                textoConfirmar="Remover" variante="destructive" loading={deleteLoading} onConfirm={handleExcluir} />
        </div>
    );
}
