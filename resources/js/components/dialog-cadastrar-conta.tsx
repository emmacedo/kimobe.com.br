import { useState } from 'react';
import { toast } from 'sonner';
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
import type { DadosBancarios } from '@/types/models';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    vinculoId: number;
    onContaCriada: (conta: DadosBancarios) => void;
};

const BANCOS = [
    { codigo: '001', nome: 'Banco do Brasil' },
    { codigo: '033', nome: 'Santander' },
    { codigo: '104', nome: 'Caixa Econômica Federal' },
    { codigo: '237', nome: 'Bradesco' },
    { codigo: '341', nome: 'Itaú Unibanco' },
    { codigo: '260', nome: 'Nubank' },
    { codigo: '077', nome: 'Inter' },
    { codigo: '212', nome: 'Banco Original' },
    { codigo: '756', nome: 'Sicoob' },
    { codigo: '748', nome: 'Sicredi' },
];

function getCsrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

export function DialogCadastrarConta({ open, onOpenChange, vinculoId, onContaCriada }: Props) {
    const [saving, setSaving] = useState(false);
    const [form, setForm] = useState({
        apelido: '',
        banco_codigo: '',
        banco_nome: '',
        agencia: '',
        conta: '',
        tipo_conta: 'corrente',
        pix_tipo: '' as string,
        pix_chave: '',
    });

    function handleBancoChange(codigo: string) {
        const banco = BANCOS.find((b) => b.codigo === codigo);
        setForm((p) => ({ ...p, banco_codigo: codigo, banco_nome: banco?.nome ?? '' }));
    }

    async function handleSalvar() {
        if (!form.apelido || !form.banco_codigo || !form.agencia || !form.conta) {
            toast.error('Preencha os campos obrigatórios.');
            return;
        }

        setSaving(true);
        try {
            const response = await fetch('/dados-bancarios', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    vinculo_id: vinculoId,
                    ...form,
                    pix_tipo: form.pix_tipo || null,
                    pix_chave: form.pix_chave || null,
                }),
            });

            if (!response.ok) {
                const data = await response.json();
                toast.error(data.message || 'Erro ao cadastrar conta.');
                return;
            }

            const conta: DadosBancarios = await response.json();
            onContaCriada(conta);

            // Limpar form
            setForm({
                apelido: '', banco_codigo: '', banco_nome: '',
                agencia: '', conta: '', tipo_conta: 'corrente',
                pix_tipo: '', pix_chave: '',
            });
        } catch {
            toast.error('Erro ao cadastrar conta.');
        } finally {
            setSaving(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Cadastrar conta bancária</DialogTitle>
                    <DialogDescription>Informe os dados da conta para recebimento de repasses.</DialogDescription>
                </DialogHeader>

                <div className="space-y-3">
                    <div>
                        <Label>Nome da conta</Label>
                        <Input
                            value={form.apelido}
                            onChange={(e) => setForm((p) => ({ ...p, apelido: e.target.value }))}
                            placeholder="Ex: Conta Itaú principal"
                            className="bg-white border-[#D8DCDA]"
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Banco</Label>
                            <Select value={form.banco_codigo} onValueChange={handleBancoChange}>
                                <SelectTrigger className="bg-white border-[#D8DCDA]">
                                    <SelectValue placeholder="Selecione" />
                                </SelectTrigger>
                                <SelectContent>
                                    {BANCOS.map((b) => (
                                        <SelectItem key={b.codigo} value={b.codigo}>
                                            {b.codigo} — {b.nome}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label>Tipo de conta</Label>
                            <Select value={form.tipo_conta} onValueChange={(v) => setForm((p) => ({ ...p, tipo_conta: v }))}>
                                <SelectTrigger className="bg-white border-[#D8DCDA]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="corrente">Corrente</SelectItem>
                                    <SelectItem value="poupanca">Poupança</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Agência</Label>
                            <Input
                                value={form.agencia}
                                onChange={(e) => setForm((p) => ({ ...p, agencia: e.target.value }))}
                                placeholder="0001"
                                className="bg-white border-[#D8DCDA]"
                            />
                        </div>
                        <div>
                            <Label>Conta</Label>
                            <Input
                                value={form.conta}
                                onChange={(e) => setForm((p) => ({ ...p, conta: e.target.value }))}
                                placeholder="12345-6"
                                className="bg-white border-[#D8DCDA]"
                            />
                        </div>
                    </div>

                    {/* PIX (opcional) */}
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Tipo PIX <span className="text-[#8A918E]">(opcional)</span></Label>
                            <Select value={form.pix_tipo} onValueChange={(v) => setForm((p) => ({ ...p, pix_tipo: v }))}>
                                <SelectTrigger className="bg-white border-[#D8DCDA]">
                                    <SelectValue placeholder="Nenhum" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="cpf">CPF</SelectItem>
                                    <SelectItem value="cnpj">CNPJ</SelectItem>
                                    <SelectItem value="email">Email</SelectItem>
                                    <SelectItem value="telefone">Telefone</SelectItem>
                                    <SelectItem value="aleatoria">Aleatória</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        {form.pix_tipo && (
                            <div>
                                <Label>Chave PIX</Label>
                                <Input
                                    value={form.pix_chave}
                                    onChange={(e) => setForm((p) => ({ ...p, pix_chave: e.target.value }))}
                                    placeholder="Chave PIX"
                                    className="bg-white border-[#D8DCDA]"
                                />
                            </div>
                        )}
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
