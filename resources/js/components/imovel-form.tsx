import { Camera, Eye, EyeOff, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { InputCep } from '@/components/input-cep';
import InputError from '@/components/input-error';
import { InputMoeda } from '@/components/input-moeda';
import { SelectUf } from '@/components/select-uf';
import { SeletorEntidadeExterna } from '@/components/seletor-entidade-externa';
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
import type { EntidadeExterna } from '@/types/models';

export type CondominioFormData = {
    entidade_externa_id: number | null;
    dia_vencimento: number | null;
    valor: number | null;
    acesso_login: string;
    acesso_senha: string;
    acesso_descricao: string;
};

export const condominioVazio: CondominioFormData = {
    entidade_externa_id: null,
    dia_vencimento: null,
    valor: null,
    acesso_login: '',
    acesso_senha: '',
    acesso_descricao: '',
};

export type ImovelFormData = {
    cep: string;
    logradouro: string;
    numero: string;
    complemento: string;
    bairro: string;
    cidade: string;
    uf: string;
    inscricao_iptu: string;
    tipo: string;
    status: string;
    quartos: number | null;
    suites: number | null;
    banheiros: number | null;
    vagas_garagem: number | null;
    andar: number | null;
    area_m2: number | null;
    valor_aluguel_sugerido: number | null;
    observacoes: string;
    condominio: CondominioFormData;
};

type Props = {
    dados: ImovelFormData;
    entidadesExternas: EntidadeExterna[];
    errors: Record<string, string>;
    processing: boolean;
    onSubmit: (dados: ImovelFormData) => void;
    onDirtyChange?: (dirty: boolean) => void;
    onCancel: () => void;
    textoBotao: string;
    mostrarPlaceholders?: boolean;
};

// Tipos de imóvel que NÃO têm quartos/suítes
const tiposSemQuartos = ['sala', 'galpao'];
// Tipos de imóvel que NÃO têm andar
const tiposSemAndar = ['casa'];

export function ImovelForm({
    dados,
    entidadesExternas: entidadesIniciais,
    errors,
    processing,
    onSubmit,
    onDirtyChange,
    onCancel,
    textoBotao,
    mostrarPlaceholders = true,
}: Props) {
    const [form, setForm] = useState<ImovelFormData>(dados);
    const [entidadesExternas, setEntidadesExternas] = useState<EntidadeExterna[]>(entidadesIniciais);
    const [verSenha, setVerSenha] = useState(false);
    const initialRef = useRef(JSON.stringify(dados));
    const numeroRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        const isDirty = JSON.stringify(form) !== initialRef.current;
        onDirtyChange?.(isDirty);
    }, [form, onDirtyChange]);

    function setField<K extends keyof ImovelFormData>(key: K, value: ImovelFormData[K]) {
        setForm((prev) => ({ ...prev, [key]: value }));
    }

    function setCondominioField<K extends keyof CondominioFormData>(key: K, value: CondominioFormData[K]) {
        setForm((prev) => ({ ...prev, condominio: { ...prev.condominio, [key]: value } }));
    }

    function setNumericField(key: keyof ImovelFormData, value: string) {
        const num = value === '' ? null : parseInt(value, 10);
        setField(key, (isNaN(num as number) ? null : num) as any);
    }

    function setDiaVencimento(value: string) {
        if (value === '') {
            setCondominioField('dia_vencimento', null);
            return;
        }
        const num = parseInt(value, 10);
        if (isNaN(num)) return;
        setCondominioField('dia_vencimento', Math.max(1, Math.min(31, num)));
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

    function handleEntidadeCriada(entidade: EntidadeExterna) {
        setEntidadesExternas((prev) => [...prev, entidade].sort((a, b) => a.nome.localeCompare(b.nome, 'pt-BR')));
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        onSubmit(form);
    }

    const mostraQuartos = !tiposSemQuartos.includes(form.tipo);
    const mostraAndar = !tiposSemAndar.includes(form.tipo);

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {/* Seção 1 — Endereço */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Endereço</h2>
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
                                placeholder="Rua, avenida, travessa..."
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
                                placeholder="Apt, sala, bloco..."
                                className="bg-white border-[#D8DCDA]"
                            />
                            <InputError message={errors.complemento} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-12">
                        <div className="sm:col-span-3">
                            <Label htmlFor="bairro">Bairro</Label>
                            <Input
                                id="bairro"
                                value={form.bairro}
                                onChange={(e) => setField('bairro', e.target.value)}
                                className="bg-white border-[#D8DCDA]"
                            />
                            <InputError message={errors.bairro} />
                        </div>
                        <div className="sm:col-span-4">
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
                        <div className="sm:col-span-3">
                            <Label htmlFor="inscricao_iptu">
                                Inscrição do IPTU{' '}
                                <span className="text-[#8A918E]">(opcional)</span>
                            </Label>
                            <Input
                                id="inscricao_iptu"
                                value={form.inscricao_iptu}
                                onChange={(e) => setField('inscricao_iptu', e.target.value)}
                                placeholder="Inscrição imobiliária"
                                maxLength={50}
                                className="bg-white border-[#D8DCDA]"
                            />
                            <InputError message={errors.inscricao_iptu} />
                        </div>
                    </div>
                </div>
            </div>

            {/* Seção 2 — Características */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Características</h2>
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Tipo do imóvel</Label>
                            <Select value={form.tipo} onValueChange={(v) => setField('tipo', v)}>
                                <SelectTrigger className="bg-white border-[#D8DCDA]">
                                    <SelectValue placeholder="Selecione o tipo" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="apartamento">Apartamento</SelectItem>
                                    <SelectItem value="casa">Casa</SelectItem>
                                    <SelectItem value="sala">Sala comercial</SelectItem>
                                    <SelectItem value="loja">Loja</SelectItem>
                                    <SelectItem value="galpao">Galpão</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.tipo} />
                        </div>
                        <div>
                            <Label>Status</Label>
                            <Select value={form.status} onValueChange={(v) => setField('status', v)}>
                                <SelectTrigger className="bg-white border-[#D8DCDA]">
                                    <SelectValue placeholder="Selecione o status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="disponivel">Disponível</SelectItem>
                                    <SelectItem value="alugado">Alugado</SelectItem>
                                    <SelectItem value="manutencao">Manutenção</SelectItem>
                                    <SelectItem value="inativo">Inativo</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.status} />
                        </div>
                    </div>

                    <div className="grid gap-4 grid-cols-2 sm:grid-cols-4">
                        {mostraQuartos && (
                            <>
                                <div>
                                    <Label htmlFor="quartos">Quartos</Label>
                                    <Input
                                        id="quartos"
                                        type="number"
                                        min={0}
                                        step={1}
                                        value={form.quartos ?? ''}
                                        onChange={(e) => setNumericField('quartos', e.target.value)}
                                        className="bg-white border-[#D8DCDA]"
                                    />
                                    <InputError message={errors.quartos} />
                                </div>
                                <div>
                                    <Label htmlFor="suites">Suítes</Label>
                                    <Input
                                        id="suites"
                                        type="number"
                                        min={0}
                                        step={1}
                                        value={form.suites ?? ''}
                                        onChange={(e) => setNumericField('suites', e.target.value)}
                                        className="bg-white border-[#D8DCDA]"
                                    />
                                    <InputError message={errors.suites} />
                                </div>
                            </>
                        )}
                        <div>
                            <Label htmlFor="banheiros">Banheiros</Label>
                            <Input
                                id="banheiros"
                                type="number"
                                min={0}
                                step={1}
                                value={form.banheiros ?? ''}
                                onChange={(e) => setNumericField('banheiros', e.target.value)}
                                className="bg-white border-[#D8DCDA]"
                            />
                            <InputError message={errors.banheiros} />
                        </div>
                        <div>
                            <Label htmlFor="vagas_garagem">Vagas</Label>
                            <Input
                                id="vagas_garagem"
                                type="number"
                                min={0}
                                step={1}
                                value={form.vagas_garagem ?? ''}
                                onChange={(e) => setNumericField('vagas_garagem', e.target.value)}
                                className="bg-white border-[#D8DCDA]"
                            />
                            <InputError message={errors.vagas_garagem} />
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        {mostraAndar && (
                            <div>
                                <Label htmlFor="andar">Andar</Label>
                                <Input
                                    id="andar"
                                    type="number"
                                    min={0}
                                    step={1}
                                    value={form.andar ?? ''}
                                    onChange={(e) => setNumericField('andar', e.target.value)}
                                    className="bg-white border-[#D8DCDA]"
                                />
                                <InputError message={errors.andar} />
                            </div>
                        )}
                        <div>
                            <Label htmlFor="area_m2">Área (m²)</Label>
                            <Input
                                id="area_m2"
                                type="number"
                                min={0}
                                step={0.01}
                                value={form.area_m2 ?? ''}
                                onChange={(e) => {
                                    const val = e.target.value === '' ? null : parseFloat(e.target.value);
                                    setField('area_m2', isNaN(val as number) ? null : val);
                                }}
                                className="bg-white border-[#D8DCDA]"
                            />
                            <InputError message={errors.area_m2} />
                        </div>
                    </div>

                    <div className="max-w-sm">
                        <Label>Valor de aluguel sugerido</Label>
                        <InputMoeda
                            value={form.valor_aluguel_sugerido}
                            onChange={(v) => setField('valor_aluguel_sugerido', v)}
                        />
                        <InputError message={errors.valor_aluguel_sugerido} />
                    </div>
                </div>
            </div>

            {/* Seção 3 — Condomínio (opcional) */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">
                    Condomínio <span className="text-xs font-normal text-[#8A918E]">(opcional)</span>
                </h2>
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-12">
                        <div className="sm:col-span-3">
                            <Label htmlFor="dia_vencimento">Dia de vencimento</Label>
                            <Input
                                id="dia_vencimento"
                                type="number"
                                min={1}
                                max={31}
                                step={1}
                                value={form.condominio.dia_vencimento ?? ''}
                                onChange={(e) => setDiaVencimento(e.target.value)}
                                placeholder="1 a 31"
                                className="bg-white border-[#D8DCDA]"
                            />
                            <InputError message={errors['condominio.dia_vencimento']} />
                        </div>
                        <div className="sm:col-span-4">
                            <Label htmlFor="valor_condominio">Valor mensal</Label>
                            <InputMoeda
                                value={form.condominio.valor}
                                onChange={(v) => setCondominioField('valor', v)}
                            />
                            <InputError message={errors['condominio.valor']} />
                        </div>
                        <div className="sm:col-span-5">
                            <Label>Administradora</Label>
                            <SeletorEntidadeExterna
                                entidades={entidadesExternas}
                                value={form.condominio.entidade_externa_id}
                                onChange={(id) => setCondominioField('entidade_externa_id', id)}
                                onEntidadeCriada={handleEntidadeCriada}
                                tipoCriacao="administradora_condominio"
                                placeholder="Selecione a administradora"
                                tituloDialog="Cadastrar nova administradora"
                            />
                            <InputError message={errors['condominio.entidade_externa_id']} />
                        </div>
                    </div>

                    {/* Sub-bloco: dados de acesso (opcional) */}
                    <div className="rounded-md border border-dashed border-[#D8DCDA] bg-[#FAFBFA] p-4">
                        <p className="mb-3 text-xs font-medium uppercase tracking-wider text-[#8A918E]">
                            Dados de acesso ao portal da administradora do condomínio
                        </p>
                        <div className="space-y-3">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="acesso_login">Login</Label>
                                    <Input
                                        id="acesso_login"
                                        name="condominio_acesso_login"
                                        value={form.condominio.acesso_login}
                                        onChange={(e) => setCondominioField('acesso_login', e.target.value)}
                                        placeholder="Usuário do portal"
                                        autoComplete="new-password"
                                        data-form-type="other"
                                        className="bg-white border-[#D8DCDA]"
                                    />
                                    <InputError message={errors['condominio.acesso_login']} />
                                </div>
                                <div>
                                    <Label htmlFor="acesso_senha">Senha</Label>
                                    <div className="relative">
                                        <Input
                                            id="acesso_senha"
                                            name="condominio_acesso_senha"
                                            type={verSenha ? 'text' : 'password'}
                                            value={form.condominio.acesso_senha}
                                            onChange={(e) => setCondominioField('acesso_senha', e.target.value)}
                                            placeholder="Senha do portal"
                                            autoComplete="new-password"
                                            data-form-type="other"
                                            className="bg-white border-[#D8DCDA] pr-10"
                                        />
                                        <button
                                            type="button"
                                            onClick={() => setVerSenha((p) => !p)}
                                            className="absolute right-2 top-1/2 -translate-y-1/2 text-[#8A918E] hover:text-[#1E2D30]"
                                            aria-label={verSenha ? 'Ocultar senha' : 'Mostrar senha'}
                                        >
                                            {verSenha ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                        </button>
                                    </div>
                                    <InputError message={errors['condominio.acesso_senha']} />
                                </div>
                            </div>
                            <div>
                                <Label htmlFor="acesso_descricao">Descrição</Label>
                                <textarea
                                    id="acesso_descricao"
                                    value={form.condominio.acesso_descricao}
                                    onChange={(e) => setCondominioField('acesso_descricao', e.target.value)}
                                    placeholder="URL do portal, observações sobre o acesso, etc."
                                    rows={2}
                                    maxLength={5000}
                                    className="w-full rounded-md border border-[#D8DCDA] bg-white px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-[#0A4F5C] focus:outline-none focus:ring-1 focus:ring-[#0A4F5C]"
                                />
                                <InputError message={errors['condominio.acesso_descricao']} />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Seção 4 — Observações */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Observações</h2>
                <textarea
                    value={form.observacoes}
                    onChange={(e) => setField('observacoes', e.target.value)}
                    placeholder="Notas internas sobre o imóvel... (visível apenas para administradores)"
                    rows={4}
                    maxLength={5000}
                    className="w-full rounded-md border border-[#D8DCDA] bg-white px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-[#0A4F5C] focus:outline-none focus:ring-1 focus:ring-[#0A4F5C]"
                />
                <InputError message={errors.observacoes} />
            </div>

            {/* Placeholders de fotos e titulares — só aparecem no formulário de criação */}
            {mostrarPlaceholders && (
                <>
                    <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5 opacity-60">
                        <div className="flex items-center gap-3 text-[#8A918E]">
                            <Camera className="h-5 w-5" />
                            <div>
                                <p className="text-sm font-medium">Fotos</p>
                                <p className="text-xs">Salve o imóvel para adicionar fotos</p>
                            </div>
                        </div>
                    </div>
                    <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5 opacity-60">
                        <div className="flex items-center gap-3 text-[#8A918E]">
                            <Users className="h-5 w-5" />
                            <div>
                                <p className="text-sm font-medium">Titulares</p>
                                <p className="text-xs">Salve o imóvel para gerenciar titulares</p>
                            </div>
                        </div>
                    </div>
                </>
            )}

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
