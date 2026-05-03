import { useEffect, useRef, useState } from 'react';
import { InputCep } from '@/components/input-cep';
import { InputCpfCnpj } from '@/components/input-cpf-cnpj';
import InputError from '@/components/input-error';
import { InputTelefone } from '@/components/input-telefone';
import { SelectUf } from '@/components/select-uf';
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
import type { TipoEntidadeExterna } from '@/types/models';

export type EntidadeExternaFormData = {
    nome: string;
    tipo: TipoEntidadeExterna;
    cpf_cnpj: string;
    telefone: string;
    email: string;
    site: string;
    contato_interno_nome: string;
    cep: string;
    logradouro: string;
    numero: string;
    complemento: string;
    bairro: string;
    cidade: string;
    uf: string;
    observacoes: string;
};

export const dadosIniciaisEntidadeExterna: EntidadeExternaFormData = {
    nome: '',
    tipo: 'administradora_condominio',
    cpf_cnpj: '',
    telefone: '',
    email: '',
    site: '',
    contato_interno_nome: '',
    cep: '',
    logradouro: '',
    numero: '',
    complemento: '',
    bairro: '',
    cidade: '',
    uf: '',
    observacoes: '',
};

const TIPOS: { valor: TipoEntidadeExterna; label: string }[] = [
    { valor: 'administradora_condominio', label: 'Administradora de condomínio' },
    { valor: 'sindico', label: 'Síndico' },
    { valor: 'prefeitura', label: 'Prefeitura' },
    { valor: 'seguradora', label: 'Seguradora' },
    { valor: 'prestador_servico', label: 'Prestador de serviço' },
    { valor: 'empresa', label: 'Empresa' },
    { valor: 'pessoa_fisica', label: 'Pessoa física' },
    { valor: 'outro', label: 'Outro' },
];

type Props = {
    dados: EntidadeExternaFormData;
    errors: Record<string, string>;
    processing: boolean;
    onSubmit: (dados: EntidadeExternaFormData) => void;
    onDirtyChange?: (dirty: boolean) => void;
    onCancel: () => void;
    textoBotao: string;
};

