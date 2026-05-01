import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ComboboxProprietario } from '@/components/combobox-proprietario';
import { DialogCadastrarConta } from '@/components/dialog-cadastrar-conta';
import { DialogCriarProprietario } from '@/components/dialog-criar-proprietario';
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
import type { DadosBancarios, Proprietario } from '@/types/models';

export type TitularFormData = {
    proprietario: Proprietario | null;
    tipo_titular: 'pessoa_fisica' | 'empresa' | 'inventario';
    papel: 'responsavel' | 'observador';
    percentual: string;
    dados_bancarios_id: number | null;
};

const formInicial: TitularFormData = {
    proprietario: null,
    tipo_titular: 'pessoa_fisica',
    papel: 'responsavel',
    percentual: '',
    dados_bancarios_id: null,
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Quando null, é uma criação. Quando preenchido, edição (proprietário não é editável). */
    editando: {
        vinculo_id: number;
        proprietario_nome: string;
        proprietario_tipo_pessoa: 'pf' | 'pj';
        proprietario_documento: string | null;
        tipo_titular: 'pessoa_fisica' | 'empresa' | 'inventario';
        papel: 'responsavel' | 'observador';
        percentual: string;
        dados_bancarios_id: number | null;
    } | null;
    /** % disponível para preencher (já considera o item em edição). */
    disponivel: number;
    /** Indica se há outro responsável atual além do em edição (para mostrar aviso de demote). */
    outroResponsavelExiste: boolean;
    excludeVinculoIds: number[];
    onSalvar: (form: TitularFormData) => Promise<void>;
    saving: boolean;
};

