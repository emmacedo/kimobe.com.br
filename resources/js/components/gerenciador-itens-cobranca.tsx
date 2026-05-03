import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { InputMoeda } from '@/components/input-moeda';
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
import { getCsrfToken } from '@/lib/csrf';
import { formataMoeda } from '@/lib/utils';
import type {
    EntidadeExterna,
    ItemCobranca,
    ParteFinanceira,
    PeriodicidadeItemCobranca,
    TipoItemCobranca,
} from '@/types/models';

type Props = {
    contratoId: number;
    itensIniciais: ItemCobranca[];
    entidadesExternas: EntidadeExterna[];
    readOnly?: boolean;
};

type EscopoEdicao = 'somente' | 'futuras' | 'todas';

const ROTULOS_PARTE: Record<ParteFinanceira, string> = {
    inquilino: 'Inquilino',
    proprietario: 'Proprietário',
    administradora: 'Administradora',
};

const ROTULOS_TIPO: Record<TipoItemCobranca, string> = {
    recorrente: 'Recorrente',
    parcelado: 'Parcelado',
    avulso: 'Avulso',
};

const ROTULOS_PERIODICIDADE: Record<PeriodicidadeItemCobranca, string> = {
    mensal: 'Mensal',
    bimestral: 'Bimestral',
    trimestral: 'Trimestral',
    semestral: 'Semestral',
    anual: 'Anual',
};

type FormState = {
    descricao: string;
    pagante: ParteFinanceira;
    recebedor: ParteFinanceira;
    entidade_externa_id: number | null;
    tipo: TipoItemCobranca;
    periodicidade: PeriodicidadeItemCobranca;
    num_parcelas_total: number;
    valor_unitario: number | null;
    mes_referencia: string;
    visivel_inquilino: boolean;
    observacoes: string;
};

type AbaVigencia = 'vigentes' | 'passados';

function mesRefParaInt(mesRef: string): number | null {
    const partes = mesRef.split('/');
    if (partes.length !== 2) return null;
    const mes = Number(partes[0]);
    const ano = Number(partes[1]);
    if (!Number.isFinite(mes) || !Number.isFinite(ano)) return null;
    return ano * 100 + mes;
}

function hojeAnoMesInt(): number {
    const d = new Date();
    return d.getFullYear() * 100 + (d.getMonth() + 1);
}

// Converte "MM/YYYY" (formato do backend) para "YYYY-MM" (formato do <input type="month">)
function mesRefParaIsoMonth(mesRef: string): string {
    const partes = mesRef.split('/');
    if (partes.length !== 2) return '';
    const [mes, ano] = partes;
    if (!mes || !ano) return '';
    return `${ano}-${mes.padStart(2, '0')}`;
}

// Converte "YYYY-MM" (formato do <input type="month">) para "MM/YYYY" (formato do backend)
function isoMonthParaMesRef(iso: string): string {
    if (!iso) return '';
    const partes = iso.split('-');
    if (partes.length !== 2) return '';
    const [ano, mes] = partes;
    return `${mes}/${ano}`;
}

export function isItemVigente(item: ItemCobranca): boolean {
    if (item.status === 'cancelado') return false;
    if (item.tipo === 'recorrente') return true;

    const inicio = mesRefParaInt(item.mes_referencia);
    if (inicio === null) return false;
    const hoje = hojeAnoMesInt();

    if (item.tipo === 'avulso') {
        return inicio >= hoje;
    }

    if (item.tipo === 'parcelado' && item.num_parcelas_total) {
        // Última parcela: mes_referencia + (num_parcelas_total - 1) meses, com overflow correto via Date
        const mesIni = inicio % 100;
        const anoIni = Math.floor(inicio / 100);
        const dataFim = new Date(anoIni, mesIni - 1 + item.num_parcelas_total - 1, 1);
        const fim = dataFim.getFullYear() * 100 + (dataFim.getMonth() + 1);
        return fim >= hoje;
    }

    return true;
}

