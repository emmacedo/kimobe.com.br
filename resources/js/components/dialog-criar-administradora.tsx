import { useState } from 'react';
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
import { Spinner } from '@/components/ui/spinner';
import type { Administradora } from '@/types/models';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onAdministradoraCriada: (administradora: Administradora) => void;
};

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

const initialState = {
    nome: '',
    cpf_cnpj: '',
    telefone: '',
    email: '',
};

export function DialogCriarAdministradora({ open, onOpenChange, onAdministradoraCriada }: Props) {
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState(initialState);

    async function handleSalvar() {
        if (!form.nome.trim()) {
            toast.error('Informe o nome da administradora.');
            return;
        }

        setSaving(true);
        try {
            const response = await fetch('/administradoras/inline', {
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
                // Laravel 422: { message, errors: { campo: ['msg'] } } — pega a primeira mensagem específica
                const primeiroErro = data?.errors ? Object.values(data.errors)[0] : null;
                const mensagem = (Array.isArray(primeiroErro) ? primeiroErro[0] : primeiroErro)
                    ?? data.message
                    ?? 'Erro ao cadastrar administradora.';
                toast.error(mensagem);
                return;
            }

            const administradora: Administradora = await response.json();
            onAdministradoraCriada(administradora);
            setForm(initialState);
            onOpenChange(false);
        } catch {
            toast.error('Erro ao cadastrar administradora.');
        } finally {
            setSaving(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Cadastrar nova administradora</DialogTitle>
                    <DialogDescription>
                        Cadastre rapidamente. Você pode complementar os dados depois em Administradoras.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-3">
                    <div>
                        <Label>Nome / Razão social</Label>
                        <Input
                            value={form.nome}
                            onChange={(e) => setForm((p) => ({ ...p, nome: e.target.value }))}
                            placeholder="Ex: Imobiliária ABC Administração"
                            className="bg-white border-[#D8DCDA]"
                        />
                    </div>

                    <div>
                        <Label>
                            CPF/CNPJ <span className="text-[#8A918E]">(opcional)</span>
                        </Label>
                        <InputCpfCnpj
                            value={form.cpf_cnpj}
                            onChange={(v) => setForm((p) => ({ ...p, cpf_cnpj: v }))}
                        />
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
                                placeholder="contato@empresa.com"
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
