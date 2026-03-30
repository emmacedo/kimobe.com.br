import { ClipboardList, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import { InputMoeda } from '@/components/input-moeda';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formataMoeda } from '@/lib/utils';
import type { ContratoResponsabilidade } from '@/types/models';

type Props = {
    contratoId: number;
    responsabilidades: ContratoResponsabilidade[];
};

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

// Itens pré-definidos do sistema
const PREDEFINIDOS = [
    { descricao: 'IPTU', periodicidade: 'anual' as const },
    { descricao: 'Condomínio', periodicidade: 'mensal' as const },
    { descricao: 'Taxa extra de condomínio', periodicidade: 'avulso' as const },
    { descricao: 'Seguro incêndio', periodicidade: 'anual' as const },
    { descricao: 'Taxa dos Bombeiros', periodicidade: 'anual' as const },
];

type FormData = {
    descricao: string;
    responsavel: string;
    valor: number | null;
    periodicidade: string;
    observacoes: string;
};

const formInicial: FormData = {
    descricao: '',
    responsavel: 'inquilino',
    valor: null,
    periodicidade: 'mensal',
    observacoes: '',
};

export function GerenciadorResponsabilidades({ contratoId, responsabilidades: respsIniciais }: Props) {
    const [resps, setResps] = useState<ContratoResponsabilidade[]>(respsIniciais);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editando, setEditando] = useState<ContratoResponsabilidade | null>(null);
    const [form, setForm] = useState<FormData>(formInicial);
    const [saving, setSaving] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<ContratoResponsabilidade | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);
    const [batchDialog, setBatchDialog] = useState(false);
    const [batchItems, setBatchItems] = useState<Array<{ descricao: string; periodicidade: string; responsavel: string; valor: number | null; checked: boolean }>>([]);
    const [batchSaving, setBatchSaving] = useState(false);

    // Itens pré-definidos que ainda não existem
    const predefinidosFaltantes = PREDEFINIDOS.filter(
        (p) => !resps.some((r) => r.descricao.toLowerCase() === p.descricao.toLowerCase() && r.predefinido),
    );

    // Resumo financeiro: soma mensal por responsável
    const somaInquilino = resps
        .filter((r) => r.responsavel === 'inquilino')
        .reduce((acc, r) => {
            const v = r.valor ? parseFloat(r.valor) : 0;
            return acc + (r.periodicidade === 'anual' ? v / 12 : r.periodicidade === 'mensal' ? v : 0);
        }, 0);

    const somaProprietario = resps
        .filter((r) => r.responsavel === 'proprietario')
        .reduce((acc, r) => {
            const v = r.valor ? parseFloat(r.valor) : 0;
            return acc + (r.periodicidade === 'anual' ? v / 12 : r.periodicidade === 'mensal' ? v : 0);
        }, 0);

    // Ordenar: pré-definidos primeiro, depois customizados, ambos alfabéticos
    const respsOrdenadas = [...resps].sort((a, b) => {
        if (a.predefinido !== b.predefinido) return a.predefinido ? -1 : 1;
        return a.descricao.localeCompare(b.descricao);
    });

    function abrirAdicionar() {
        setEditando(null);
        setForm(formInicial);
        setDialogOpen(true);
    }

    function abrirEditar(resp: ContratoResponsabilidade) {
        setEditando(resp);
        setForm({
            descricao: resp.descricao,
            responsavel: resp.responsavel,
            valor: resp.valor ? parseFloat(resp.valor) : null,
            periodicidade: resp.periodicidade,
            observacoes: resp.observacoes ?? '',
        });
        setDialogOpen(true);
    }

    async function handleSalvar() {
        setSaving(true);
        try {
            const url = editando
                ? `/contratos/${contratoId}/responsabilidades/${editando.id}`
                : `/contratos/${contratoId}/responsabilidades`;

            const response = await fetch(url, {
                method: editando ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
                body: JSON.stringify({ ...form, predefinido: false }),
            });

            if (!response.ok) {
                const data = await response.json();
                toast.error(data.message || 'Erro ao salvar.');
                return;
            }

            const resp: ContratoResponsabilidade = await response.json();
            if (editando) {
                setResps((prev) => prev.map((r) => (r.id === resp.id ? resp : r)));
                toast.success('Responsabilidade atualizada.');
            } else {
                setResps((prev) => [...prev, resp]);
                toast.success('Responsabilidade adicionada.');
            }
            setDialogOpen(false);
        } catch {
            toast.error('Erro ao salvar.');
        } finally {
            setSaving(false);
        }
    }

    async function handleExcluir() {
        if (!deleteTarget) return;
        setDeleteLoading(true);
        try {
            await fetch(`/contratos/${contratoId}/responsabilidades/${deleteTarget.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
            });
            setResps((prev) => prev.filter((r) => r.id !== deleteTarget.id));
            toast.success('Responsabilidade removida.');
        } catch {
            toast.error('Erro ao remover.');
        } finally {
            setDeleteLoading(false);
            setDeleteTarget(null);
        }
    }

    function abrirBatch() {
        setBatchItems(
            predefinidosFaltantes.map((p) => ({
                descricao: p.descricao,
                periodicidade: p.periodicidade,
                responsavel: 'inquilino',
                valor: null,
                checked: true,
            })),
        );
        setBatchDialog(true);
    }

    async function handleBatchSalvar() {
        const selecionados = batchItems.filter((i) => i.checked);
        if (selecionados.length === 0) {
            toast.error('Selecione ao menos um item.');
            return;
        }
        setBatchSaving(true);
        try {
            const response = await fetch(`/contratos/${contratoId}/responsabilidades`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
                body: JSON.stringify({
                    itens: selecionados.map((i) => ({
                        descricao: i.descricao,
                        responsavel: i.responsavel,
                        valor: i.valor,
                        periodicidade: i.periodicidade,
                        predefinido: true,
                    })),
                }),
            });
            if (!response.ok) throw new Error();
            const criados: ContratoResponsabilidade[] = await response.json();
            setResps((prev) => [...prev, ...criados]);
            toast.success(`${criados.length} responsabilidade(s) adicionada(s).`);
            setBatchDialog(false);
        } catch {
            toast.error('Erro ao adicionar itens.');
        } finally {
            setBatchSaving(false);
        }
    }

    return (
        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-sm font-medium text-[#1E2D30]">Responsabilidades financeiras</h2>
                <div className="flex gap-2">
                    {predefinidosFaltantes.length > 0 && (
                        <Button variant="outline" size="sm" onClick={abrirBatch} className="border-[#D8DCDA] text-xs">
                            Adicionar pré-definidos
                        </Button>
                    )}
                    <Button variant="outline" size="sm" onClick={abrirAdicionar} className="border-[#D8DCDA]">
                        <Plus className="mr-1 h-3.5 w-3.5" />
                        Adicionar
                    </Button>
                </div>
            </div>

            {resps.length === 0 ? (
                <div className="flex flex-col items-center py-6 text-center">
                    <ClipboardList className="mb-2 h-8 w-8 text-[#8A918E]" />
                    <p className="text-sm text-[#8A918E]">Nenhuma responsabilidade definida</p>
                    <p className="mb-4 text-xs text-[#8A918E]">Defina quem é responsável por cada despesa do imóvel.</p>
                    {predefinidosFaltantes.length > 0 && (
                        <Button variant="outline" size="sm" onClick={abrirBatch} className="border-[#D8DCDA]">
                            Adicionar itens pré-definidos
                        </Button>
                    )}
                </div>
            ) : (
                <>
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Item</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Responsável</TableHead>
                                <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Valor</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Periodicidade</TableHead>
                                <TableHead className="w-20" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {respsOrdenadas.map((r) => (
                                <TableRow key={r.id} className="border-b border-[#F7F8F7]">
                                    <TableCell className="text-sm">
                                        {r.descricao}
                                        {r.predefinido && <Badge variant="outline" className="ml-2 text-[9px]">Pré-definido</Badge>}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary" className={r.responsavel === 'inquilino' ? 'bg-[#E8F4F6] text-[#0A4F5C]' : 'bg-[#F7F8F7] text-[#6B7370]'}>
                                            {r.responsavel === 'inquilino' ? 'Inquilino' : 'Proprietário'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-right font-mono text-sm">{r.valor ? formataMoeda(r.valor) : '—'}</TableCell>
                                    <TableCell>
                                        <Badge variant="outline" className={
                                            r.periodicidade === 'anual' ? 'border-[#8DCAD6] text-[#0A4F5C]' :
                                            r.periodicidade === 'avulso' ? 'border-[#E4CC82] text-[#6B5420]' :
                                            ''
                                        }>
                                            {r.periodicidade === 'mensal' ? 'Mensal' : r.periodicidade === 'anual' ? 'Anual' : 'Avulso'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex gap-1">
                                            <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => abrirEditar(r)} aria-label="Editar">
                                                <Pencil className="h-3.5 w-3.5 text-[#6B7370]" />
                                            </Button>
                                            <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setDeleteTarget(r)} aria-label="Remover">
                                                <Trash2 className="h-3.5 w-3.5 text-[#A83232]" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>

                    {/* Resumo financeiro */}
                    <div className="mt-4 rounded-lg bg-[#F7F8F7] p-3">
                        <div className="flex justify-between text-xs">
                            <span className="text-[#3A4240]">Responsabilidades do inquilino (mensal)</span>
                            <span className="font-mono font-medium text-[#1E2D30]">{formataMoeda(somaInquilino)}</span>
                        </div>
                        <div className="mt-1 flex justify-between text-xs">
                            <span className="text-[#3A4240]">Responsabilidades do proprietário (mensal)</span>
                            <span className="font-mono font-medium text-[#1E2D30]">{formataMoeda(somaProprietario)}</span>
                        </div>
                    </div>
                </>
            )}

            {/* Dialog adicionar/editar */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar responsabilidade' : 'Adicionar responsabilidade'}</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label>Descrição</Label>
                            <Input value={form.descricao} onChange={(e) => setForm((p) => ({ ...p, descricao: e.target.value }))} placeholder="Ex: IPTU, Condomínio..." className="bg-white border-[#D8DCDA]" />
                        </div>
                        <div>
                            <Label>Responsável</Label>
                            <div className="mt-1 flex gap-3">
                                {(['inquilino', 'proprietario'] as const).map((r) => (
                                    <button key={r} type="button" onClick={() => setForm((p) => ({ ...p, responsavel: r }))}
                                        className={`rounded-md border px-3 py-1.5 text-xs font-medium transition-all ${form.responsavel === r ? (r === 'inquilino' ? 'border-[#0A4F5C] bg-[#E8F4F6] text-[#0A4F5C]' : 'border-[#6B7370] bg-[#F7F8F7] text-[#3A4240]') : 'border-[#D8DCDA] text-[#8A918E]'}`}>
                                        {r === 'inquilino' ? 'Inquilino' : 'Proprietário'}
                                    </button>
                                ))}
                            </div>
                        </div>
                        <div>
                            <Label>Valor</Label>
                            <InputMoeda value={form.valor} onChange={(v) => setForm((p) => ({ ...p, valor: v }))} placeholder="Deixe vazio se variável" />
                            <p className="mt-1 text-[10px] text-[#8A918E]">Deixe em branco se o valor varia mensalmente.</p>
                        </div>
                        <div>
                            <Label>Periodicidade</Label>
                            <Select value={form.periodicidade} onValueChange={(v) => setForm((p) => ({ ...p, periodicidade: v }))}>
                                <SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="mensal">Mensal</SelectItem>
                                    <SelectItem value="anual">Anual</SelectItem>
                                    <SelectItem value="avulso">Avulso</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>Observações <span className="text-[#8A918E]">(opcional)</span></Label>
                            <textarea value={form.observacoes} onChange={(e) => setForm((p) => ({ ...p, observacoes: e.target.value }))} rows={2} className="w-full rounded-md border border-[#D8DCDA] bg-white px-3 py-2 text-sm focus:border-[#0A4F5C] focus:outline-none focus:ring-1 focus:ring-[#0A4F5C]" />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDialogOpen(false)} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button onClick={handleSalvar} disabled={saving || !form.descricao} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                            {saving && <Spinner />}
                            {editando ? 'Salvar' : 'Adicionar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Dialog batch pré-definidos */}
            <Dialog open={batchDialog} onOpenChange={setBatchDialog}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Adicionar itens pré-definidos</DialogTitle>
                        <DialogDescription>Selecione os itens e defina o responsável e valor de cada um.</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3">
                        {batchItems.map((item, idx) => (
                            <div key={idx} className="flex items-center gap-3 rounded-md border border-[#EEF0EF] p-3">
                                <Checkbox
                                    checked={item.checked}
                                    onCheckedChange={(c) => {
                                        const items = [...batchItems];
                                        items[idx].checked = !!c;
                                        setBatchItems(items);
                                    }}
                                />
                                <div className="flex-1 space-y-2">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium text-[#1E2D30]">{item.descricao}</span>
                                        <Badge variant="outline" className="text-[10px] capitalize">{item.periodicidade}</Badge>
                                    </div>
                                    {item.checked && (
                                        <div className="flex gap-2">
                                            <Select value={item.responsavel} onValueChange={(v) => { const items = [...batchItems]; items[idx].responsavel = v; setBatchItems(items); }}>
                                                <SelectTrigger className="h-8 w-32 bg-white border-[#D8DCDA] text-xs"><SelectValue /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="inquilino">Inquilino</SelectItem>
                                                    <SelectItem value="proprietario">Proprietário</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputMoeda value={item.valor} onChange={(v) => { const items = [...batchItems]; items[idx].valor = v; setBatchItems(items); }} placeholder="Valor" className="h-8 text-xs" />
                                        </div>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setBatchDialog(false)} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button onClick={handleBatchSalvar} disabled={batchSaving} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                            {batchSaving && <Spinner />}
                            Adicionar selecionados
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Dialog excluir */}
            <ConfirmDialog
                open={!!deleteTarget}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                titulo="Remover responsabilidade"
                descricao={deleteTarget ? `Tem certeza que deseja remover "${deleteTarget.descricao}" das responsabilidades do contrato?` : ''}
                textoConfirmar="Remover"
                variante="destructive"
                loading={deleteLoading}
                onConfirm={handleExcluir}
            />
        </div>
    );
}
