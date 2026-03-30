import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, Calendar, Camera, Info, Landmark, Pencil } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { usePermissions } from '@/hooks/use-permissions';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { FiadorDetalhesDialog } from '@/components/fiador-detalhes-dialog';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
// Dialog não mais necessário diretamente — FiadorDetalhesDialog encapsula
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formataMoeda } from '@/lib/utils';

// Labels
const indiceLabels: Record<string, string> = { igpm: 'IGPM', ipca: 'IPCA', fixo: 'Fixo' };
const garantiaTipoLabels: Record<string, string> = { caucao: 'Caução', fiador: 'Fiador', seguro_fianca: 'Seguro fiança', titulo_capitalizacao: 'Título de capitalização', sem_garantia: 'Sem garantia' };
const papelLabels: Record<string, string> = { responsavel: 'Responsável', observador: 'Observador' };

function formatDate(d: string): string {
    return new Date(d).toLocaleDateString('pt-BR');
}

function cpfParcial(cpf: string): string {
    return cpf.length > 6 ? `***${cpf.slice(-6)}` : cpf;
}

type Props = {
    contrato: any;
    cobrancasRecentes: any[];
    contatoAdmin?: { nome: string; email: string } | null;
};

export default function MostrarContrato({ contrato, cobrancasRecentes, contatoAdmin }: Props) {
    const { flash } = usePage().props as any;
    const { can, isInquilino } = usePermissions();
    const [actionTarget, setActionTarget] = useState<'encerrar' | 'cancelar' | null>(null);
    const [actionLoading, setActionLoading] = useState(false);
    const [fiadorDialog, setFiadorDialog] = useState<any>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

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

                        {/* Responsabilidades */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Responsabilidades</p>
                            {contrato.responsabilidades?.length > 0 ? (
                                <Table>
                                    <TableHeader>
                                        <TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                                            <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Item</TableHead>
                                            <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Responsável</TableHead>
                                            <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Valor</TableHead>
                                            <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Periodicidade</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {contrato.responsabilidades.map((r: any) => (
                                            <TableRow key={r.id} className="border-b border-[#F7F8F7]">
                                                <TableCell className="text-sm">
                                                    {r.descricao}
                                                    {r.predefinido && <Badge variant="outline" className="ml-2 text-[9px]">Pré-definido</Badge>}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge variant="secondary" className={r.responsavel === 'proprietario' ? 'bg-[#E8F4F6] text-[#0A4F5C]' : 'bg-[#F7F8F7] text-[#6B7370]'}>
                                                        {r.responsavel === 'proprietario' ? 'Proprietário' : 'Inquilino'}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right font-mono text-sm">{r.valor ? formataMoeda(r.valor) : '—'}</TableCell>
                                                <TableCell className="text-xs capitalize text-[#6B7370]">{r.periodicidade}</TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            ) : (
                                <p className="text-sm text-[#8A918E]">Nenhuma responsabilidade definida</p>
                            )}
                        </div>

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

                        {/* Cobranças recentes */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Cobranças recentes</p>
                            {cobrancasRecentes.length > 0 ? (
                                <div className="space-y-2">
                                    {cobrancasRecentes.map((cob: any) => (
                                        <Link key={cob.id} href={`/financeiro/cobrancas/${cob.id}`}
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

                        {/* Inquilino */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Inquilino</p>
                            <p className="text-sm font-medium text-[#1E2D30]">{inquilino.user.name}</p>
                            <p className="mt-0.5 text-xs text-[#6B7370]">{inquilino.user.email}</p>
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
