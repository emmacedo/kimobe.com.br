import { Head, Link, router, usePage } from '@inertiajs/react';
import { Calendar, Camera, Landmark } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { usePermissions } from '@/hooks/use-permissions';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { GerenciadorComprovantes } from '@/components/gerenciador-comprovantes';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formataMoeda } from '@/lib/utils';

function formatDate(d: string): string { return new Date(d).toLocaleDateString('pt-BR'); }

const papelLabels: Record<string, string> = { responsavel: 'Responsável', observador: 'Observador' };
const tipoLabels: Record<string, string> = { pessoa_fisica: 'Pessoa física', empresa: 'Empresa', inventario: 'Inventário' };
const modeloLabels: Record<string, string> = { por_recebimento: 'Por recebimento', garantido: 'Garantido' };

type Props = { repasse: any };

export default function MostrarRepasse({ repasse }: Props) {
    const { flash } = usePage().props as any;
    const { can } = usePermissions();
    const [confirmarOpen, setConfirmarOpen] = useState(false);
    const [confirmarData, setConfirmarData] = useState(new Date().toISOString().split('T')[0]);
    const [confirmarSaving, setConfirmarSaving] = useState(false);
    const [cancelOpen, setCancelOpen] = useState(false);
    const [cancelLoading, setCancelLoading] = useState(false);

    useEffect(() => { if (flash?.success) toast.success(flash.success); }, [flash?.success]);

    const tit = repasse.titularidade;
    const cobranca = repasse.cobranca;
    const contrato = cobranca.contrato;
    const imovel = contrato.imovel;
    const titulo = `${tit.vinculo.user.name} — ${cobranca.referencia}`;
    const isPendente = repasse.status === 'pendente';
    const banco = tit.dados_bancarios;

    function handleConfirmar() {
        setConfirmarSaving(true);
        router.patch(`/financeiro/repasses/${repasse.id}/confirmar`, { data_realizada: confirmarData }, {
            onFinish: () => { setConfirmarSaving(false); setConfirmarOpen(false); },
        });
    }

    function handleCancelar() {
        setCancelLoading(true);
        router.patch(`/financeiro/repasses/${repasse.id}/cancelar`, {}, {
            onFinish: () => { setCancelLoading(false); setCancelOpen(false); },
        });
    }

    return (
        <>
            <Head title={`Repasse — ${titulo}`} />
            <div className="space-y-4">
                <PageHeader titulo={`Repasse — ${titulo}`}>
                    <div className="flex items-center gap-2">
                        <StatusBadge status={repasse.status} tipo="repasse" />
                        {can.manage_repasses && isPendente && (
                            <>
                                <Button size="sm" className="bg-[#C9A84C] text-white hover:bg-[#B8993F]" onClick={() => setConfirmarOpen(true)}>Confirmar repasse</Button>
                                <Button variant="ghost" size="sm" className="text-[#A83232]" onClick={() => setCancelOpen(true)}>Cancelar</Button>
                            </>
                        )}
                    </div>
                </PageHeader>

                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Coluna principal */}
                    <div className="space-y-4 lg:col-span-2">
                        {/* Decomposição */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Detalhes do repasse</p>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-[#3A4240]">Aluguel do imóvel</span>
                                    <span className="font-mono text-[#1E2D30]">{formataMoeda(contrato.valor_aluguel)}</span>
                                </div>
                                {parseFloat(tit.percentual) < 100 && (
                                    <div className="flex justify-between">
                                        <span className="text-[#3A4240]">Participação do titular ({parseFloat(tit.percentual).toFixed(0)}%)</span>
                                        <span className="font-mono text-[#1E2D30]">{formataMoeda(repasse.valor_aluguel_bruto)}</span>
                                    </div>
                                )}
                                <div className="flex justify-between text-[#A83232]">
                                    <span>Taxa de administração ({parseFloat(contrato.taxa_administracao_pct).toFixed(2)}%)</span>
                                    <span className="font-mono">-{formataMoeda(repasse.taxa_administracao_valor)}</span>
                                </div>
                                {repasse.taxa_seguro_inadimplencia_valor && parseFloat(repasse.taxa_seguro_inadimplencia_valor) > 0 && (
                                    <div className="flex justify-between text-[#A83232]">
                                        <span>Seguro inadimplência ({parseFloat(contrato.taxa_seguro_inadimplencia_pct).toFixed(2)}%)</span>
                                        <span className="font-mono">-{formataMoeda(repasse.taxa_seguro_inadimplencia_valor)}</span>
                                    </div>
                                )}
                                <div className="flex justify-between border-t border-[#D8DCDA] pt-2">
                                    <span className="font-semibold text-[#1E2D30]">Valor líquido</span>
                                    <span className={`font-mono text-lg font-semibold ${repasse.status === 'realizado' ? 'text-[#1B6B3A]' : 'text-[#0A4F5C]'}`}>
                                        {formataMoeda(repasse.valor_liquido)}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {/* Dados bancários */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Dados bancários do titular</p>
                            {banco ? (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <div><p className="text-[10px] uppercase text-[#8A918E]">Banco</p><p className="text-sm text-[#1E2D30]">{banco.banco_codigo} — {banco.banco_nome}</p></div>
                                    <div><p className="text-[10px] uppercase text-[#8A918E]">Agência / Conta</p><p className="text-sm text-[#1E2D30]">Ag {banco.agencia} — CC {banco.conta} ({banco.tipo_conta})</p></div>
                                    {banco.pix_chave && (
                                        <div className="sm:col-span-2"><p className="text-[10px] uppercase text-[#8A918E]">PIX ({banco.pix_tipo})</p><p className="text-sm text-[#1E2D30]">{banco.pix_chave}</p></div>
                                    )}
                                </div>
                            ) : (
                                <div className="rounded-md bg-[#FFF4E5] p-3 text-xs text-[#8C5A10]">
                                    Titular sem conta bancária cadastrada. Cadastre antes de confirmar o repasse.
                                </div>
                            )}
                        </div>

                        {/* Comprovantes */}
                        <GerenciadorComprovantes
                            entidadeId={repasse.id}
                            entidadeTipo="repasse"
                            comprovantes={repasse.comprovantes ?? []}
                            readOnly={!can.manage_repasses}
                        />

                        {/* Cobrança de origem */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Cobrança de origem</p>
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-[#1E2D30]">Ref. {cobranca.referencia}</p>
                                    <p className="text-xs text-[#8A918E]">Total: {formataMoeda(cobranca.valor_total)}</p>
                                </div>
                                <div className="flex items-center gap-2">
                                    <StatusBadge status={cobranca.status} tipo="cobranca" />
                                    <Link href={`/financeiro/cobrancas/${cobranca.id}`} className="text-xs text-[#0A4F5C] hover:underline">Ver cobrança →</Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Coluna lateral */}
                    <div className="space-y-4">
                        {/* Titular */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Titular</p>
                            <p className="text-sm font-medium text-[#1E2D30]">{tit.vinculo.user.name}</p>
                            <div className="mt-1 flex gap-1.5">
                                <Badge variant="secondary" className="text-[10px]">{papelLabels[tit.papel]}</Badge>
                                <Badge variant="outline" className="text-[10px]">{tipoLabels[tit.tipo_titular]}</Badge>
                            </div>
                            <p className="mt-2 text-lg font-medium text-[#0A4F5C]">{parseFloat(tit.percentual).toFixed(0)}%</p>
                        </div>

                        {/* Imóvel */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Imóvel</p>
                            {imovel.foto_principal?.url ? (
                                <img src={imovel.foto_principal.url} alt="" className="mb-2 aspect-[4/3] w-full rounded-md object-cover" />
                            ) : (
                                <div className="mb-2 flex h-20 items-center justify-center rounded-md bg-[#EEF0EF]"><Camera className="h-5 w-5 text-[#8A918E]" /></div>
                            )}
                            <p className="text-sm font-medium text-[#1E2D30]">{imovel.complemento || `${imovel.logradouro}, ${imovel.numero}`}</p>
                            <Link href={`/imoveis/${imovel.id}`} className="mt-2 inline-block text-xs text-[#0A4F5C] hover:underline">Ver imóvel →</Link>
                        </div>

                        {/* Contrato */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Contrato</p>
                            <p className="text-sm text-[#6B7370]">Aluguel: {formataMoeda(contrato.valor_aluguel)}</p>
                            <p className="text-sm text-[#6B7370]">Taxa admin: {parseFloat(contrato.taxa_administracao_pct).toFixed(2)}%</p>
                            <div className="mt-2">
                                <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${contrato.modelo_repasse === 'garantido' ? 'bg-[#FBF6E8] text-[#6B5420]' : 'bg-[#F7F8F7] text-[#6B7370]'}`}>
                                    {modeloLabels[contrato.modelo_repasse]}
                                </span>
                            </div>
                            <Link href={`/contratos/${contrato.id}`} className="mt-2 inline-block text-xs text-[#0A4F5C] hover:underline">Ver contrato →</Link>
                        </div>

                        {/* Datas */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Datas</p>
                            <div className="space-y-2 text-sm text-[#6B7370]">
                                <div className="flex items-center gap-2"><Calendar className="h-3.5 w-3.5" />Previsto: {formatDate(repasse.data_prevista)}</div>
                                <div className="flex items-center gap-2">
                                    <Calendar className="h-3.5 w-3.5" />
                                    {repasse.data_realizada ? `Realizado: ${formatDate(repasse.data_realizada)}` : 'Pendente'}
                                </div>
                                <div className="flex items-center gap-2"><Calendar className="h-3.5 w-3.5" />Criado: {formatDate(repasse.created_at)}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Dialog confirmar */}
            <Dialog open={confirmarOpen} onOpenChange={setConfirmarOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader><DialogTitle>Confirmar repasse</DialogTitle></DialogHeader>
                    <div className="rounded-md bg-[#F7F8F7] p-3 text-sm">
                        <p className="font-medium text-[#1E2D30]">{tit.vinculo.user.name}</p>
                        {banco && <p className="text-xs text-[#8A918E]">{banco.banco_nome} — Ag {banco.agencia} CC {banco.conta}</p>}
                        <p className="mt-1 font-mono text-lg font-medium text-[#0A4F5C]">{formataMoeda(repasse.valor_liquido)}</p>
                    </div>
                    <div><Label>Data da transferência</Label><Input type="date" value={confirmarData} onChange={(e) => setConfirmarData(e.target.value)} className="bg-white border-[#D8DCDA]" /></div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setConfirmarOpen(false)} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button onClick={handleConfirmar} disabled={confirmarSaving} className="bg-[#C9A84C] text-white hover:bg-[#B8993F]">{confirmarSaving && <Spinner />}Confirmar</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Dialog cancelar */}
            <ConfirmDialog open={cancelOpen} onOpenChange={setCancelOpen}
                titulo="Cancelar repasse" descricao={`Cancelar o repasse de ${formataMoeda(repasse.valor_liquido)} para ${tit.vinculo.user.name}?`}
                textoConfirmar="Cancelar repasse" textoCancelar="Voltar" variante="destructive" loading={cancelLoading} onConfirm={handleCancelar} />
        </>
    );
}
