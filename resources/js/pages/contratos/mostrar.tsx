import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Building2, Calendar, Camera, ChevronDown, ChevronRight, History, Info, Landmark, Pencil, TrendingUp } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { usePermissions } from '@/hooks/use-permissions';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FiadorDetalhesDialog } from '@/components/fiador-detalhes-dialog';
import { GerenciadorItensCobranca } from '@/components/gerenciador-itens-cobranca';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formataMoeda } from '@/lib/utils';
import type { EntidadeExterna, ItemCobranca } from '@/types/models';

// Labels
const indiceLabels: Record<string, string> = { igpm: 'IGPM', ipca: 'IPCA', fixo: 'Fixo', manual: 'Manual' };
const garantiaTipoLabels: Record<string, string> = { caucao: 'Caução', fiador: 'Fiador', seguro_fianca: 'Seguro fiança', titulo_capitalizacao: 'Título de capitalização', sem_garantia: 'Sem garantia' };
const papelLabels: Record<string, string> = { responsavel: 'Responsável', observador: 'Observador' };
const reajusteOrigemLabels: Record<string, string> = {
    reajuste_anual: 'Reajuste anual',
    aditivo: 'Aditivo',
    renegociacao: 'Renegociação',
    correcao: 'Correção',
};

function formatDate(d: string): string {
    return new Date(d).toLocaleDateString('pt-BR');
}

function cpfParcial(cpf: string): string {
    return cpf.length > 6 ? `***${cpf.slice(-6)}` : cpf;
}

type EventoAuditoria = {
    id: string;
    tipo: 'reajuste' | 'alteracao' | 'atividade';
    data: string;
    titulo: string;
    subtitulo?: string;
    usuario?: string | null;
    valor_anterior?: any;
    valor_novo?: any;
    properties?: any;
    extra?: Record<string, any>;
};

type Props = {
    contrato: any;
    faturasRecentes: any[];
    contatoAdmin?: { nome: string; email: string } | null;
    timelineAuditoria?: EventoAuditoria[] | null;
    itensCobranca?: ItemCobranca[] | null;
    entidadesExternas?: EntidadeExterna[] | null;
};

