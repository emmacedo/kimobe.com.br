import { Landmark, Pencil, Plus, Trash2, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DialogCadastrarConta } from '@/components/dialog-cadastrar-conta';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import type { DadosBancarios, Titularidade, Vinculo } from '@/types/models';

type Props = {
    imovelId: number;
    titularidades: Titularidade[];
    proprietariosDisponiveis: Vinculo[];
};

const papelLabels: Record<string, string> = { responsavel: 'Responsável', observador: 'Observador' };
const tipoLabels: Record<string, string> = { pessoa_fisica: 'Pessoa física', empresa: 'Empresa', inventario: 'Inventário' };

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

type FormData = {
    vinculo_id: number | null;
    tipo_titular: string;
    papel: string;
    percentual: string;
    dados_bancarios_id: number | null;
};

const formInicial: FormData = {
    vinculo_id: null,
    tipo_titular: 'pessoa_fisica',
    papel: 'responsavel',
    percentual: '',
    dados_bancarios_id: null,
};

export function GerenciadorTitulares({ imovelId, titularidades: titsIniciais, proprietariosDisponiveis: propsIniciais }: Props) {
    const [titularidades, setTitularidades] = useState<Titularidade[]>(titsIniciais);
    const [proprietarios, setProprietarios] = useState<Vinculo[]>(propsIniciais);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editando, setEditando] = useState<Titularidade | null>(null);
    const [form, setForm] = useState<FormData>(formInicial);
    const [contasVinculo, setContasVinculo] = useState<DadosBancarios[]>([]);
    const [saving, setSaving] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<Titularidade | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);
    const [dialogConta, setDialogConta] = useState(false);

    // Soma dos percentuais atuais
    const somaPercentuais = titularidades.reduce((acc, t) => acc + parseFloat(t.percentual), 0);
    const somaEditada = editando
        ? somaPercentuais - parseFloat(editando.percentual) + (parseFloat(form.percentual) || 0)
        : somaPercentuais + (parseFloat(form.percentual) || 0);
    const disponivel = editando
        ? 100 - somaPercentuais + parseFloat(editando.percentual)
        : 100 - somaPercentuais;

    // Barra de progresso
    const barraCorEstilo = somaPercentuais === 100
        ? 'bg-[#1B6B3A]'
        : somaPercentuais > 100
            ? 'bg-[#A83232]'
            : 'bg-[#C9A84C]';

    function abrirAdicionar() {
        setEditando(null);
        setForm(formInicial);
        setContasVinculo([]);
        setDialogOpen(true);
    }

    function abrirEditar(tit: Titularidade) {
        setEditando(tit);
        setForm({
            vinculo_id: tit.vinculo_id,
            tipo_titular: tit.tipo_titular,
            papel: tit.papel,
            percentual: parseFloat(tit.percentual).toString(),
            dados_bancarios_id: tit.dados_bancarios_id,
        });
        // Carregar contas do vínculo
        carregarContas(tit.vinculo_id);
        setDialogOpen(true);
    }

    async function carregarContas(vinculoId: number) {
        // As contas estão nos dados já carregados nas titularidades existentes
        // ou buscamos a partir do proprietário disponível
        const titExistente = titularidades.find((t) => t.vinculo_id === vinculoId);
        if (titExistente?.dados_bancarios) {
            setContasVinculo([titExistente.dados_bancarios]);
        } else {
            setContasVinculo([]);
        }
        // Nota: idealmente teríamos um endpoint para buscar contas por vínculo
        // Por agora, trabalhamos com as contas que já temos no estado
    }

    function handleVinculoChange(vinculoId: string) {
        const id = parseInt(vinculoId);
        setForm((prev) => ({ ...prev, vinculo_id: id, dados_bancarios_id: null }));
        setContasVinculo([]);
    }

    async function handleSalvar() {
        if (!form.vinculo_id && !editando) {
            toast.error('Selecione o proprietário.');
            return;
        }
        if (!form.percentual || parseFloat(form.percentual) <= 0) {
            toast.error('Informe o percentual de propriedade.');
            return;
        }

        setSaving(true);
        try {
            const url = editando
                ? `/imoveis/${imovelId}/titularidades/${editando.id}`
                : `/imoveis/${imovelId}/titularidades`;
            const method = editando ? 'PUT' : 'POST';

            const body: Record<string, unknown> = {
                tipo_titular: form.tipo_titular,
                papel: form.papel,
                percentual: parseFloat(form.percentual),
                dados_bancarios_id: form.dados_bancarios_id,
            };
            if (!editando) {
                body.vinculo_id = form.vinculo_id;
            }

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify(body),
            });

            if (!response.ok) {
                const data = await response.json();
                toast.error(data.message || 'Erro ao salvar titular.');
                return;
            }

            const titularidade: Titularidade = await response.json();

            if (editando) {
                setTitularidades((prev) => prev.map((t) => (t.id === titularidade.id ? titularidade : t)));
                toast.success('Titular atualizado.');
            } else {
                setTitularidades((prev) => [...prev, titularidade]);
                // Remover da lista de disponíveis
                setProprietarios((prev) => prev.filter((p) => p.id !== form.vinculo_id));
                toast.success('Titular adicionado.');
            }

            setDialogOpen(false);
        } catch {
            toast.error('Erro ao salvar titular.');
        } finally {
            setSaving(false);
        }
    }

    async function handleExcluir() {
        if (!deleteTarget) return;
        setDeleteLoading(true);
        try {
            const response = await fetch(`/imoveis/${imovelId}/titularidades/${deleteTarget.id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                },
            });

            if (!response.ok) {
                const data = await response.json();
                toast.error(data.message || 'Erro ao remover titular.');
                return;
            }

            // Devolver à lista de disponíveis
            setProprietarios((prev) => [...prev, deleteTarget.vinculo]);
            setTitularidades((prev) => prev.filter((t) => t.id !== deleteTarget.id));
            toast.success('Titular removido.');
        } catch {
            toast.error('Erro ao remover titular.');
        } finally {
            setDeleteLoading(false);
            setDeleteTarget(null);
        }
    }

    function handleContaCriada(conta: DadosBancarios) {
        setContasVinculo((prev) => [...prev, conta]);
        setForm((prev) => ({ ...prev, dados_bancarios_id: conta.id }));
        setDialogConta(false);
        toast.success('Conta bancária cadastrada.');
    }

    return (
        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-sm font-medium text-[#1E2D30]">Titulares</h2>
                <Button variant="outline" size="sm" onClick={abrirAdicionar} className="border-[#D8DCDA]">
                    <Plus className="mr-1 h-3.5 w-3.5" />
                    Adicionar titular
                </Button>
            </div>

            {/* Barra de progresso dos percentuais */}
            {titularidades.length > 0 && (
                <div className="mb-4">
                    <div className="mb-1 flex items-center justify-between text-xs">
                        <span className="text-[#6B7370]">Soma dos percentuais</span>
                        <span className={somaPercentuais === 100 ? 'text-[#1B6B3A]' : somaPercentuais > 100 ? 'text-[#A83232]' : 'text-[#8C5A10]'}>
                            {somaPercentuais.toFixed(0)}%
                            {somaPercentuais < 100 && ` — Faltam ${(100 - somaPercentuais).toFixed(0)}%`}
                            {somaPercentuais > 100 && ` — Excede ${(somaPercentuais - 100).toFixed(0)}%`}
                        </span>
                    </div>
                    <div className="h-2 overflow-hidden rounded-full bg-[#EEF0EF]">
                        <div
                            className={`h-full rounded-full transition-all ${barraCorEstilo}`}
                            style={{ width: `${Math.min(somaPercentuais, 100)}%` }}
                        />
                    </div>
                </div>
            )}

            {/* Lista de titulares */}
            {titularidades.length === 0 ? (
                <div className="flex flex-col items-center py-6 text-center">
                    <UserPlus className="mb-2 h-8 w-8 text-[#8A918E]" />
                    <p className="text-sm text-[#8A918E]">Nenhum titular cadastrado</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {titularidades.map((tit) => (
                        <div key={tit.id} className="flex items-center justify-between rounded-lg border border-[#EEF0EF] px-4 py-3">
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <p className="text-sm font-medium text-[#1E2D30]">{tit.vinculo.user.name}</p>
                                    <Badge variant="secondary" className={tit.papel === 'responsavel' ? 'bg-[#E8F4F6] text-[#0A4F5C]' : 'bg-[#F7F8F7] text-[#6B7370]'}>
                                        {papelLabels[tit.papel]}
                                    </Badge>
                                    <Badge variant="outline" className="text-[10px]">
                                        {tipoLabels[tit.tipo_titular]}
                                    </Badge>
                                </div>
                                <p className="mt-0.5 text-xs text-[#8A918E]">
                                    {tit.dados_bancarios ? (
                                        <span className="flex items-center gap-1">
                                            <Landmark className="h-3 w-3" />
                                            {tit.dados_bancarios.banco_nome} — Ag {tit.dados_bancarios.agencia} CC {tit.dados_bancarios.conta}
                                        </span>
                                    ) : (
                                        <span className="text-[#8C5A10]">Sem conta bancária</span>
                                    )}
                                </p>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className="text-lg font-medium text-[#0A4F5C]">
                                    {parseFloat(tit.percentual).toFixed(0)}%
                                </span>
                                <div className="flex items-center gap-1">
                                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => abrirEditar(tit)} aria-label="Editar titular">
                                        <Pencil className="h-3.5 w-3.5 text-[#6B7370]" />
                                    </Button>
                                    <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setDeleteTarget(tit)} aria-label="Remover titular">
                                        <Trash2 className="h-3.5 w-3.5 text-[#A83232]" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Dialog adicionar/editar titular */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar titular' : 'Adicionar titular'}</DialogTitle>
                        <DialogDescription>
                            {editando ? 'Altere os dados do titular.' : 'Selecione um proprietário e defina seu percentual.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        {/* Select proprietário */}
                        <div>
                            <Label>Proprietário</Label>
                            {editando ? (
                                <Input value={editando.vinculo.user.name} disabled className="bg-[#F7F8F7]" />
                            ) : proprietarios.length === 0 ? (
                                <p className="mt-1 rounded-md bg-[#FFF4E5] p-3 text-xs text-[#8C5A10]">
                                    Não há proprietários disponíveis. Cadastre um novo proprietário no sistema primeiro.
                                </p>
                            ) : (
                                <Select
                                    value={form.vinculo_id?.toString() ?? ''}
                                    onValueChange={handleVinculoChange}
                                >
                                    <SelectTrigger className="bg-white border-[#D8DCDA]">
                                        <SelectValue placeholder="Selecione o proprietário" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {proprietarios.map((p) => (
                                            <SelectItem key={p.id} value={p.id.toString()}>
                                                {p.user.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </div>

                        {/* Tipo de titular */}
                        <div>
                            <Label>Tipo de titular</Label>
                            <Select value={form.tipo_titular} onValueChange={(v) => setForm((p) => ({ ...p, tipo_titular: v }))}>
                                <SelectTrigger className="bg-white border-[#D8DCDA]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="pessoa_fisica">Pessoa física</SelectItem>
                                    <SelectItem value="empresa">Empresa</SelectItem>
                                    <SelectItem value="inventario">Inventário</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Papel */}
                        <div>
                            <Label>Papel</Label>
                            <Select value={form.papel} onValueChange={(v) => setForm((p) => ({ ...p, papel: v }))}>
                                <SelectTrigger className="bg-white border-[#D8DCDA]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="responsavel">Responsável</SelectItem>
                                    <SelectItem value="observador">Observador</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        {/* Percentual */}
                        <div>
                            <Label>Percentual de propriedade</Label>
                            <Input
                                type="number"
                                min={0.01}
                                max={100}
                                step={0.01}
                                value={form.percentual}
                                onChange={(e) => setForm((p) => ({ ...p, percentual: e.target.value }))}
                                placeholder="Ex: 50.00"
                                className="bg-white border-[#D8DCDA]"
                            />
                            <p className="mt-1 text-xs text-[#8A918E]">Disponível: {disponivel.toFixed(2)}%</p>
                        </div>

                        {/* Conta bancária */}
                        <div>
                            <Label>Conta bancária para repasse</Label>
                            {contasVinculo.length > 0 ? (
                                <Select
                                    value={form.dados_bancarios_id?.toString() ?? ''}
                                    onValueChange={(v) => setForm((p) => ({ ...p, dados_bancarios_id: v ? parseInt(v) : null }))}
                                >
                                    <SelectTrigger className="bg-white border-[#D8DCDA]">
                                        <SelectValue placeholder="Selecione a conta (opcional)" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {contasVinculo.map((c) => (
                                            <SelectItem key={c.id} value={c.id.toString()}>
                                                {c.apelido} — {c.banco_nome}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            ) : (
                                <p className="mt-1 text-xs text-[#8A918E]">Nenhuma conta cadastrada.</p>
                            )}
                            {(form.vinculo_id || editando) && (
                                <Button
                                    type="button"
                                    variant="link"
                                    size="sm"
                                    className="mt-1 h-auto p-0 text-xs text-[#0A4F5C]"
                                    onClick={() => setDialogConta(true)}
                                >
                                    + Cadastrar conta bancária
                                </Button>
                            )}
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDialogOpen(false)} className="border-[#D8DCDA]">
                            Cancelar
                        </Button>
                        <Button
                            onClick={handleSalvar}
                            disabled={saving || (!editando && !form.vinculo_id)}
                            className="bg-[#0A4F5C] text-white hover:bg-[#073B45]"
                        >
                            {saving && <Spinner />}
                            {editando ? 'Salvar' : 'Adicionar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Sub-dialog cadastrar conta bancária */}
            <DialogCadastrarConta
                open={dialogConta}
                onOpenChange={setDialogConta}
                vinculoId={form.vinculo_id ?? editando?.vinculo_id ?? 0}
                onContaCriada={handleContaCriada}
            />

            {/* Dialog excluir titular */}
            <ConfirmDialog
                open={!!deleteTarget}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                titulo="Remover titular"
                descricao={deleteTarget ? `Tem certeza que deseja remover ${deleteTarget.vinculo.user.name} como titular deste imóvel?` : ''}
                textoConfirmar="Remover"
                variante="destructive"
                loading={deleteLoading}
                onConfirm={handleExcluir}
            />
        </div>
    );
}
