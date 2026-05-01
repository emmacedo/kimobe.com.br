import { useEffect, useRef, useState } from 'react';
import { InputCpfCnpj } from '@/components/input-cpf-cnpj';
import InputError from '@/components/input-error';
import { InputTelefone } from '@/components/input-telefone';
import { Button } from '@/components/ui/button';
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

export type ProprietarioFormData = {
    name: string;
    tipo_pessoa: 'pf' | 'pj';
    documento: string;
    telefone: string;
    email: string;
};

export const dadosIniciaisProprietario: ProprietarioFormData = {
    name: '',
    tipo_pessoa: 'pf',
    documento: '',
    telefone: '',
    email: '',
};

type Props = {
    dados: ProprietarioFormData;
    errors: Record<string, string>;
    processing: boolean;
    onSubmit: (dados: ProprietarioFormData) => void;
    onDirtyChange?: (dirty: boolean) => void;
    onCancel: () => void;
    textoBotao: string;
    avisoEmailPlaceholder?: boolean;
};

export function ProprietarioForm({
    dados,
    errors,
    processing,
    onSubmit,
    onDirtyChange,
    onCancel,
    textoBotao,
    avisoEmailPlaceholder = false,
}: Props) {
    const [form, setForm] = useState<ProprietarioFormData>(dados);
    const initialRef = useRef(JSON.stringify(dados));

    useEffect(() => {
        const isDirty = JSON.stringify(form) !== initialRef.current;
        onDirtyChange?.(isDirty);
    }, [form, onDirtyChange]);

    function setField<K extends keyof ProprietarioFormData>(key: K, value: ProprietarioFormData[K]) {
        setForm((prev) => ({ ...prev, [key]: value }));
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        onSubmit(form);
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Dados do proprietário</h2>
                <div className="space-y-4">
                    <div>
                        <Label htmlFor="name">Nome / Razão social</Label>
                        <Input
                            id="name"
                            value={form.name}
                            onChange={(e) => setField('name', e.target.value)}
                            placeholder="Nome completo ou razão social"
                            className="bg-white border-[#D8DCDA]"
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-4 sm:grid-cols-12">
                        <div className="sm:col-span-3">
                            <Label>Tipo de pessoa</Label>
                            <Select value={form.tipo_pessoa} onValueChange={(v: 'pf' | 'pj') => setField('tipo_pessoa', v)}>
                                <SelectTrigger className="bg-white border-[#D8DCDA]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="pf">Pessoa física</SelectItem>
                                    <SelectItem value="pj">Pessoa jurídica</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.tipo_pessoa} />
                        </div>
                        <div className="sm:col-span-4">
                            <Label htmlFor="documento">
                                {form.tipo_pessoa === 'pj' ? 'CNPJ' : 'CPF'}{' '}
                                <span className="text-[#8A918E]">(opcional)</span>
                            </Label>
                            <InputCpfCnpj
                                value={form.documento}
                                onChange={(v) => setField('documento', v)}
                            />
                            <InputError message={errors.documento} />
                        </div>
                        <div className="sm:col-span-5">
                            <Label htmlFor="telefone">
                                Telefone <span className="text-[#8A918E]">(opcional)</span>
                            </Label>
                            <InputTelefone
                                value={form.telefone}
                                onChange={(v) => setField('telefone', v)}
                            />
                            <InputError message={errors.telefone} />
                        </div>
                    </div>

                    <div>
                        <Label htmlFor="email">
                            Email <span className="text-[#8A918E]">(opcional)</span>
                        </Label>
                        <Input
                            id="email"
                            type="email"
                            value={form.email}
                            onChange={(e) => setField('email', e.target.value)}
                            placeholder="email@exemplo.com"
                            className="bg-white border-[#D8DCDA]"
                        />
                        <InputError message={errors.email} />
                        {avisoEmailPlaceholder && form.email === '' && (
                            <p className="mt-1 rounded-md bg-[#FFF4E5] p-2 text-xs text-[#8C5A10]">
                                Este proprietário foi cadastrado sem email. Adicione um email real se quiser que ele tenha acesso ao sistema futuramente.
                            </p>
                        )}
                    </div>
                </div>
            </div>

            <div className="flex items-center justify-end gap-3">
                <Button type="button" variant="outline" onClick={onCancel} className="border-[#D8DCDA]">
                    Cancelar
                </Button>
                <Button
                    type="submit"
                    disabled={processing}
                    className="bg-[#0A4F5C] text-white hover:bg-[#073B45]"
                >
                    {processing && <Spinner />}
                    {textoBotao}
                </Button>
            </div>
        </form>
    );
}