export function EntidadeExternaForm({ dados, errors, processing, onSubmit, onDirtyChange, onCancel, textoBotao }: Props) {
    const [form, setForm] = useState<EntidadeExternaFormData>(dados);
    const initialRef = useRef(JSON.stringify(dados));
    const numeroRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        const isDirty = JSON.stringify(form) !== initialRef.current;
        onDirtyChange?.(isDirty);
    }, [form, onDirtyChange]);

    function setField<K extends keyof EntidadeExternaFormData>(key: K, value: EntidadeExternaFormData[K]) {
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
        setTimeout(() => numeroRef.current?.focus(), 100);
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        onSubmit(form);
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {/* Identificação */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Identificação</h2>
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-12">
                        <div className="sm:col-span-7">
                            <Label htmlFor="nome">Nome / Razão social</Label>
                            <Input
                                id="nome"
                                value={form.nome}
                                onChange={(e) => setField('nome', e.target.value)}
                                placeholder="Ex: Imobiliária ABC Administração"
                                className="bg-white border-[#D8DCDA]"
                            />
                            <InputError message={errors.nome} />
                        </div>
                        <div className="sm:col-span-5">
                            <Label>Tipo</Label>
                            <Select value={form.tipo} onValueChange={(v) => setField('tipo', v as TipoEntidadeExterna)}>
                                <SelectTrigger className="bg-white border-[#D8DCDA]">
                                    <SelectValue placeholder="Selecione o tipo" />
                                </SelectTrigger>
                                <SelectContent>
                                    {TIPOS.map((t) => (
                                        <SelectItem key={t.valor} value={t.valor}>
                                            {t.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.tipo} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-12">
                        <div className="sm:col-span-5">
                            <Label htmlFor="cpf_cnpj">
                                CPF/CNPJ <span className="text-[#8A918E]">(opcional)</span>
                            </Label>
                            <InputCpfCnpj
                                value={form.cpf_cnpj}
                                onChange={(v) => setField('cpf_cnpj', v)}
                            />
                            <InputError message={errors.cpf_cnpj} />
                        </div>
                        <div className="sm:col-span-4">
                            <Label htmlFor="telefone">
                                Telefone <span className="text-[#8A918E]">(opcional)</span>
                            </Label>
                            <InputTelefone
                                value={form.telefone}
                                onChange={(v) => setField('telefone', v)}
                            />
                            <InputError message={errors.telefone} />
                        </div>
                        <div className="sm:col-span-3">
                            <Label htmlFor="email">
                                Email <span className="text-[#8A918E]">(opcional)</span>
                            </Label>
                            <Input
                                id="email"
                                type="email"
                                value={form.email}
                                onChange={(e) => setField('email', e.target.value)}
                                placeholder="contato@empresa.com"
                                className="bg-white border-[#D8DCDA]"
                            />
                            <InputError message={errors.email} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-12">
                        <div className="sm:col-span-6">
                            <Label htmlFor="site">
                                Site <span className="text-[#8A918E]">(opcional)</span>
                            </Label>
                            <Input
                                id="site"
                                value={form.site}
                                onChange={(e) => setField('site', e.target.value)}
                                placeholder="https://"
                                className="bg-white border-[#D8DCDA]"
                            />
                            <InputError message={errors.site} />
                        </div>
                        <div className="sm:col-span-6">
                            <Label htmlFor="contato_interno_nome">
                                Contato interno <span className="text-[#8A918E]">(opcional)</span>
                            </Label>
                            <Input
                                id="contato_interno_nome"
                                value={form.contato_interno_nome}
                                onChange={(e) => setField('contato_interno_nome', e.target.value)}
                                placeholder="Nome da pessoa que atende você"
                                className="bg-white border-[#D8DCDA]"
                            />
                            <InputError message={errors.contato_interno_nome} />
                        </div>
                    </div>
                </div>
            </div>

            {/* Endereço */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">
                    Endereço <span className="text-xs font-normal text-[#8A918E]">(opcional)</span>
                </h2>
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-12">
                        <div className="sm:col-span-2">
                            <Label htmlFor="cep">CEP</Label>
                            <InputCep
                                value={form.cep}
                                onChange={(v) => setField('cep', v)}
                                onAddressFound={handleAddressFound}
                            />
                            <InputError message={errors.cep} />
                        </div>
                        <div className="sm:col-span-5">
                            <Label htmlFor="logradouro">Logradouro</Label>
                            <Input
                                id="logradouro"
                                value={form.logradouro}
                                onChange={(e) => setField('logradouro', e.target.value)}
                                placeholder="Rua, avenida..."
                                className="bg-white border-[#D8DCDA]"
                            />
                            <InputError message={errors.logradouro} />
                        </div>
                        <div className="sm:col-span-2">
                            <Label htmlFor="numero">Número</Label>
                            <Input
                                id="numero"
                                ref={numeroRef}
                                value={form.numero}
                                onChange={(e) => setField('numero', e.target.value)}
                                placeholder="Nº"
                                className="bg-white border-[#D8DCDA]"
                            />
                            <InputError message={errors.numero} />
                        </div>
                        <div className="sm:col-span-3">
                            <Label htmlFor="complemento">Complemento</Label>
                            <Input
                                id="complemento"
                                value={form.complemento}
                                onChange={(e) => setField('complemento', e.target.value)}
                                placeholder="Sala, andar..."
                                className="bg-white border-[#D8DCDA]"
                            />
                            <InputError message={errors.complemento} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-12">
                        <div className="sm:col-span-4">
                            <Label htmlFor="bairro">Bairro</Label>
                            <Input
                                id="bairro"
                                value={form.bairro}
                                onChange={(e) => setField('bairro', e.target.value)}
                                className="bg-white border-[#D8DCDA]"
                            />
                            <InputError message={errors.bairro} />
                        </div>
                        <div className="sm:col-span-6">
                            <Label htmlFor="cidade">Cidade</Label>
                            <Input
                                id="cidade"
                                value={form.cidade}
                                onChange={(e) => setField('cidade', e.target.value)}
                                className="bg-white border-[#D8DCDA]"
                            />
                            <InputError message={errors.cidade} />
                        </div>
                        <div className="sm:col-span-2">
                            <Label>UF</Label>
                            <SelectUf
                                value={form.uf}
                                onValueChange={(v) => setField('uf', v)}
                                className="bg-white border-[#D8DCDA]"
                            />
                            <InputError message={errors.uf} />
                        </div>
                    </div>
                </div>
            </div>

            {/* Observações */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">
                    Observações <span className="text-xs font-normal text-[#8A918E]">(opcional)</span>
                </h2>
                <textarea
                    value={form.observacoes}
                    onChange={(e) => setField('observacoes', e.target.value)}
                    placeholder="Horário de atendimento, particularidades, anotações internas..."
                    rows={4}
                    maxLength={5000}
                    className="w-full rounded-md border border-[#D8DCDA] bg-white px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-[#0A4F5C] focus:outline-none focus:ring-1 focus:ring-[#0A4F5C]"
                />
                <InputError message={errors.observacoes} />
            </div>

            {/* Rodapé */}
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