const FORM_INICIAL: FormState = {
    descricao: '',
    pagante: 'inquilino',
    recebedor: 'proprietario',
    entidade_externa_id: null,
    tipo: 'recorrente',
    periodicidade: 'mensal',
    num_parcelas_total: 12,
    valor_unitario: null,
    mes_referencia: '',
    visivel_inquilino: true,
    observacoes: '',
};

export function GerenciadorItensCobranca({ contratoId, itensIniciais, entidadesExternas, readOnly = false }: Props) {
    const [itens, setItens] = useState<ItemCobranca[]>(itensIniciais);
    const [criarOpen, setCriarOpen] = useState(false);
    const [form, setForm] = useState<FormState>(FORM_INICIAL);
    const [saving, setSaving] = useState(false);
    const [editTarget, setEditTarget] = useState<ItemCobranca | null>(null);
    const [editEscopo, setEditEscopo] = useState<EscopoEdicao>('futuras');
    const [editValor, setEditValor] = useState<number | null>(null);
    const [editSaving, setEditSaving] = useState(false);
    const [cancelTarget, setCancelTarget] = useState<ItemCobranca | null>(null);
    const [cancelarSerie, setCancelarSerie] = useState(false);
    const [cancelLoading, setCancelLoading] = useState(false);
    const [aba, setAba] = useState<AbaVigencia>('vigentes');

    const { vigentes, passados } = useMemo(() => {
        const v: ItemCobranca[] = [];
        const p: ItemCobranca[] = [];
        for (const item of itens) {
            if (isItemVigente(item)) v.push(item);
            else p.push(item);
        }
        return { vigentes: v, passados: p };
    }, [itens]);

    const itensFiltrados = aba === 'vigentes' ? vigentes : passados;

    function setF<K extends keyof FormState>(k: K, v: FormState[K]) {
        setForm((p) => ({ ...p, [k]: v }));
    }

    async function handleCriar() {
        if (!form.descricao.trim() || !form.mes_referencia || !form.valor_unitario) {
            toast.error('Preencha descrição, mês de referência e valor.');
            return;
        }

        const payload: Record<string, unknown> = {
            descricao: form.descricao,
            pagante: form.pagante,
            recebedor: form.recebedor,
            entidade_externa_id: form.entidade_externa_id,
            tipo: form.tipo,
            valor_unitario: form.valor_unitario,
            mes_referencia: form.mes_referencia,
            visivel_inquilino: form.visivel_inquilino,
            observacoes: form.observacoes || null,
        };

        if (form.tipo === 'recorrente') {
            payload.periodicidade = form.periodicidade;
        } else if (form.tipo === 'parcelado') {
            payload.num_parcelas_total = form.num_parcelas_total;
        }

        setSaving(true);
        try {
            const r = await fetch(`/contratos/${contratoId}/itens-cobranca`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify(payload),
            });
            if (!r.ok) {
                const err = await r.json().catch(() => ({}));
                toast.error(err?.message ?? 'Erro ao criar item.');
                return;
            }
            const novo: ItemCobranca = await r.json();
            setItens((prev) => [novo, ...prev]);
            // Se o item criado não está na aba ativa, troca pra mostrar onde ele caiu
            const abaDoNovo: AbaVigencia = isItemVigente(novo) ? 'vigentes' : 'passados';
            if (abaDoNovo !== aba) setAba(abaDoNovo);
            setForm(FORM_INICIAL);
            setCriarOpen(false);
            toast.success('Item criado.');
        } catch {
            toast.error('Erro ao criar item.');
        } finally {
            setSaving(false);
        }
    }

    async function handleEditar() {
        if (!editTarget || editValor === null) return;

        setEditSaving(true);
        try {
            const r = await fetch(`/itens-cobranca/${editTarget.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ escopo: editEscopo, valor_unitario: editValor }),
            });
            if (!r.ok) {
                const err = await r.json().catch(() => ({}));
                toast.error(err?.message ?? 'Erro ao atualizar.');
                return;
            }
            const data = await r.json();
            toast.success(`${data.atualizadas} ocorrência(s) atualizada(s).`);
            setEditTarget(null);
            // Atualiza valor exibido na lista do pai (visualização imediata)
            setItens((prev) =>
                prev.map((i) => (i.id === editTarget.id ? { ...i, valor_unitario: String(editValor) } : i)),
            );
        } catch {
            toast.error('Erro ao atualizar.');
        } finally {
            setEditSaving(false);
        }
    }

    async function handleCancelar() {
        if (!cancelTarget) return;
        setCancelLoading(true);
        try {
            const r = await fetch(`/itens-cobranca/${cancelTarget.id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ escopo: cancelarSerie ? 'serie' : 'somente' }),
            });
            if (!r.ok) {
                toast.error('Erro ao cancelar.');
                return;
            }
            const data = await r.json();
            toast.success(`${data.canceladas} ocorrência(s) cancelada(s).`);
            if (cancelarSerie) {
                setItens((prev) => prev.filter((i) => i.id !== cancelTarget.id));
            } else {
                setItens((prev) =>
                    prev.map((i) => (i.id === cancelTarget.id ? { ...i, status: 'cancelado' } : i)),
                );
            }
            setCancelTarget(null);
            setCancelarSerie(false);
        } catch {
            toast.error('Erro ao cancelar.');
        } finally {
            setCancelLoading(false);
        }
    }

    function abrirEditar(item: ItemCobranca) {
        setEditTarget(item);
        setEditValor(parseFloat(item.valor_unitario));
        setEditEscopo('futuras');
    }

    function descricaoCompleta(item: ItemCobranca): string {
        if (item.tipo === 'parcelado' && item.num_parcelas_total) {
            return `${item.descricao} (parcelado em ${item.num_parcelas_total}x)`;
        }
        if (item.tipo === 'recorrente' && item.periodicidade) {
            return `${item.descricao} (${ROTULOS_PERIODICIDADE[item.periodicidade].toLowerCase()})`;
        }
        return item.descricao;
    }

    return (
        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
            <div className="mb-4 flex items-center justify-between gap-3">
                <h2 className="text-sm font-medium text-[#1E2D30]">Itens de cobrança</h2>
                {!readOnly && (
                    <Button variant="outline" size="sm" onClick={() => setCriarOpen(true)} className="border-[#D8DCDA]">
                        <Plus className="mr-1 h-3.5 w-3.5" />
                        Novo item
                    </Button>
                )}
            </div>

            <div className="mb-3 flex gap-1 border-b border-[#EEF0EF]">
                <button
                    type="button"
                    onClick={() => setAba('vigentes')}
                    className={`relative -mb-px px-3 py-1.5 text-xs font-medium transition-colors ${
                        aba === 'vigentes'
                            ? 'border-b-2 border-[#0A4F5C] text-[#0A4F5C]'
                            : 'border-b-2 border-transparent text-[#6B7370] hover:text-[#1E2D30]'
                    }`}
                >
                    Vigentes <span className="ml-1 text-[10px] text-[#8A918E]">({vigentes.length})</span>
                </button>
                <button
                    type="button"
                    onClick={() => setAba('passados')}
                    className={`relative -mb-px px-3 py-1.5 text-xs font-medium transition-colors ${
                        aba === 'passados'
                            ? 'border-b-2 border-[#0A4F5C] text-[#0A4F5C]'
                            : 'border-b-2 border-transparent text-[#6B7370] hover:text-[#1E2D30]'
                    }`}
                >
                    Passados <span className="ml-1 text-[10px] text-[#8A918E]">({passados.length})</span>
                </button>
            </div>

            {itensFiltrados.length === 0 ? (
                <p className="text-sm text-[#8A918E]">
                    {aba === 'vigentes'
                        ? 'Nenhum item vigente. Use "Novo item" para cadastrar aluguel, condomínio, IPTU, etc.'
                        : 'Nenhum item passado.'}
                </p>
            ) : (
                <div className="space-y-2">
                    {itensFiltrados.map((item) => (
                        <div
                            key={item.id}
                            className="flex items-center justify-between rounded-md border border-[#EEF0EF] px-3 py-2 text-sm"
                        >
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <p className="truncate font-medium text-[#1E2D30]">{descricaoCompleta(item)}</p>
                                    <Badge variant="outline" className="text-[9px]">{ROTULOS_TIPO[item.tipo]}</Badge>
                                    {item.status === 'cancelado' && (
                                        <Badge variant="outline" className="bg-[#FDECEC] text-[10px] text-[#A83232]">Cancelado</Badge>
                                    )}
                                </div>
                                <p className="text-xs text-[#6B7370]">
                                    {ROTULOS_PARTE[item.pagante]} → {ROTULOS_PARTE[item.recebedor]}
                                    {item.entidade_externa && ` (via ${item.entidade_externa.nome})`}
                                    {' · início '}{item.mes_referencia}
                                </p>
                            </div>
                            <div className="flex items-center gap-3 shrink-0">
                                <span className="font-mono text-sm font-medium text-[#1E2D30]">{formataMoeda(item.valor_unitario)}</span>
                                {!readOnly && item.status !== 'cancelado' && (
                                    <>
                                        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => abrirEditar(item)} aria-label="Editar">
                                            <Pencil className="h-3.5 w-3.5 text-[#6B7370]" />
                                        </Button>
                                        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setCancelTarget(item)} aria-label="Cancelar">
                                            <Trash2 className="h-3.5 w-3.5 text-[#A83232]" />
                                        </Button>
                                    </>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {/* Dialog: criar */}
            <Dialog open={criarOpen} onOpenChange={setCriarOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Novo item de cobrança</DialogTitle>
                        <DialogDescription>
                            Cadastre aluguel, condomínio, IPTU, frete, multa, etc. Itens recorrentes e parcelados são pré-gerados em todas as ocorrências.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="grid gap-3">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <Label>Descrição</Label>
                                <Input value={form.descricao} onChange={(e) => setF('descricao', e.target.value)} placeholder="Ex: Aluguel" className="bg-white border-[#D8DCDA]" />
                            </div>
                            <div>
                                <Label>Primeiro mês de cobrança</Label>
                                <Input
                                    type="month"
                                    value={mesRefParaIsoMonth(form.mes_referencia)}
                                    onChange={(e) => setF('mes_referencia', isoMonthParaMesRef(e.target.value))}
                                    className="bg-white border-[#D8DCDA]"
                                />
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-3">
                            <div>
                                <Label>Pagante</Label>
                                <Select value={form.pagante} onValueChange={(v) => setF('pagante', v as ParteFinanceira)}>
                                    <SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="inquilino">Inquilino</SelectItem>
                                        <SelectItem value="proprietario">Proprietário</SelectItem>
                                        <SelectItem value="administradora">Administradora</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>Recebedor</Label>
                                <Select value={form.recebedor} onValueChange={(v) => setF('recebedor', v as ParteFinanceira)}>
                                    <SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="inquilino">Inquilino</SelectItem>
                                        <SelectItem value="proprietario">Proprietário</SelectItem>
                                        <SelectItem value="administradora">Administradora</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>Entidade externa (opcional)</Label>
                                <Select
                                    value={form.entidade_externa_id?.toString() ?? '__nenhuma__'}
                                    onValueChange={(v) => setF('entidade_externa_id', v === '__nenhuma__' ? null : parseInt(v))}
                                >
                                    <SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue placeholder="Nenhuma" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__nenhuma__">Nenhuma</SelectItem>
                                        {entidadesExternas.map((e) => (
                                            <SelectItem key={e.id} value={String(e.id)}>{e.nome}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-3">
                            <div>
                                <Label>Tipo</Label>
                                <Select value={form.tipo} onValueChange={(v) => setF('tipo', v as TipoItemCobranca)}>
                                    <SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="recorrente">Recorrente</SelectItem>
                                        <SelectItem value="parcelado">Parcelado</SelectItem>
                                        <SelectItem value="avulso">Avulso</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            {form.tipo === 'recorrente' && (
                                <div>
                                    <Label>Periodicidade</Label>
                                    <Select value={form.periodicidade} onValueChange={(v) => setF('periodicidade', v as PeriodicidadeItemCobranca)}>
                                        <SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="mensal">Mensal</SelectItem>
                                            <SelectItem value="bimestral">Bimestral</SelectItem>
                                            <SelectItem value="trimestral">Trimestral</SelectItem>
                                            <SelectItem value="semestral">Semestral</SelectItem>
                                            <SelectItem value="anual">Anual</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                            {form.tipo === 'parcelado' && (
                                <div>
                                    <Label>Nº de parcelas</Label>
                                    <Input
                                        type="number"
                                        min={1}
                                        max={360}
                                        value={form.num_parcelas_total}
                                        onChange={(e) => setF('num_parcelas_total', parseInt(e.target.value) || 1)}
                                        className="bg-white border-[#D8DCDA]"
                                    />
                                </div>
                            )}
                            <div>
                                <Label>Valor por ocorrência</Label>
                                <InputMoeda value={form.valor_unitario} onChange={(v) => setF('valor_unitario', v)} />
                            </div>
                        </div>

                        <div>
                            <Label>Observações (opcional)</Label>
                            <textarea
                                value={form.observacoes}
                                onChange={(e) => setF('observacoes', e.target.value)}
                                rows={2}
                                className="w-full rounded-md border border-[#D8DCDA] bg-white px-3 py-2 text-sm focus:border-[#0A4F5C] focus:outline-none focus:ring-1 focus:ring-[#0A4F5C]"
                            />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCriarOpen(false)} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button onClick={handleCriar} disabled={saving} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                            {saving && <Spinner />}
                            Cadastrar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Dialog: editar */}
            <Dialog open={!!editTarget} onOpenChange={(open) => !open && setEditTarget(null)}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Editar valor</DialogTitle>
                        <DialogDescription>
                            {editTarget?.descricao} — escolha o escopo da alteração.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-3">
                        <div>
                            <Label>Novo valor</Label>
                            <InputMoeda value={editValor} onChange={setEditValor} />
                        </div>
                        <div>
                            <Label>Escopo</Label>
                            <Select value={editEscopo} onValueChange={(v) => setEditEscopo(v as EscopoEdicao)}>
                                <SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="somente">Somente esta ocorrência</SelectItem>
                                    <SelectItem value="futuras">Esta e as futuras pendentes</SelectItem>
                                    <SelectItem value="todas">Todas as ocorrências pendentes</SelectItem>
                                </SelectContent>
                            </Select>
                            <p className="mt-1 text-xs text-[#8A918E]">
                                Itens já conciliados em faturas fechadas nunca são alterados.
                            </p>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setEditTarget(null)} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button onClick={handleEditar} disabled={editSaving} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                            {editSaving && <Spinner />}
                            Salvar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Confirm cancelar */}
            <ConfirmDialog
                open={!!cancelTarget}
                onOpenChange={(open) => !open && (setCancelTarget(null), setCancelarSerie(false))}
                titulo="Cancelar item de cobrança"
                descricao={
                    cancelTarget ? (
                        <div className="space-y-3">
                            <p>
                                Cancelar <strong>{cancelTarget.descricao}</strong> ({cancelTarget.mes_referencia})?
                            </p>
                            <label className="flex items-center gap-2 text-sm text-[#3A4240]">
                                <input
                                    type="checkbox"
                                    checked={cancelarSerie}
                                    onChange={(e) => setCancelarSerie(e.target.checked)}
                                    className="h-4 w-4 rounded border-[#D8DCDA]"
                                />
                                Cancelar a série inteira (todas as ocorrências pendentes)
                            </label>
                        </div>
                    ) : ''
                }
                textoConfirmar={cancelarSerie ? 'Cancelar série' : 'Cancelar ocorrência'}
                variante="destructive"
                loading={cancelLoading}
                onConfirm={handleCancelar}
            />
        </div>
    );
}