export function DialogTitularForm({
    open,
    onOpenChange,
    editando,
    disponivel,
    outroResponsavelExiste,
    excludeVinculoIds,
    onSalvar,
    saving,
}: Props) {
    const [form, setForm] = useState<TitularFormData>(formInicial);
    const [contasVinculo, setContasVinculo] = useState<DadosBancarios[]>([]);
    const [carregandoContas, setCarregandoContas] = useState(false);
    const [dialogConta, setDialogConta] = useState(false);
    const [dialogProprietario, setDialogProprietario] = useState(false);
    const [proprietarioPendingNome, setProprietarioPendingNome] = useState('');

    // Hidrata o form sempre que o dialog abre.
    useEffect(() => {
        if (!open) return;

        if (editando) {
            setForm({
                proprietario: {
                    vinculo_id: editando.vinculo_id,
                    user_id: 0,
                    name: editando.proprietario_nome,
                    email: null,
                    telefone: null,
                    tipo_pessoa: editando.proprietario_tipo_pessoa,
                    documento: editando.proprietario_documento,
                    status: 'ativo',
                },
                tipo_titular: editando.tipo_titular,
                papel: editando.papel,
                percentual: parseFloat(editando.percentual).toString(),
                dados_bancarios_id: editando.dados_bancarios_id,
            });
            carregarContasVinculo(editando.vinculo_id);
        } else {
            setForm({
                ...formInicial,
                // Se já há um responsável, novo titular vira observador por padrão.
                papel: outroResponsavelExiste ? 'observador' : 'responsavel',
            });
            setContasVinculo([]);
        }
    }, [open, editando, outroResponsavelExiste]);

    async function carregarContasVinculo(vinculoId: number) {
        setCarregandoContas(true);
        try {
            const response = await fetch(`/vinculos/${vinculoId}/dados-bancarios`, {
                headers: { Accept: 'application/json' },
            });
            setContasVinculo(response.ok ? await response.json() : []);
        } catch {
            setContasVinculo([]);
        } finally {
            setCarregandoContas(false);
        }
    }

    function handleProprietarioChange(p: Proprietario | null) {
        setForm((prev) => ({ ...prev, proprietario: p, dados_bancarios_id: null }));
        if (p) carregarContasVinculo(p.vinculo_id);
        else setContasVinculo([]);
    }

    function handleProprietarioCriado(p: Proprietario) {
        setForm((prev) => ({ ...prev, proprietario: p, dados_bancarios_id: null }));
        setContasVinculo([]);
        toast.success('Proprietário cadastrado.');
    }

    function handleContaCriada(conta: DadosBancarios) {
        setContasVinculo((prev) => [...prev, conta]);
        setForm((prev) => ({ ...prev, dados_bancarios_id: conta.id }));
        setDialogConta(false);
        toast.success('Conta bancária cadastrada.');
    }

    async function handleSalvar() {
        if (!editando && !form.proprietario) {
            toast.error('Selecione o proprietário.');
            return;
        }
        const valorPerc = parseFloat(form.percentual);
        if (!form.percentual || isNaN(valorPerc) || valorPerc <= 0) {
            toast.error('Informe o percentual de propriedade.');
            return;
        }
        if (valorPerc > disponivel + 0.005) {
            toast.error(`A soma ultrapassaria 100%. Disponível: ${disponivel.toFixed(2)}%.`);
            return;
        }

        await onSalvar(form);
    }

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar titular' : 'Adicionar titular'}</DialogTitle>
                        <DialogDescription>
                            {editando
                                ? 'Altere os dados do titular.'
                                : 'Busque o proprietário ou cadastre um novo, e defina o percentual.'}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div>
                            <Label>Proprietário</Label>
                            {editando ? (
                                <Input value={editando.proprietario_nome} disabled className="bg-[#F7F8F7]" />
                            ) : (
                                <ComboboxProprietario
                                    value={form.proprietario}
                                    onChange={handleProprietarioChange}
                                    onCriarNovo={() => {
                                        setProprietarioPendingNome('');
                                        setDialogProprietario(true);
                                    }}
                                    excludeVinculoIds={excludeVinculoIds}
                                />
                            )}
                        </div>

                        <div>
                            <Label>Tipo de titular</Label>
                            <Select
                                value={form.tipo_titular}
                                onValueChange={(v: TitularFormData['tipo_titular']) =>
                                    setForm((p) => ({ ...p, tipo_titular: v }))
                                }
                            >
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

                        <div>
                            <Label>Papel</Label>
                            <Select
                                value={form.papel}
                                onValueChange={(v: TitularFormData['papel']) => setForm((p) => ({ ...p, papel: v }))}
                            >
                                <SelectTrigger className="bg-white border-[#D8DCDA]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="responsavel">Responsável</SelectItem>
                                    <SelectItem value="observador">Observador</SelectItem>
                                </SelectContent>
                            </Select>
                            {form.papel === 'responsavel' && outroResponsavelExiste && (
                                <p className="mt-1 text-xs text-[#8C5A10]">
                                    Marcar como responsável demoverá o atual responsável para observador.
                                </p>
                            )}
                        </div>

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

                        <div>
                            <Label>Conta bancária para repasse</Label>
                            {carregandoContas ? (
                                <p className="mt-1 text-xs text-[#8A918E]">Carregando...</p>
                            ) : contasVinculo.length > 0 ? (
                                <Select
                                    value={form.dados_bancarios_id?.toString() ?? ''}
                                    onValueChange={(v) =>
                                        setForm((p) => ({ ...p, dados_bancarios_id: v ? parseInt(v) : null }))
                                    }
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
                                <p className="mt-1 text-xs text-[#8A918E]">
                                    Nenhuma conta cadastrada para este proprietário.
                                </p>
                            )}
                            {(form.proprietario || editando) && (
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
                        <Button variant="outline" onClick={() => onOpenChange(false)} className="border-[#D8DCDA]">
                            Cancelar
                        </Button>
                        <Button
                            onClick={handleSalvar}
                            disabled={saving || (!editando && !form.proprietario)}
                            className="bg-[#0A4F5C] text-white hover:bg-[#073B45]"
                        >
                            {saving && <Spinner />}
                            {editando ? 'Salvar' : 'Adicionar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <DialogCriarProprietario
                open={dialogProprietario}
                onOpenChange={setDialogProprietario}
                nomeInicial={proprietarioPendingNome}
                onProprietarioCriado={handleProprietarioCriado}
            />

            <DialogCadastrarConta
                open={dialogConta}
                onOpenChange={setDialogConta}
                vinculoId={form.proprietario?.vinculo_id ?? editando?.vinculo_id ?? 0}
                onContaCriada={handleContaCriada}
            />
        </>
    );
}