export default function MostrarContrato({ contrato, faturasRecentes, contatoAdmin, timelineAuditoria, itensCobranca, entidadesExternas }: Props) {
    const { flash } = usePage().props as any;
    const { can, isInquilino, isAdmin } = usePermissions();
    const [actionTarget, setActionTarget] = useState<'encerrar' | 'cancelar' | null>(null);
    const [actionLoading, setActionLoading] = useState(false);
    const [fiadorDialog, setFiadorDialog] = useState<any>(null);
    const [reajusteOpen, setReajusteOpen] = useState(false);
    const [timelineFiltro, setTimelineFiltro] = useState<'todos' | 'reajuste' | 'alteracao' | 'atividade'>('todos');
    const [timelineExpanded, setTimelineExpanded] = useState<Set<string>>(new Set());

    const reajusteForm = useForm({
        valor_novo: '',
        data_aplicacao: '',
        indice_usado: contrato.indice_reajuste === 'fixo' ? 'fixo' : (contrato.indice_reajuste ?? 'manual'),
        origem: 'reajuste_anual',
        observacao: '',
    });

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    function abrirReajuste() {
        reajusteForm.reset();
        reajusteForm.setData({
            valor_novo: '',
            data_aplicacao: '',
            indice_usado: contrato.indice_reajuste === 'fixo' ? 'fixo' : (contrato.indice_reajuste ?? 'manual'),
            origem: 'reajuste_anual',
            observacao: '',
        });
        setReajusteOpen(true);
    }

    function aplicarReajuste(e: React.FormEvent) {
        e.preventDefault();
        reajusteForm.post(`/contratos/${contrato.id}/reajustes`, {
            preserveScroll: true,
            onSuccess: () => setReajusteOpen(false),
        });
    }

    function toggleTimelineDetalhe(id: string) {
        setTimelineExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    }

    const timelineFiltrada = (timelineAuditoria ?? []).filter((e) =>
        timelineFiltro === 'todos' ? true : e.tipo === timelineFiltro
    );

    function formatDateTime(iso: string): string {
        const d = new Date(iso);
        return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
    }

    const tipoLabels: Record<string, string> = { reajuste: 'Reajuste', alteracao: 'Alteração contratual', atividade: 'Atividade' };
    const tipoCores: Record<string, string> = {
        reajuste: 'bg-[#FBF6E8] text-[#6B5420] border-[#E6D9B0]',
        alteracao: 'bg-[#E8F4F6] text-[#0A4F5C] border-[#B7DDE3]',
        atividade: 'bg-[#F7F8F7] text-[#3A4240] border-[#D8DCDA]',
    };

    const imovel = contrato.imovel;
    const inquilino = contrato.inquilino;
    const titulo = imovel.complemento || `${imovel.logradouro}, ${imovel.numero}`;

    function handleAction() {
        if (!actionTarget) return;
        setActionLoading(true);
        router.patch(`/contratos/${contrato.id}/${actionTarget}`, {}, {
            onFinish: () => { setActionLoading(false); setActionTarget(null); },
        });
    }

    return (
        <>
            <Head title={`Contrato — ${titulo}`} />
            <div className="space-y-4">
                {/* Header */}
                <PageHeader titulo={`Contrato — ${titulo}`}>
                    <div className="flex items-center gap-2">
                        <StatusBadge status={contrato.status} tipo="contrato" />
                        {can.manage_contratos && (
                            <>
                                <Button variant="outline" size="sm" asChild className="border-[#D8DCDA]">
                                    <Link href={`/contratos/${contrato.id}/editar`}>
                                        <Pencil className="mr-1 h-3.5 w-3.5" />
                                        Editar
                                    </Link>
                                </Button>
                                {contrato.status === 'ativo' && (
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="outline" size="sm" className="border-[#D8DCDA]">Ações</Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem onClick={() => setActionTarget('encerrar')}>Encerrar contrato</DropdownMenuItem>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem className="text-[#A83232]" onClick={() => setActionTarget('cancelar')}>Cancelar contrato</DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                )}
                            </>
                        )}
                    </div>
                </PageHeader>

                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Coluna principal */}
                    <div className="space-y-4 lg:col-span-2">
                        {/* Dados do contrato */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Dados do contrato</p>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <InfoItem label="Imóvel">
                                    <Link href={`/imoveis/${imovel.id}`} className="text-sm font-medium text-[#0A4F5C] hover:underline">
                                        {titulo}
                                    </Link>
                                </InfoItem>
                                <InfoItem label="Inquilino" value={inquilino.user.name} />
                                <InfoItem label="Vigência" value={`${formatDate(contrato.data_inicio)} — ${formatDate(contrato.data_fim)}`} />
                                <InfoItem label="Dia de vencimento" value={`Dia ${contrato.dia_vencimento}`} />
                                <InfoItem label="Valor do aluguel" value={formataMoeda(contrato.valor_aluguel)} className="font-mono font-medium" />
                                <InfoItem label="Índice de reajuste" value={`${indiceLabels[contrato.indice_reajuste] ?? contrato.indice_reajuste} — Mês ${contrato.mes_reajuste}`} />
                            </div>
                        </div>

                        {/* Modelo de repasse — oculto para inquilinos */}
                        {!isInquilino && (
                            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                                <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Modelo de repasse</p>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <InfoItem label="Modelo">
                                        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${contrato.modelo_repasse === 'garantido' ? 'bg-[#FBF6E8] text-[#6B5420]' : 'bg-[#F7F8F7] text-[#6B7370]'}`}>
                                            {contrato.modelo_repasse === 'garantido' ? 'Garantido' : 'Por recebimento'}
                                        </span>
                                    </InfoItem>
                                    <InfoItem label="Taxa de administração" value={`${parseFloat(contrato.taxa_administracao_pct).toFixed(2)}%`} />
                                    {contrato.modelo_repasse === 'garantido' && contrato.taxa_seguro_inadimplencia_pct && (
                                        <InfoItem label="Seguro inadimplência" value={`${parseFloat(contrato.taxa_seguro_inadimplencia_pct).toFixed(2)}%`} />
                                    )}
                                </div>
                                {contrato.modelo_repasse === 'garantido' && (
                                    <div className="mt-3 flex items-start gap-2 rounded-md bg-[#E8F4F6] p-3 text-xs text-[#0A4F5C]">
                                        <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                        O proprietário recebe o repasse na data fixa, independente do pagamento do inquilino.
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Multas e juros */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Multas e juros</p>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                <InfoItem label="Multa por atraso" value={`${parseFloat(contrato.multa_atraso_pct).toFixed(2)}%`} />
                                <InfoItem label="Juros por dia" value={`${parseFloat(contrato.juros_atraso_pct_dia).toFixed(4)}%`} />
                                <InfoItem label="Dias de carência" value={`${contrato.dias_carencia} dia(s)`} />
                                {contrato.desconto_pontualidade_pct && (
                                    <InfoItem label="Desconto pontualidade" value={`${parseFloat(contrato.desconto_pontualidade_pct).toFixed(2)}%`} />
                                )}
                                {contrato.multa_rescisoria_pct && (
                                    <InfoItem label="Multa rescisória" value={`${parseFloat(contrato.multa_rescisoria_pct).toFixed(2)}%`} />
                                )}
                            </div>
                        </div>

                        {/* Reajustes — oculto para inquilinos */}
                        {!isInquilino && (
                            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                                <div className="mb-3 flex items-center justify-between">
                                    <p className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Reajustes</p>
                                    {can.manage_contratos && contrato.status === 'ativo' && (
                                        <Button size="sm" variant="outline" className="border-[#D8DCDA]" onClick={abrirReajuste}>
                                            <TrendingUp className="mr-1 h-3.5 w-3.5" />
                                            Aplicar reajuste
                                        </Button>
                                    )}
                                </div>
                                {contrato.reajustes && contrato.reajustes.length > 0 ? (
                                    <Table>
                                        <TableHeader>
                                            <TableRow className="border-[#EEF0EF] hover:bg-transparent">
                                                <TableHead className="text-[11px] uppercase text-[#8A918E]">Aplicação</TableHead>
                                                <TableHead className="text-[11px] uppercase text-[#8A918E]">Origem</TableHead>
                                                <TableHead className="text-[11px] uppercase text-[#8A918E]">Índice</TableHead>
                                                <TableHead className="text-right text-[11px] uppercase text-[#8A918E]">Anterior</TableHead>
                                                <TableHead className="text-right text-[11px] uppercase text-[#8A918E]">Novo</TableHead>
                                                <TableHead className="text-right text-[11px] uppercase text-[#8A918E]">%</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {contrato.reajustes.map((r: any) => (
                                                <TableRow key={r.id} className="border-[#EEF0EF]">
                                                    <TableCell className="text-sm">{formatDate(r.data_aplicacao)}</TableCell>
                                                    <TableCell className="text-sm">{reajusteOrigemLabels[r.origem] ?? r.origem}</TableCell>
                                                    <TableCell className="text-sm">{indiceLabels[r.indice_usado] ?? r.indice_usado}</TableCell>
                                                    <TableCell className="text-right font-mono text-sm text-[#6B7370]">{formataMoeda(r.valor_anterior)}</TableCell>
                                                    <TableCell className="text-right font-mono text-sm font-medium">{formataMoeda(r.valor_novo)}</TableCell>
                                                    <TableCell className="text-right font-mono text-sm">{parseFloat(r.percentual).toFixed(2)}%</TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                ) : (
                                    <p className="text-sm text-[#8A918E]">Nenhum reajuste aplicado</p>
                                )}
                            </div>
                        )}

                        {/* Garantia */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Garantia</p>
                            <div className="mb-3 flex items-center gap-2">
                                <span className="inline-flex items-center rounded-full bg-[#F7F8F7] px-2.5 py-0.5 text-xs font-medium text-[#3A4240]">
                                    {garantiaTipoLabels[contrato.tipo_garantia] ?? contrato.tipo_garantia}
                                </span>
                                {contrato.garantia && <StatusBadge status={contrato.garantia.status} tipo="repasse" />}
                            </div>

                            {contrato.tipo_garantia === 'sem_garantia' && (
                                <p className="text-sm text-[#8A918E]">Contrato sem garantia locatícia</p>
                            )}

                            {contrato.garantia?.tipo === 'caucao' && (
                                <InfoItem label="Valor depositado" value={formataMoeda(contrato.garantia.valor)} />
                            )}

                            {contrato.garantia?.tipo === 'seguro_fianca' && (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <InfoItem label="Seguradora" value={contrato.garantia.seguradora} />
                                    <InfoItem label="Nº Apólice" value={contrato.garantia.numero_apolice} />
                                    <InfoItem label="Valor prêmio" value={formataMoeda(contrato.garantia.valor)} />
                                    <InfoItem label="Vigência" value={`${formatDate(contrato.garantia.data_inicio)} — ${formatDate(contrato.garantia.data_fim)}`} />
                                </div>
                            )}

                            {contrato.garantia?.tipo === 'titulo_capitalizacao' && (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <InfoItem label="Nº Título" value={contrato.garantia.numero_titulo} />
                                    <InfoItem label="Valor" value={formataMoeda(contrato.garantia.valor)} />
                                    <InfoItem label="Vigência" value={`${formatDate(contrato.garantia.data_inicio)} — ${formatDate(contrato.garantia.data_fim)}`} />
                                </div>
                            )}

                            {contrato.garantia?.tipo === 'fiador' && contrato.fiadores?.length > 0 && (
                                <div className="space-y-2">
                                    {contrato.fiadores.map((f: any) => (
                                        <div key={f.id} className="flex items-center justify-between rounded-md border border-[#EEF0EF] px-3 py-2.5">
                                            <div>
                                                <p className="text-sm font-medium text-[#1E2D30]">{f.nome}</p>
                                                <p className="text-xs text-[#8A918E]">CPF: {cpfParcial(f.cpf)} · {f.telefone}</p>
                                            </div>
                                            <Button variant="link" size="sm" className="h-auto p-0 text-xs text-[#0A4F5C]" onClick={() => setFiadorDialog(f)}>
                                                Ver dados completos
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Timeline de auditoria — admin/proprietário */}
                        {timelineAuditoria && (
                            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                    <p className="flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">
                                        <History className="h-3.5 w-3.5" />
                                        Histórico do contrato
                                    </p>
                                    <div className="flex flex-wrap gap-1.5">
                                        {(['todos', 'reajuste', 'alteracao', 'atividade'] as const).map((t) => (
                                            <button
                                                key={t}
                                                type="button"
                                                onClick={() => setTimelineFiltro(t)}
                                                className={`rounded-full border px-2.5 py-0.5 text-[11px] transition-colors ${
                                                    timelineFiltro === t
                                                        ? 'border-[#0A4F5C] bg-[#0A4F5C] text-white'
                                                        : 'border-[#D8DCDA] bg-white text-[#6B7370] hover:bg-[#F7F8F7]'
                                                }`}
                                            >
                                                {t === 'todos' ? 'Todos' : tipoLabels[t]}
                                            </button>
                                        ))}
                                    </div>
                                </div>

                                {timelineFiltrada.length === 0 ? (
                                    <p className="text-sm text-[#8A918E]">Nenhum evento registrado</p>
                                ) : (
                                    <ol className="space-y-2">
                                        {timelineFiltrada.map((e) => {
                                            const aberto = timelineExpanded.has(e.id);
                                            return (
                                                <li key={e.id} className="rounded-md border border-[#EEF0EF]">
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleTimelineDetalhe(e.id)}
                                                        className="flex w-full items-start gap-3 px-3 py-2.5 text-left hover:bg-[#FAFBFA]"
                                                    >
                                                        <span className={`mt-0.5 inline-flex shrink-0 items-center rounded-full border px-2 py-0.5 text-[10px] font-medium ${tipoCores[e.tipo]}`}>
                                                            {tipoLabels[e.tipo]}
                                                        </span>
                                                        <div className="min-w-0 flex-1">
                                                            <p className="truncate text-sm font-medium text-[#1E2D30]">{e.titulo}</p>
                                                            {e.subtitulo && (
                                                                <p className="mt-0.5 truncate text-xs text-[#6B7370]">{e.subtitulo}</p>
                                                            )}
                                                            <p className="mt-0.5 text-[11px] text-[#8A918E]">
                                                                {formatDateTime(e.data)}
                                                                {e.usuario && <> · por <span className="font-medium text-[#3A4240]">{e.usuario}</span></>}
                                                            </p>
                                                        </div>
                                                        {aberto ? (
                                                            <ChevronDown className="mt-0.5 h-4 w-4 shrink-0 text-[#8A918E]" />
                                                        ) : (
                                                            <ChevronRight className="mt-0.5 h-4 w-4 shrink-0 text-[#8A918E]" />
                                                        )}
                                                    </button>
                                                    {aberto && (
                                                        <div className="border-t border-[#EEF0EF] bg-[#FAFBFA] px-3 py-2.5">
                                                            {e.tipo === 'reajuste' && e.extra && (
                                                                <dl className="grid gap-1.5 text-xs sm:grid-cols-2">
                                                                    <div><dt className="text-[#8A918E]">Origem</dt><dd className="text-[#1E2D30]">{reajusteOrigemLabels[e.extra.origem] ?? e.extra.origem}</dd></div>
                                                                    <div><dt className="text-[#8A918E]">Índice</dt><dd className="text-[#1E2D30]">{indiceLabels[e.extra.indice_usado] ?? e.extra.indice_usado}</dd></div>
                                                                    <div><dt className="text-[#8A918E]">Aplicação</dt><dd className="text-[#1E2D30]">{formatDate(e.extra.data_aplicacao)}</dd></div>
                                                                    <div><dt className="text-[#8A918E]">Variação</dt><dd className="font-mono text-[#1E2D30]">{formataMoeda(e.valor_anterior)} → {formataMoeda(e.valor_novo)}</dd></div>
                                                                    {e.extra.observacao && (
                                                                        <div className="sm:col-span-2"><dt className="text-[#8A918E]">Observação</dt><dd className="text-[#3A4240]">{e.extra.observacao}</dd></div>
                                                                    )}
                                                                </dl>
                                                            )}
                                                            {e.tipo === 'alteracao' && (
                                                                <dl className="grid gap-1.5 text-xs sm:grid-cols-2">
                                                                    <div><dt className="text-[#8A918E]">Campo</dt><dd className="font-mono text-[#1E2D30]">{e.extra?.campo}</dd></div>
                                                                    <div><dt className="text-[#8A918E]">Data efetiva</dt><dd className="text-[#1E2D30]">{formatDate(e.extra?.data_efetiva)}</dd></div>
                                                                    <div className="sm:col-span-2">
                                                                        <dt className="text-[#8A918E]">Mudança</dt>
                                                                        <dd className="font-mono text-[#1E2D30]">
                                                                            {JSON.stringify(e.valor_anterior?.[e.extra?.campo] ?? null)}
                                                                            <span className="mx-2 text-[#8A918E]">→</span>
                                                                            {JSON.stringify(e.valor_novo?.[e.extra?.campo] ?? null)}
                                                                        </dd>
                                                                    </div>
                                                                </dl>
                                                            )}
                                                            {e.tipo === 'atividade' && e.properties && (
                                                                <pre className="overflow-x-auto rounded bg-white p-2 font-mono text-[11px] text-[#3A4240]">{JSON.stringify(e.properties, null, 2)}</pre>
                                                            )}
                                                        </div>
                                                    )}
                                                </li>
                                            );
                                        })}
                                    </ol>
                                )}
                            </div>
                        )}

                        {/* Itens de cobrança (gerenciador) — apenas admin */}
                        {isAdmin && (
                            <GerenciadorItensCobranca
                                contratoId={contrato.id}
                                itensIniciais={itensCobranca ?? []}
                                entidadesExternas={entidadesExternas ?? []}
                            />
                        )}

                        {/* Cobranças recentes */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Cobranças recentes</p>
                            {faturasRecentes.length > 0 ? (
                                <div className="space-y-2">
                                    {faturasRecentes.map((cob: any) => (
                                        <Link key={cob.id} href={`/financeiro/faturas/${cob.id}`}
                                            className="flex items-center justify-between rounded-md border border-[#EEF0EF] px-3 py-2.5 transition-colors hover:bg-[#FAFBFA]">
                                            <div>
                                                <p className="text-sm font-medium text-[#1E2D30]">Ref. {cob.referencia}</p>
                                                <p className="text-xs text-[#8A918E]">Vencimento: {formatDate(cob.data_vencimento)}</p>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <span className="font-mono text-sm font-medium text-[#1E2D30]">{formataMoeda(cob.valor_total)}</span>
                                                <StatusBadge status={cob.status} tipo="cobranca" />
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-[#8A918E]">Nenhuma cobrança gerada</p>
                            )}
                        </div>
                    </div>

                    {/* Coluna lateral */}
                    <div className="space-y-4">
                        {/* Imóvel */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Imóvel</p>
                            {imovel.foto_principal?.url ? (
                                <img src={imovel.foto_principal.url} alt="Foto do imóvel" className="mb-3 aspect-[4/3] w-full rounded-md object-cover" />
                            ) : (
                                <div className="mb-3 flex h-24 items-center justify-center rounded-md bg-[#EEF0EF]">
                                    <Camera className="h-6 w-6 text-[#8A918E]" />
                                </div>
                            )}
                            <p className="text-sm font-medium text-[#1E2D30]">{titulo}</p>
                            <p className="mt-0.5 text-xs text-[#6B7370]">{imovel.bairro}, {imovel.cidade}/{imovel.uf}</p>
                            <div className="mt-2 flex gap-2">
                                <StatusBadge status={imovel.status} tipo="imovel" />
                            </div>
                            <Link href={`/imoveis/${imovel.id}`} className="mt-3 inline-block text-xs text-[#0A4F5C] hover:underline">
                                Ver imóvel →
                            </Link>
                        </div>

                        {/* Inquilino(s) */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">
                                {(contrato.inquilinos?.length ?? 0) > 1 ? 'Inquilinos' : 'Inquilino'}
                            </p>
                            {contrato.inquilinos && contrato.inquilinos.length > 0 ? (
                                <div className="space-y-3">
                                    {contrato.inquilinos.map((ci: any) => (
                                        <div key={ci.id}>
                                            <div className="flex items-center gap-2">
                                                <p className="text-sm font-medium text-[#1E2D30]">{ci.vinculo.user.name}</p>
                                                {ci.principal && (
                                                    <Badge className="bg-[#E8F4F6] text-[#0A4F5C] text-[10px]">Principal</Badge>
                                                )}
                                            </div>
                                            {ci.vinculo.user.email && !ci.vinculo.user.email.endsWith('@nao-cadastrado.kimobe.local') && (
                                                <p className="mt-0.5 text-xs text-[#6B7370]">{ci.vinculo.user.email}</p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <>
                                    <p className="text-sm font-medium text-[#1E2D30]">{inquilino.user.name}</p>
                                    <p className="mt-0.5 text-xs text-[#6B7370]">{inquilino.user.email}</p>
                                </>
                            )}
                        </div>

                        {/* Contato da imobiliária — visível apenas para inquilinos */}
                        {contatoAdmin && (
                            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                                <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Contato da imobiliária</p>
                                <p className="text-sm font-medium text-[#1E2D30]">{contatoAdmin.nome}</p>
                                <p className="mt-0.5 text-xs text-[#6B7370]">{contatoAdmin.email}</p>
                            </div>
                        )}

                        {/* Proprietários — oculto para inquilinos */}
                        {!isInquilino && (
                            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                                <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Proprietário(s)</p>
                                {imovel.titularidades?.length > 0 ? (
                                    <div className="space-y-3">
                                        {imovel.titularidades.map((tit: any) => (
                                            <div key={tit.id} className="flex items-center justify-between">
                                                <div>
                                                    <p className="text-sm font-medium text-[#1E2D30]">{tit.vinculo.user.name}</p>
                                                    <div className="mt-0.5 flex items-center gap-1.5">
                                                        <Badge variant="secondary" className="text-[10px]">{papelLabels[tit.papel]}</Badge>
                                                        {contrato.modelo_repasse === 'garantido' && (
                                                            <Badge className="bg-[#FBF6E8] text-[#6B5420] text-[10px]">Repasse garantido</Badge>
                                                        )}
                                                    </div>
                                                    {tit.dados_bancarios && (
                                                        <p className="mt-1 flex items-center gap-1 text-[10px] text-[#8A918E]">
                                                            <Landmark className="h-3 w-3" />
                                                            {tit.dados_bancarios.banco_nome} — CC {tit.dados_bancarios.conta}
                                                        </p>
                                                    )}
                                                </div>
                                                <span className="text-sm font-medium text-[#0A4F5C]">{parseFloat(tit.percentual).toFixed(0)}%</span>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-sm text-[#8A918E]">Nenhum titular cadastrado</p>
                                )}
                            </div>
                        )}

                        {/* Info cadastro */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Informações</p>
                            <div className="space-y-2 text-sm">
                                <div className="flex items-center gap-2 text-[#6B7370]">
                                    <Calendar className="h-3.5 w-3.5" />
                                    Criado em {formatDate(contrato.created_at)}
                                </div>
                                <div className="flex items-center gap-2 text-[#6B7370]">
                                    <Calendar className="h-3.5 w-3.5" />
                                    Atualizado em {formatDate(contrato.updated_at)}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modais de ação */}
            <ConfirmDialog
                open={actionTarget === 'encerrar'}
                onOpenChange={(open) => !open && setActionTarget(null)}
                titulo="Encerrar contrato"
                descricao={`Tem certeza que deseja encerrar o contrato do imóvel ${titulo}? O status será alterado para "Encerrado" e nenhuma nova cobrança será gerada.`}
                textoConfirmar="Encerrar"
                loading={actionLoading}
                onConfirm={handleAction}
            />
            <ConfirmDialog
                open={actionTarget === 'cancelar'}
                onOpenChange={(open) => !open && setActionTarget(null)}
                titulo="Cancelar contrato"
                descricao={`Tem certeza que deseja cancelar o contrato do imóvel ${titulo}? Esta ação indica rescisão e pode envolver multa rescisória.`}
                textoConfirmar="Confirmar cancelamento"
                variante="destructive"
                loading={actionLoading}
                onConfirm={handleAction}
            />

            {/* Dialog dados do fiador */}
            <FiadorDetalhesDialog
                fiador={fiadorDialog}
                open={!!fiadorDialog}
                onOpenChange={(open) => !open && setFiadorDialog(null)}
            />

            {/* Dialog aplicar reajuste */}
            <Dialog open={reajusteOpen} onOpenChange={setReajusteOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Aplicar reajuste</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={aplicarReajuste} className="space-y-3">
                        <div className="rounded-md bg-[#F7F8F7] px-3 py-2 text-xs text-[#6B7370]">
                            Valor atual: <span className="font-mono font-medium text-[#1E2D30]">{formataMoeda(contrato.valor_aluguel)}</span>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label>Novo valor</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={reajusteForm.data.valor_novo}
                                    onChange={(e) => reajusteForm.setData('valor_novo', e.target.value)}
                                    className="bg-white border-[#D8DCDA]"
                                />
                                {reajusteForm.errors.valor_novo && (
                                    <p className="mt-1 text-xs text-[#A83232]">{reajusteForm.errors.valor_novo}</p>
                                )}
                            </div>
                            <div>
                                <Label>Aplicação</Label>
                                <Input
                                    type="date"
                                    value={reajusteForm.data.data_aplicacao}
                                    onChange={(e) => reajusteForm.setData('data_aplicacao', e.target.value)}
                                    className="bg-white border-[#D8DCDA]"
                                />
                                {reajusteForm.errors.data_aplicacao && (
                                    <p className="mt-1 text-xs text-[#A83232]">{reajusteForm.errors.data_aplicacao}</p>
                                )}
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <Label>Índice</Label>
                                <Select value={reajusteForm.data.indice_usado} onValueChange={(v) => reajusteForm.setData('indice_usado', v)}>
                                    <SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="igpm">IGPM</SelectItem>
                                        <SelectItem value="ipca">IPCA</SelectItem>
                                        <SelectItem value="fixo">Fixo</SelectItem>
                                        <SelectItem value="manual">Manual</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div>
                                <Label>Origem</Label>
                                <Select value={reajusteForm.data.origem} onValueChange={(v) => reajusteForm.setData('origem', v)}>
                                    <SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="reajuste_anual">Reajuste anual</SelectItem>
                                        <SelectItem value="aditivo">Aditivo</SelectItem>
                                        <SelectItem value="renegociacao">Renegociação</SelectItem>
                                        <SelectItem value="correcao">Correção</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div>
                            <Label>Observação <span className="text-[#8A918E]">(opcional)</span></Label>
                            <textarea
                                value={reajusteForm.data.observacao}
                                onChange={(e) => reajusteForm.setData('observacao', e.target.value)}
                                rows={2}
                                className="w-full rounded-md border border-[#D8DCDA] bg-white px-3 py-2 text-sm focus:border-[#0A4F5C] focus:outline-none focus:ring-1 focus:ring-[#0A4F5C]"
                            />
                        </div>
                        <p className="rounded-md bg-[#FBF6E8] px-3 py-2 text-xs text-[#6B5420]">
                            O novo valor é aplicado a partir da data e propagado para todos os itens de aluguel pendentes nesse mês ou posteriores. Itens já conciliados ficam intocados.
                        </p>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setReajusteOpen(false)} className="border-[#D8DCDA]">Cancelar</Button>
                            <Button type="submit" disabled={reajusteForm.processing} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                                {reajusteForm.processing ? 'Aplicando...' : 'Aplicar reajuste'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

/** Helper: campo label + valor */
function InfoItem({ label, value, children, className }: { label: string; value?: string; children?: React.ReactNode; className?: string }) {
    return (
        <div>
            <p className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">{label}</p>
            {children ?? <p className={`mt-0.5 text-sm text-[#1E2D30] ${className ?? ''}`}>{value ?? '—'}</p>}
        </div>
    );
}
