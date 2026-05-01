import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { InputCpfCnpj } from '@/components/input-cpf-cnpj';
import { InputTelefone } from '@/components/input-telefone';
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
import { getCsrfToken } from '@/lib/csrf';
import type { Proprietario } from '@/types/models';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    nomeInicial?: string;
    onProprietarioCriado: (proprietario: Proprietario) => void;
};

const initialState = {
    name: '',
    tipo_pessoa: 'pf',
    documento: '',
    telefone: '',
    email: '',
};

export function DialogCriarProprietario({ open, onOpenChange, nomeInicial, onProprietarioCriado }: Props) {
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState({ ...initialState });

    // Pré-preenche o nome ao abrir vindo do combobox
    useEffect(() => {
        if (open) {
            setForm({ ...initialState, name: nomeInicial ?? '' });
        }
    }, [open, nomeInicial]);

    async function handleSalvar() {
        if (!form.name.trim()) {
            toast.error('Informe o nome do proprietário.');
            return;
        }

        setSaving(true);
        try {
            const response = await fetch('/proprietarios/inline', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify(form),
            });

            if (!response.ok) {
                const data = await response.json().catch(() => ({}));
                const primeiroErro = data?.errors ? Object.values(data.errors)[0] : null;
                const mensagem = (Array.isArray(primeiroErro) ? primeiroErro[0] : primeiroErro)
                    ?? data.message
                    ?? 'Erro ao cadastrar proprietário.';
                toast.error(mensagem);
                return;
            }

            const proprietario: Proprietario = await response.json();
            onProprietarioCriado(proprietario);
            onOpenChange(false);
        } catch {
            toast.error('Erro ao cadastrar proprietário.');
        } finally {
            setSaving(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Cadastrar novo proprietário</DialogTitle>
                    <DialogDescription>
                        Cadastre rapidamente. Email é opcional — você pode complementar depois em Proprietários.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3">
                    <div>
                        <Label>Nome</Label>
                        <Input
                            value={form.name}
                            onChange={(e) => setForm((p) => ({ ...p, name: e.target.value }))}
                            placeholder="Nome completo ou razão social"
                            className="bg-white border-[#D8DCDA]"
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Tipo</Label>
                            <Select value={form.tipo_pessoa} onValueChange={(v) => setForm((p) => ({ ...p, tipo_pessoa: v }))}>
                                <SelectTrigger className="bg-white border-[#D8DCDA]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="pf">Pessoa física</SelectItem>
                                    <SelectItem value="pj">Pessoa jurídica</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>
                                {form.tipo_pessoa === 'pj' ? 'CNPJ' : 'CPF'}{' '}
                                <span className="text-[#8A918E]">(opcional)</span>
                            </Label>
                            <InputCpfCnpj
                                value={form.documento}
                                onChange={(v) => setForm((p) => ({ ...p, documento: v }))}
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label>
                                Telefone <span className="text-[#8A918E]">(opcional)</span>
                            </Label>
                            <InputTelefone
                                value={form.telefone}
                                onChange={(v) => setForm((p) => ({ ...p, telefone: v }))}
                            />
                        </div>
                        <div>
                            <Label>
                                Email <span className="text-[#8A918E]">(opcional)</span>
                            </Label>
                            <Input
                                type="email"
                                value={form.email}
                                onChange={(e) => setForm((p) => ({ ...p, email: e.target.value }))}
                                placeholder="email@exemplo.com"
                                className="bg-white border-[#D8DCDA]"
                            />
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)} className="border-[#D8DCDA]">
                        Cancelar
                    </Button>
                    <Button
                        onClick={handleSalvar}
                        disabled={saving}
                        className="bg-[#0A4F5C] text-white hover:bg-[#073B45]"
                    >
                        {saving && <Spinner />}
                        Cadastrar
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
