import { Head, Link, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Calendar, CheckCircle, Clock, FileText } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { usePermissions } from '@/hooks/use-permissions';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DialogPagamento } from '@/components/dialog-pagamento';
import { GerenciadorComprovantes } from '@/components/gerenciador-comprovantes';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formataMoeda } from '@/lib/utils';

function formatDate(d: string): string {
    return new Date(d).toLocaleDateString('pt-BR');
}

const modeloLabels: Record<string, string> = { por_recebimento: 'Por recebimento', garantido: 'Garantido' };
const metodoLabels: Record<string, string> = { boleto: 'Boleto', pix: 'PIX', transferencia: 'Transferência', dinheiro: 'Dinheiro' };

type Props = {
    fatura: any;
    acrescimos: {
        dias_atraso: number;
        multa_estimada: number;
        juros_estimados: number;
        total_estimado: number;
    } | null;
};

export default function MostrarFatura({ fatura, acrescimos }: Props) {
    const { flash } = usePage().props as any;
    const { can, isInquilino } = usePermissions();
    const [cancelOpen, setCancelOpen] = useState(false);
    const [cancelLoading, setCancelLoading] = useState(false);
    const [pagamentoOpen, setPagamentoOpen] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    const contrato = fatura.contrato;
    const imovel = contrato.imovel;
    const titulo = imovel.complemento || `${imovel.logradouro}, ${imovel.numero}`;
    const podeAgir = ['pendente', 'atrasado'].includes(fatura.status);

    function handleCancelar() {
        setCancelLoading(true);
        router.patch(`/financeiro/faturas/${fatura.id}/cancelar`, {}, {
            onFinish: () => { setCancelLoading(false); setCancelOpen(false); },
        });
    }

    // Itens da fatura — vêm de itens_cobranca conciliados (modelo unificado).
    // Estrutura completa será exibida após item 5 do plano (ItemCobrancaService).
    const itens = fatura.itens ?? [];

    return (
        <>
            <Head title={`Cobrança ${fatura.referencia} — ${titulo}`} />
            <div className="space-y-4">
                <PageHeader titulo={`Cobrança ${fatura.referencia} — ${titulo}`}>
                    <div className="flex items-center gap-2">
                        <StatusBadge status={fatura.status} tipo="cobranca" />
                        {can.manage_faturas && podeAgir && (
                            <>
                                <Button size="sm" className="bg-[#0A4F5C] text-white hover:bg-[#073B45]" onClick={() => setPagamentoOpen(true)}>
                                    Registrar pagamento
                                </Button>
                                <Button variant="ghost" size="sm" className="text-[#A83232] hover:text-[#A83232]" onClick={() => setCancelOpen(true)}>
                                    Cancelar
                                </Button>
                            </>
                        )}
                    </div>
                </PageHeader>

                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Coluna principal */}
                    <div className="space-y-4 lg:col-span-2">
                        {/* Composição */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Composição da fatura</p>
                            <div className="space-y-2">
                                {itens.length > 0 ? itens.map((item: any) => (
                                    <div key={item.id} className="flex justify-between text-sm">
                                        <span className="text-[#3A4240]">{item.descricao}</span>
                                        <span className="font-mono text-[#1E2D30]">{formataMoeda(item.valor_unitario)}</span>
                                    </div>
                                )) : (
                                    <p className="text-sm text-[#8A918E]">Nenhum item conciliado nesta fatura ainda.</p>
                                )}

                                <div className="flex justify-between border-t border-[#D8DCDA] pt-2">
                                    <span className="text-sm font-semibold text-[#1E2D30]">Total da fatura</span>
                                    <span className="font-mono text-lg font-semibold text-[#0A4F5C]">{formataMoeda(fatura.valor_total)}</span>
                                </div>
                            </div>
                        </div>

                        {/* Acréscimos/descontos */}
                        {(acrescimos || fatura.status === 'pago') && (
                            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                                <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Acréscimos e descontos</p>
                                {acrescimos && fatura.status !== 'pago' && (
                                    <div className="space-y-2">
                                        <div className="flex justify-between text-sm">
                                            <span className="text-[#3A4240]">Dias de atraso</span>
                                            <span className="font-medium text-[#A83232]">{acrescimos.dias_atraso} dia(s)</span>
                                        </div>
                                        <div className="flex justify-between text-sm">
                                            <span className="text-[#3A4240]">Multa estimada ({parseFloat(contrato.multa_atraso_pct).toFixed(2)}%)</span>
                                            <span className="font-mono text-[#A83232]">+{formataMoeda(acrescimos.multa_estimada)}</span>
                                        </div>
                                        <div className="flex justify-between text-sm">
                                            <span className="text-[#3A4240]">Juros estimados</span>
                                            <span className="font-mono text-[#A83232]">+{formataMoeda(acrescimos.juros_estimados)}</span>
                                        </div>
                                        <div className="flex justify-between border-t border-[#EEF0EF] pt-2 font-medium">
                                            <span className="text-[#1E2D30]">Total estimado</span>
                                            <span className="font-mono text-lg text-[#A83232]">{formataMoeda(acrescimos.total_estimado)}</span>
                                        </div>
                                        <p className="mt-2 text-[10px] text-[#8A918E]">Valores estimados com base na data de hoje. O valor final será calculado na data do pagamento.</p>
                                    </div>
                                )}
                                {fatura.status === 'pago' && (
                                    <div className="space-y-2">
                                        {fatura.valor_desconto && parseFloat(fatura.valor_desconto) > 0 && (
                                            <div className="flex justify-between text-sm">
                                                <span className="text-[#3A4240]">Desconto concedido</span>
                                                <span className="font-mono text-[#1B6B3A]">-{formataMoeda(fatura.valor_desconto)}</span>
                                            </div>
                                        )}
                                        {fatura.valor_multa && parseFloat(fatura.valor_multa) > 0 && (
                                            <div className="flex justify-between text-sm">
                                                <span className="text-[#3A4240]">Multa aplicada</span>
                                                <span className="font-mono text-[#A83232]">+{formataMoeda(fatura.valor_multa)}</span>
                                            </div>
                                        )}
                                        {fatura.valor_juros && parseFloat(fatura.valor_juros) > 0 && (
                                            <div className="flex justify-between text-sm">
                                                <span className="text-[#3A4240]">Juros aplicados</span>
                                                <span className="font-mono text-[#A83232]">+{formataMoeda(fatura.valor_juros)}</span>
                                            </div>
                                        )}
                                        <div className="flex justify-between border-t border-[#EEF0EF] pt-2 font-medium">
                                            <span className="text-[#1E2D30]">Valor pago</span>
                                            <span className="font-mono text-lg text-[#1B6B3A]">{formataMoeda(fatura.valor_pago)}</span>
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Repasses — oculto para inquilinos */}
                        {!isInquilino && (
                            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                                <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Repasses</p>
                                {fatura.repasses?.length > 0 ? (
                                    <Table>
                                        <TableHeader>
                                            <TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Titular</TableHead>
                                                <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">%</TableHead>
                                                <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Bruto</TableHead>
                                                <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Taxa</TableHead>
                                                <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Líquido</TableHead>
                                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Status</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {fatura.repasses.map((r: any) => (
                                                <TableRow key={r.id} className="cursor-pointer border-b border-[#F7F8F7] hover:bg-[#FAFBFA]"
                                                    onClick={() => router.visit(`/financeiro/repasses/${r.id}`)}>
                                                    <TableCell className="text-sm font-medium text-[#1E2D30]">{r.titularidade.vinculo.user.name}</TableCell>
                                                    <TableCell className="text-right text-sm text-[#6B7370]">{parseFloat(r.titularidade.percentual).toFixed(0)}%</TableCell>
                                                    <TableCell className="text-right font-mono text-sm">{formataMoeda(r.valor_aluguel_bruto)}</TableCell>
                                                    <TableCell className="text-right font-mono text-sm text-[#A83232]">-{formataMoeda(parseFloat(r.taxa_administracao_valor) + parseFloat(r.taxa_seguro_inadimplencia_valor ?? 0))}</TableCell>
                                                    <TableCell className="text-right font-mono text-sm font-medium text-[#0A4F5C]">{formataMoeda(r.valor_liquido)}</TableCell>
                                                    <TableCell><StatusBadge status={r.status} tipo="repasse" /></TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                ) : (
                                    <div className="text-sm text-[#8A918E]">
                                        {podeAgir && contrato.modelo_repasse === 'por_recebimento'
                                            ? 'Os repasses serão gerados automaticamente quando o pagamento for registrado.'
                                            : 'Nenhum repasse gerado para esta cobrança.'}
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Observações — oculto para inquilinos */}
                        {(fatura.observacoes || fatura.url_boleto) && !isInquilino && (
                            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                                <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Informações adicionais</p>
                                {fatura.observacoes && <p className="mb-2 whitespace-pre-line text-sm text-[#3A4240]">{fatura.observacoes}</p>}
                                {fatura.url_boleto && (
                                    <a href={fatura.url_boleto} target="_blank" rel="noopener noreferrer" className="text-xs text-[#0A4F5C] hover:underline">
                                        <FileText className="mr-1 inline h-3.5 w-3.5" />Ver boleto
                                    </a>
                                )}
                            </div>
                        )}
                        {/* Comprovantes */}
                        <GerenciadorComprovantes
                            entidadeId={fatura.id}
                            entidadeTipo="fatura"
                            comprovantes={fatura.comprovantes ?? []}
                            readOnly={fatura.status === 'cancelado' || !can.upload_comprovantes}
                        />
                    </div>

                    {/* Coluna lateral */}
                    <div className="space-y-4">
                        {/* Contrato */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Contrato</p>
                            <Link href={`/imoveis/${imovel.id}`} className="text-sm font-medium text-[#0A4F5C] hover:underline">{titulo}</Link>
                            <p className="mt-1 text-xs text-[#6B7370]">{contrato.inquilino.user.name}</p>
                            <p className="mt-1 text-xs text-[#6B7370]">Aluguel: {formataMoeda(contrato.valor_aluguel)}</p>
                            <div className="mt-2">
                                <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${contrato.modelo_repasse === 'garantido' ? 'bg-[#FBF6E8] text-[#6B5420]' : 'bg-[#F7F8F7] text-[#6B7370]'}`}>
                                    {modeloLabels[contrato.modelo_repasse]}
                                </span>
                            </div>
                            <Link href={`/contratos/${contrato.id}`} className="mt-3 inline-block text-xs text-[#0A4F5C] hover:underline">Ver contrato →</Link>
                        </div>

                        {/* Vencimento */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-2 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Vencimento</p>
                            <p className="text-lg font-medium text-[#1E2D30]">{formatDate(fatura.data_vencimento)}</p>
                            {fatura.status === 'atrasado' && acrescimos && (
                                <div className="mt-2 flex items-center gap-1.5 text-xs text-[#A83232]">
                                    <AlertTriangle className="h-3.5 w-3.5" />
                                    {acrescimos.dias_atraso} dia(s) em atraso
                                </div>
                            )}
                            {fatura.status === 'pago' && (
                                <div className="mt-2 flex items-center gap-1.5 text-xs text-[#1B6B3A]">
                                    <CheckCircle className="h-3.5 w-3.5" />
                                    Pago em {formatDate(fatura.data_pagamento)}
                                </div>
                            )}
                            {fatura.status === 'pendente' && !acrescimos && (
                                <div className="mt-2 flex items-center gap-1.5 text-xs text-[#6B7370]">
                                    <Clock className="h-3.5 w-3.5" />
                                    Aguardando pagamento
                                </div>
                            )}
                        </div>

                        {/* Pagamento (se pago) */}
                        {fatura.status === 'pago' && (
                            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                                <p className="mb-2 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Pagamento</p>
                                <p className="text-sm text-[#6B7370]">{formatDate(fatura.data_pagamento)}</p>
                                {fatura.metodo_pagamento && (
                                    <Badge variant="secondary" className="mt-1 text-[10px]">
                                        {metodoLabels[fatura.metodo_pagamento] ?? fatura.metodo_pagamento}
                                    </Badge>
                                )}
                                <p className="mt-2 font-mono text-xl font-medium text-[#1B6B3A]">{formataMoeda(fatura.valor_pago)}</p>
                            </div>
                        )}

                        {/* Info */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Informações</p>
                            <div className="space-y-2 text-sm text-[#6B7370]">
                                <div className="flex items-center gap-2">
                                    <Calendar className="h-3.5 w-3.5" />
                                    Criado em {formatDate(fatura.created_at)}
                                </div>
                                <div>
                                    <Badge variant="secondary" className="text-[10px]">
                                        {fatura.tipo_geracao === 'automatica' ? 'Automática' : 'Manual'}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <ConfirmDialog
                open={cancelOpen}
                onOpenChange={setCancelOpen}
                titulo="Cancelar cobrança"
                descricao={`Tem certeza que deseja cancelar a cobrança de ${fatura.referencia} do imóvel ${titulo}? Se houver repasses vinculados, eles também serão cancelados.`}
                textoConfirmar="Cancelar cobrança"
                textoCancelar="Voltar"
                variante="destructive"
                loading={cancelLoading}
                onConfirm={handleCancelar}
            />

            {/* Dialog registrar pagamento */}
            {podeAgir && (
                <DialogPagamento
                    open={pagamentoOpen}
                    onOpenChange={setPagamentoOpen}
                    cobranca={fatura}
                />
            )}
        </>
    );
}
