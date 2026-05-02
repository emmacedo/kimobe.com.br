import { ClipboardList, Clock, FileText, Shield, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ComboboxImovel } from '@/components/combobox-imovel';
import InputError from '@/components/input-error';
import { InputMoeda } from '@/components/input-moeda';
import { RadioCardGroup } from '@/components/radio-card-group';
import { SimulacaoRepasse } from '@/components/simulacao-repasse';
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
import { formataMoeda } from '@/lib/utils';
import type { ImovelDisponivel } from '@/types/models';

export type ContratoFormData = {
    imovel_id: number | null;
    valor_aluguel: number | null;
    dia_vencimento: number | null;
    data_inicio: string;
    data_fim: string;
    indice_reajuste: string;
    mes_reajuste: number | null;
    modelo_repasse: string;
    taxa_administracao_pct: number | null;
    taxa_seguro_inadimplencia_pct: number | null;
    multa_atraso_pct: number | null;
    juros_atraso_pct_dia: number | null;
    dias_carencia: number | null;
    multa_rescisoria_pct: number | null;
    desconto_pontualidade_pct: number | null;
    tipo_garantia: string;
    garantia_valor: number | null;
    garantia_seguradora: string;
    garantia_numero_apolice: string;
    garantia_numero_titulo: string;
    garantia_data_inicio: string;
    garantia_data_fim: string;
    observacoes: string;
};

type Props = {
    dados: ContratoFormData;
    errors: Record<string, string>;
    processing: boolean;
    onSubmit: (dados: ContratoFormData) => void;
    onDirtyChange?: (dirty: boolean) => void;
    onCancel: () => void;
    textoBotao: string;
    modoEdicao?: boolean;
    /** Apenas em modoEdicao=true: dados do imóvel atual para mostrar (campo desabilitado). */
    imovelAtual?: {
        id: number;
        logradouro: string;
        numero: string;
        complemento: string | null;
        valor_aluguel_sugerido: string | null;
        titularidades?: Array<{ vinculo: { user: { name: string } }; percentual: string }>;
    } | null;
};

const MESES = [
    'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
];

