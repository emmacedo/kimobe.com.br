import { Eye, Pencil, Plus, Trash2, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FiadorDetalhesDialog } from '@/components/fiador-detalhes-dialog';
import { InputCep } from '@/components/input-cep';
import { InputCpf } from '@/components/input-cpf';
import InputError from '@/components/input-error';
import { InputTelefone } from '@/components/input-telefone';
import { SelectUf } from '@/components/select-uf';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { Fiador } from '@/types/models';

type Props = {
    contratoId: number;
    fiadores: Fiador[];
    tipoGarantia: string;
};

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

type FiadorForm = Omit<Fiador, 'id' | 'contrato_id'>;

const formInicial: FiadorForm = {
    nome: '', cpf: '', rg: '', telefone: '', email: '', profissao: '', estado_civil: '',
    cep: '', logradouro: '', numero: '', complemento: '', bairro: '', cidade: '', uf: '',
};


export function GerenciadorFiadores({ contratoId, fiadores: fiadoresIniciais, tipoGarantia }: Props) {
    const [fiadores, setFiadores] = useState<Fiador[]>(fiadoresIniciais);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editando, setEditando] = useState<Fiador | null>(null);
    const [form, setForm] = useState<FiadorForm>(formInicial);
    const [saving, setSaving] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<Fiador | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);
    const [detalhesTarget, setDetalhesTarget] = useState<Fiador | null>(null);

    // Só aparece se tipo_garantia é fiador
    if (tipoGarantia !== 'fiador') return null;

    const maxFiadores = 2;
    const podeAdicionar = fiadores.length < maxFiadores;

    function abrirAdicionar() {
        setEditando(null);
        setForm(formInicial);
        setDialogOpen(true);
    }

    function abrirEditar(fiador: Fiador) {
        setEditando(fiador);
        setForm({
            nome: fiador.nome, cpf: fiador.cpf, rg: fiador.rg ?? '', telefone: fiador.telefone,
            email: fiador.email ?? '', profissao: fiador.profissao ?? '', estado_civil: fiador.estado_civil ?? '',
            cep: fiador.cep, logradouro: fiador.logradouro, numero: fiador.numero,
            complemento: fiador.complemento ?? '', bairro: fiador.bairro, cidade: fiador.cidade, uf: fiador.uf,
        });
        setDialogOpen(true);
    }

    function setField<K extends keyof FiadorForm>(key: K, value: FiadorForm[K]) {
        setForm((prev) => ({ ...prev, [key]: value }));
    }

    function handleAddressFound(endereco: { logradouro: string; bairro: string; localidade: string; uf: string }) {
        setForm((prev) => ({
            ...prev,
            logradouro: endereco.logradouro || prev.logradouro,
            bairro: endereco.bairro || prev.bairro,
            cidade: endereco.localidade || prev.cidade,
            uf: endereco.uf || prev.uf,
        }));
    }

    async function handleSalvar() {
        setSaving(true);
        try {
            const url = editando
                ? `/contratos/${contratoId}/fiadores/${editando.id}`
                : `/contratos/${contratoId}/fiadores`;

            const response = await fetch(url, {
                method: editando ? 'PUT' : 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
                body: JSON.stringify({
                    ...form,
                    rg: form.rg || null,
                    email: form.email || null,
                    profissao: form.profissao || null,
                    estado_civil: form.estado_civil || null,
                    complemento: form.complemento || null,
                }),
            });

            if (!response.ok) {
                const data = await response.json();
                toast.error(data.message || 'Erro ao salvar fiador.');
                return;
            }

            const fiador: Fiador = await response.json();
            if (editando) {
                setFiadores((prev) => prev.map((f) => (f.id === fiador.id ? fiador : f)));
                toast.success('Fiador atualizado.');
            } else {
                setFiadores((prev) => [...prev, fiador]);
                toast.success('Fiador cadastrado.');
            }
            setDialogOpen(false);
        } catch {
            toast.error('Erro ao salvar fiador.');
        } finally {
            setSaving(false);
        }
    }

    async function handleExcluir() {
        if (!deleteTarget) return;
        setDeleteLoading(true);
        try {
            await fetch(`/contratos/${contratoId}/fiadores/${deleteTarget.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
            });
            setFiadores((prev) => prev.filter((f) => f.id !== deleteTarget.id));
            toast.success('Fiador removido.');
        } catch {
            toast.error('Erro ao remover fiador.');
        } finally {
            setDeleteLoading(false);
            setDeleteTarget(null);
        }
    }

    return (
        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-sm font-medium text-[#1E2D30]">Fiadores</h2>
                {podeAdicionar ? (
                    <Button variant="outline" size="sm" onClick={abrirAdicionar} className="border-[#D8DCDA]">
                        <Plus className="mr-1 h-3.5 w-3.5" />
                        Adicionar fiador
                    </Button>
                ) : (
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button variant="outline" size="sm" disabled className="border-[#D8DCDA]">
                                <Plus className="mr-1 h-3.5 w-3.5" />
                                Adicionar fiador
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Máximo de 2 fiadores por contrato</TooltipContent>
                    </Tooltip>
                )}
            </div>

            {fiadores.length === 0 ? (
                <div className="flex flex-col items-center py-6 text-center">
                    <UserPlus className="mb-2 h-8 w-8 text-[#8A918E]" />
                    <p className="text-sm text-[#8A918E]">Nenhum fiador cadastrado</p>
                    <p className="mb-2 text-xs text-[#8A918E]">Este contrato exige fiador como garantia.</p>
                    <div className="mb-4 rounded-md bg-[#FFF4E5] px-3 py-2 text-xs text-[#8C5A10]">
                        Contratos com garantia de fiador devem ter ao menos 1 fiador cadastrado.
                    </div>
                    <Button variant="outline" size="sm" onClick={abrirAdicionar} className="border-[#D8DCDA]">
                        <Plus className="mr-1 h-3.5 w-3.5" />
                        Adicionar fiador
                    </Button>
                </div>
            ) : (
                <div className="space-y-3">
                    {fiadores.map((f) => (
                        <div key={f.id} className="flex items-center justify-between rounded-lg border border-[#EEF0EF] px-4 py-3">
                            <div className="min-w-0 flex-1">
                                <p className="text-sm font-medium text-[#1E2D30]">{f.nome}</p>
                                <p className="text-xs text-[#8A918E]">CPF: {f.cpf} · {f.telefone}</p>
                                <p className="text-xs text-[#8A918E]">{f.cidade} — {f.uf}</p>
                            </div>
                            <div className="flex items-center gap-1">
                                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setDetalhesTarget(f)} aria-label="Ver dados completos">
                                    <Eye className="h-3.5 w-3.5 text-[#6B7370]" />
                                </Button>
                                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => abrirEditar(f)} aria-label="Editar fiador">
                                    <Pencil className="h-3.5 w-3.5 text-[#6B7370]" />
                                </Button>
                                <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setDeleteTarget(f)} aria-label="Remover fiador">
                                    <Trash2 className="h-3.5 w-3.5 text-[#A83232]" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Dialog adicionar/editar fiador */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar fiador' : 'Adicionar fiador'}</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-4">
                        {/* Dados pessoais */}
                        <p className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Dados pessoais</p>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="sm:col-span-2">
                                <Label>Nome completo</Label>
                                <Input value={form.nome} onChange={(e) => setField('nome', e.target.value)} className="bg-white border-[#D8DCDA]" />
                            </div>
                            <div>
                                <Label>CPF</Label>
                                <InputCpf value={form.cpf} onChange={(v) => setField('cpf', v)} />
                            </div>
                            <div>
                                <Label>RG <span className="text-[#8A918E]">(opcional)</span></Label>
                                <Input value={form.rg ?? ''} onChange={(e) => setField('rg', e.target.value)} className="bg-white border-[#D8DCDA]" />
                            </div>
                            <div>
                                <Label>Telefone</Label>
                                <InputTelefone value={form.telefone} onChange={(v) => setField('telefone', v)} />
                            </div>
                            <div>
                                <Label>Email <span className="text-[#8A918E]">(opcional)</span></Label>
                                <Input type="email" value={form.email ?? ''} onChange={(e) => setField('email', e.target.value)} className="bg-white border-[#D8DCDA]" />
                            </div>
                            <div>
                                <Label>Profissão <span className="text-[#8A918E]">(opcional)</span></Label>
                                <Input value={form.profissao ?? ''} onChange={(e) => setField('profissao', e.target.value)} className="bg-white border-[#D8DCDA]" />
                            </div>
                            <div>
                                <Label>Estado civil <span className="text-[#8A918E]">(opcional)</span></Label>
                                <Select value={form.estado_civil ?? ''} onValueChange={(v) => setField('estado_civil', v)}>
                                    <SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue placeholder="Selecione" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="Solteiro(a)">Solteiro(a)</SelectItem>
                                        <SelectItem value="Casado(a)">Casado(a)</SelectItem>
                                        <SelectItem value="Divorciado(a)">Divorciado(a)</SelectItem>
                                        <SelectItem value="Viúvo(a)">Viúvo(a)</SelectItem>
                                        <SelectItem value="União estável">União estável</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* Endereço */}
                        <p className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Endereço</p>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <div>
                                <Label>CEP</Label>
                                <InputCep value={form.cep} onChange={(v) => setField('cep', v)} onAddressFound={handleAddressFound} />
                            </div>
                            <div className="sm:col-span-2">
                                <Label>Logradouro</Label>
                                <Input value={form.logradouro} onChange={(e) => setField('logradouro', e.target.value)} className="bg-white border-[#D8DCDA]" />
                            </div>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-4">
                            <div>
                                <Label>Número</Label>
                                <Input value={form.numero} onChange={(e) => setField('numero', e.target.value)} className="bg-white border-[#D8DCDA]" />
                            </div>
                            <div className="sm:col-span-3">
                                <Label>Complemento</Label>
                                <Input value={form.complemento ?? ''} onChange={(e) => setField('complemento', e.target.value)} className="bg-white border-[#D8DCDA]" />
                            </div>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-3">
                            <div>
                                <Label>Bairro</Label>
                                <Input value={form.bairro} onChange={(e) => setField('bairro', e.target.value)} className="bg-white border-[#D8DCDA]" />
                            </div>
                            <div>
                                <Label>Cidade</Label>
                                <Input value={form.cidade} onChange={(e) => setField('cidade', e.target.value)} className="bg-white border-[#D8DCDA]" />
                            </div>
                            <div>
                                <Label>UF</Label>
                                <SelectUf value={form.uf} onValueChange={(v) => setField('uf', v)} className="bg-white border-[#D8DCDA]" />
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDialogOpen(false)} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button onClick={handleSalvar} disabled={saving || !form.nome || !form.cpf} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                            {saving && <Spinner />}
                            {editando ? 'Salvar alterações' : 'Adicionar fiador'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Dialog ver detalhes */}
            <FiadorDetalhesDialog fiador={detalhesTarget} open={!!detalhesTarget} onOpenChange={(open) => !open && setDetalhesTarget(null)} />

            {/* Dialog remover */}
            <ConfirmDialog
                open={!!deleteTarget}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                titulo="Remover fiador"
                descricao={deleteTarget ? `Tem certeza que deseja remover ${deleteTarget.nome} como fiador deste contrato?` : ''}
                textoConfirmar="Remover"
                variante="destructive"
                loading={deleteLoading}
                onConfirm={handleExcluir}
            />
        </div>
    );
}