export function ContratoForm({
    dados, errors, processing, onSubmit, onDirtyChange, onCancel, textoBotao,
    modoEdicao = false, imovelAtual,
}: Props) {
    const [form, setForm] = useState<ContratoFormData>(dados);
    const initialRef = useRef(JSON.stringify(dados));
    // No modo criar, mantém o objeto completo do imóvel selecionado para mostrar contexto
    // (titulares + valor sugerido). No modo editar usa imovelAtual.
    const [imovelSelecionadoCompleto, setImovelSelecionadoCompleto] = useState<ImovelDisponivel | null>(null);

    useEffect(() => {
        onDirtyChange?.(JSON.stringify(form) !== initialRef.current);
    }, [form, onDirtyChange]);

    function setField<K extends keyof ContratoFormData>(key: K, value: ContratoFormData[K]) {
        setForm((prev) => ({ ...prev, [key]: value }));
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        onSubmit(form);
    }

    // Imóvel selecionado (para contexto de titulares e simulação)
    const imovelSelecionado = modoEdicao ? imovelAtual : imovelSelecionadoCompleto;

    // Titulares para simulação
    const titulares = (imovelSelecionado?.titularidades ?? []).map((t) => ({
        nome: t.vinculo.user.name,
        percentual: parseFloat(t.percentual),
    }));

    // Ao selecionar imóvel, pré-preencher valor sugerido (mantém comportamento atual).
    function handleImovelChange(imovel: ImovelDisponivel | null) {
        setImovelSelecionadoCompleto(imovel);
        setField('imovel_id', imovel?.id ?? null);
        if (imovel?.valor_aluguel_sugerido && !form.valor_aluguel) {
            setField('valor_aluguel', parseFloat(imovel.valor_aluguel_sugerido));
        }
    }

    // Calcular duração em meses
    const duracaoMeses = form.data_inicio && form.data_fim
        ? Math.round((new Date(form.data_fim).getTime() - new Date(form.data_inicio).getTime()) / (1000 * 60 * 60 * 24 * 30))
        : null;

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {/* Seção 1 — Imóvel */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Imóvel</h2>
                <div className="space-y-4">
                    <div>
                        <Label>Imóvel</Label>
                        {modoEdicao ? (
                            <Input
                                value={imovelAtual ? (imovelAtual.complemento || `${imovelAtual.logradouro}, ${imovelAtual.numero}`) : ''}
                                disabled
                                className="bg-[#F7F8F7]"
                            />
                        ) : (
                            <ComboboxImovel value={imovelSelecionadoCompleto} onChange={handleImovelChange} />
                        )}
                        <InputError message={errors.imovel_id} />
                    </div>

                    {/* Contexto: titulares do imóvel */}
                    {imovelSelecionado && imovelSelecionado.titularidades && imovelSelecionado.titularidades.length > 0 && (
                        <div className="rounded-md bg-[#F7F8F7] p-3">
                            <p className="mb-1.5 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Titulares do imóvel</p>
                            <div className="space-y-1">
                                {imovelSelecionado.titularidades.map((t, i) => (
                                    <div key={i} className="flex items-center justify-between text-xs">
                                        <span className="text-[#3A4240]">{t.vinculo.user.name}</span>
                                        <span className="font-medium text-[#0A4F5C]">{parseFloat(t.percentual).toFixed(0)}%</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>

            {/* Seção 2 — Valores e vigência */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Valores e vigência</h2>
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Valor do aluguel</Label>
                            <InputMoeda value={form.valor_aluguel} onChange={(v) => setField('valor_aluguel', v)} />
                            <InputError message={errors.valor_aluguel} />
                        </div>
                        <div>
                            <Label>Dia de vencimento</Label>
                            <Select value={form.dia_vencimento?.toString() ?? ''} onValueChange={(v) => setField('dia_vencimento', parseInt(v))}>
                                <SelectTrigger className="bg-white border-[#D8DCDA]">
                                    <SelectValue placeholder="Dia" />
                                </SelectTrigger>
                                <SelectContent>
                                    {Array.from({ length: 28 }, (_, i) => i + 1).map((d) => (
                                        <SelectItem key={d} value={d.toString()}>Dia {d}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <p className="mt-1 text-[10px] text-[#8A918E]">Dias 29-31 não aceitos (meses curtos)</p>
                            <InputError message={errors.dia_vencimento} />
                        </div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Data de início</Label>
                            <Input type="date" value={form.data_inicio} onChange={(e) => setField('data_inicio', e.target.value)} className="bg-white border-[#D8DCDA]" />
                            <InputError message={errors.data_inicio} />
                        </div>
                        <div>
                            <Label>Data de término</Label>
                            <Input type="date" value={form.data_fim} onChange={(e) => setField('data_fim', e.target.value)} className="bg-white border-[#D8DCDA]" />
                            {duracaoMeses && duracaoMeses > 0 && (
                                <p className="mt-1 text-[10px] text-[#8A918E]">Duração: {duracaoMeses} meses</p>
                            )}
                            <InputError message={errors.data_fim} />
                        </div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Índice de reajuste</Label>
                            <Select value={form.indice_reajuste} onValueChange={(v) => setField('indice_reajuste', v)}>
                                <SelectTrigger className="bg-white border-[#D8DCDA]">
                                    <SelectValue placeholder="Selecione" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="igpm">IGPM</SelectItem>
                                    <SelectItem value="ipca">IPCA</SelectItem>
                                    <SelectItem value="fixo">Fixo</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={errors.indice_reajuste} />
                        </div>
                        <div>
                            <Label>Mês do reajuste</Label>
                            <Select value={form.mes_reajuste?.toString() ?? ''} onValueChange={(v) => setField('mes_reajuste', parseInt(v))}>
                                <SelectTrigger className="bg-white border-[#D8DCDA]">
                                    <SelectValue placeholder="Selecione" />
                                </SelectTrigger>
                                <SelectContent>
                                    {MESES.map((m, i) => (
                                        <SelectItem key={i + 1} value={(i + 1).toString()}>{m}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.mes_reajuste} />
                        </div>
                    </div>
                </div>
            </div>

            {/* Seção 3 — Modelo de repasse */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Modelo de repasse</h2>
                <div className="space-y-4">
                    <RadioCardGroup
                        value={form.modelo_repasse}
                        onChange={(v) => setField('modelo_repasse', v)}
                        options={[
                            {
                                value: 'por_recebimento',
                                titulo: 'Por recebimento',
                                descricao: 'O proprietário recebe o repasse somente após o inquilino efetuar o pagamento.',
                                icone: <Clock className="h-4 w-4 text-[#0A4F5C]" />,
                            },
                            {
                                value: 'garantido',
                                titulo: 'Garantido',
                                descricao: 'O proprietário recebe na data fixa, independente do pagamento. Requer seguro inadimplência.',
                                icone: <Shield className="h-4 w-4 text-[#C9A84C]" />,
                            },
                        ]}
                    />
                    <InputError message={errors.modelo_repasse} />

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Taxa de administração (%)</Label>
                            <Input
                                type="number" min={0} max={100} step={0.01}
                                value={form.taxa_administracao_pct ?? ''}
                                onChange={(e) => setField('taxa_administracao_pct', e.target.value ? parseFloat(e.target.value) : null)}
                                className="bg-white border-[#D8DCDA]"
                            />
                            <p className="mt-1 text-[10px] text-[#8A918E]">Percentual cobrado sobre o aluguel</p>
                            <InputError message={errors.taxa_administracao_pct} />
                        </div>
                        {form.modelo_repasse === 'garantido' && (
                            <div className="transition-all">
                                <Label>Seguro inadimplência (%)</Label>
                                <Input
                                    type="number" min={0} max={100} step={0.01}
                                    value={form.taxa_seguro_inadimplencia_pct ?? ''}
                                    onChange={(e) => setField('taxa_seguro_inadimplencia_pct', e.target.value ? parseFloat(e.target.value) : null)}
                                    className="bg-white border-[#D8DCDA]"
                                />
                                <p className="mt-1 text-[10px] text-[#8A918E]">Percentual adicional para cobrir inadimplência</p>
                                <InputError message={errors.taxa_seguro_inadimplencia_pct} />
                            </div>
                        )}
                    </div>

                    {/* Simulação de repasse */}
                    {form.valor_aluguel && form.valor_aluguel > 0 && form.taxa_administracao_pct != null && (
                        <SimulacaoRepasse
                            valorAluguel={form.valor_aluguel}
                            taxaAdministracaoPct={form.taxa_administracao_pct}
                            taxaSeguroInadimplenciaPct={form.taxa_seguro_inadimplencia_pct}
                            modeloRepasse={form.modelo_repasse}
                            titulares={titulares}
                        />
                    )}
                </div>
            </div>

            {/* Seção 4 — Multas, juros e descontos */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Multas, juros e descontos</h2>
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <Label>Multa por atraso (%)</Label>
                            <Input type="number" min={0} max={100} step={0.01}
                                value={form.multa_atraso_pct ?? ''}
                                onChange={(e) => setField('multa_atraso_pct', e.target.value ? parseFloat(e.target.value) : null)}
                                className="bg-white border-[#D8DCDA]" />
                            <InputError message={errors.multa_atraso_pct} />
                        </div>
                        <div>
                            <Label>Juros por dia (%)</Label>
                            <Input type="number" min={0} max={10} step={0.0001}
                                value={form.juros_atraso_pct_dia ?? ''}
                                onChange={(e) => setField('juros_atraso_pct_dia', e.target.value ? parseFloat(e.target.value) : null)}
                                className="bg-white border-[#D8DCDA]" />
                            <p className="mt-1 text-[10px] text-[#8A918E]">0.0333% ao dia ≈ 1% ao mês</p>
                            <InputError message={errors.juros_atraso_pct_dia} />
                        </div>
                        <div>
                            <Label>Dias de carência</Label>
                            <Input type="number" min={0} step={1}
                                value={form.dias_carencia ?? ''}
                                onChange={(e) => setField('dias_carencia', e.target.value ? parseInt(e.target.value) : null)}
                                className="bg-white border-[#D8DCDA]" />
                            <InputError message={errors.dias_carencia} />
                        </div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Multa rescisória (%) <span className="text-[#8A918E]">— opcional</span></Label>
                            <Input type="number" min={0} max={100} step={0.01}
                                value={form.multa_rescisoria_pct ?? ''}
                                onChange={(e) => setField('multa_rescisoria_pct', e.target.value ? parseFloat(e.target.value) : null)}
                                className="bg-white border-[#D8DCDA]" />
                            <InputError message={errors.multa_rescisoria_pct} />
                        </div>
                        <div>
                            <Label>Desconto pontualidade (%) <span className="text-[#8A918E]">— opcional</span></Label>
                            <Input type="number" min={0} max={100} step={0.01}
                                value={form.desconto_pontualidade_pct ?? ''}
                                onChange={(e) => setField('desconto_pontualidade_pct', e.target.value ? parseFloat(e.target.value) : null)}
                                className="bg-white border-[#D8DCDA]" />
                            <InputError message={errors.desconto_pontualidade_pct} />
                        </div>
                    </div>
                </div>
            </div>

            {/* Seção 5 — Garantia */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Garantia locatícia</h2>
                <div className="space-y-4">
                    <div>
                        <Label>Tipo de garantia</Label>
                        <Select value={form.tipo_garantia} onValueChange={(v) => setField('tipo_garantia', v)}>
                            <SelectTrigger className="bg-white border-[#D8DCDA]">
                                <SelectValue placeholder="Selecione" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="caucao">Caução</SelectItem>
                                <SelectItem value="fiador">Fiador</SelectItem>
                                <SelectItem value="seguro_fianca">Seguro fiança</SelectItem>
                                <SelectItem value="titulo_capitalizacao">Título de capitalização</SelectItem>
                                <SelectItem value="sem_garantia">Sem garantia</SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError message={errors.tipo_garantia} />
                    </div>

                    {/* Campos condicionais por tipo */}
                    {form.tipo_garantia === 'caucao' && (
                        <div className="max-w-sm">
                            <Label>Valor da caução</Label>
                            <InputMoeda value={form.garantia_valor} onChange={(v) => setField('garantia_valor', v)} />
                            {form.valor_aluguel && form.valor_aluguel > 0 && (
                                <p className="mt-1 text-[10px] text-[#8A918E]">
                                    Sugestão: 1 a 3 meses ({formataMoeda(form.valor_aluguel)} a {formataMoeda(form.valor_aluguel * 3)})
                                </p>
                            )}
                            <InputError message={errors.garantia_valor} />
                        </div>
                    )}

                    {form.tipo_garantia === 'fiador' && !modoEdicao && (
                        <div className="rounded-md bg-[#F7F8F7] p-3 text-xs text-[#8A918E]">
                            <FileText className="mb-1 inline h-3.5 w-3.5" /> Os dados do fiador serão cadastrados após salvar o contrato.
                        </div>
                    )}

                    {form.tipo_garantia === 'seguro_fianca' && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Seguradora</Label>
                                <Input value={form.garantia_seguradora} onChange={(e) => setField('garantia_seguradora', e.target.value)} className="bg-white border-[#D8DCDA]" />
                                <InputError message={errors.garantia_seguradora} />
                            </div>
                            <div>
                                <Label>Nº da apólice</Label>
                                <Input value={form.garantia_numero_apolice} onChange={(e) => setField('garantia_numero_apolice', e.target.value)} className="bg-white border-[#D8DCDA]" />
                                <InputError message={errors.garantia_numero_apolice} />
                            </div>
                            <div>
                                <Label>Valor do prêmio</Label>
                                <InputMoeda value={form.garantia_valor} onChange={(v) => setField('garantia_valor', v)} />
                                <InputError message={errors.garantia_valor} />
                            </div>
                            <div />
                            <div>
                                <Label>Vigência início</Label>
                                <Input type="date" value={form.garantia_data_inicio} onChange={(e) => setField('garantia_data_inicio', e.target.value)} className="bg-white border-[#D8DCDA]" />
                            </div>
                            <div>
                                <Label>Vigência fim</Label>
                                <Input type="date" value={form.garantia_data_fim} onChange={(e) => setField('garantia_data_fim', e.target.value)} className="bg-white border-[#D8DCDA]" />
                            </div>
                        </div>
                    )}

                    {form.tipo_garantia === 'titulo_capitalizacao' && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Nº do título</Label>
                                <Input value={form.garantia_numero_titulo} onChange={(e) => setField('garantia_numero_titulo', e.target.value)} className="bg-white border-[#D8DCDA]" />
                                <InputError message={errors.garantia_numero_titulo} />
                            </div>
                            <div>
                                <Label>Valor do título</Label>
                                <InputMoeda value={form.garantia_valor} onChange={(v) => setField('garantia_valor', v)} />
                                <InputError message={errors.garantia_valor} />
                            </div>
                            <div>
                                <Label>Vigência início</Label>
                                <Input type="date" value={form.garantia_data_inicio} onChange={(e) => setField('garantia_data_inicio', e.target.value)} className="bg-white border-[#D8DCDA]" />
                            </div>
                            <div>
                                <Label>Vigência fim</Label>
                                <Input type="date" value={form.garantia_data_fim} onChange={(e) => setField('garantia_data_fim', e.target.value)} className="bg-white border-[#D8DCDA]" />
                            </div>
                        </div>
                    )}

                    {form.tipo_garantia === 'sem_garantia' && (
                        <div className="rounded-md bg-[#FFF4E5] p-3 text-xs text-[#8C5A10]">
                            Este contrato não possui garantia locatícia. Recomenda-se exigir ao menos uma forma de garantia.
                        </div>
                    )}
                </div>
            </div>

            {/* Seção 6 — Observações */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Observações</h2>
                <textarea
                    value={form.observacoes}
                    onChange={(e) => setField('observacoes', e.target.value)}
                    placeholder="Notas internas, cláusulas especiais, condições particulares..."
                    rows={4}
                    maxLength={5000}
                    className="w-full rounded-md border border-[#D8DCDA] bg-white px-3 py-2 text-sm placeholder:text-muted-foreground focus:border-[#0A4F5C] focus:outline-none focus:ring-1 focus:ring-[#0A4F5C]"
                />
                <InputError message={errors.observacoes} />
            </div>

            {/* Placeholders */}
            {!modoEdicao && (
                <>
                    <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5 opacity-60">
                        <div className="flex items-center gap-3 text-[#8A918E]">
                            <ClipboardList className="h-5 w-5" />
                            <div>
                                <p className="text-sm font-medium">Responsabilidades</p>
                                <p className="text-xs">Salve o contrato para definir responsabilidades financeiras (IPTU, condomínio, etc.)</p>
                            </div>
                        </div>
                    </div>
                    {form.tipo_garantia === 'fiador' && (
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5 opacity-60">
                            <div className="flex items-center gap-3 text-[#8A918E]">
                                <Users className="h-5 w-5" />
                                <div>
                                    <p className="text-sm font-medium">Fiadores</p>
                                    <p className="text-xs">Salve o contrato para cadastrar os dados do fiador</p>
                                </div>
                            </div>
                        </div>
                    )}
                </>
            )}

            {/* Rodapé */}
            <div className="flex items-center justify-end gap-3">
                <Button type="button" variant="outline" onClick={onCancel} className="border-[#D8DCDA]">Cancelar</Button>
                <Button type="submit" disabled={processing} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                    {processing && <Spinner />}
                    {textoBotao}
                </Button>
            </div>
        </form>
    );
}
