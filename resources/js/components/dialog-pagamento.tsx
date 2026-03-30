import { router } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { InputMoeda } from '@/components/input-moeda';
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
import { formataMoeda } from '@/lib/utils';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    cobranca: {
        id: number;
        referencia: string;
        valor_total: string;
        data_vencimento: string;
        status: string;
        contrato: {
            multa_atraso_pct: string;
            juros_atraso_pct_dia: string;
            dias_carencia: number;
            desconto_pontualidade_pct: string | null;
            imovel: { logradouro: string; numero: string; complemento: string | null };
        };
    };
};

function formatDate(d: string): string {
    return new Date(d).toLocaleDateString('pt-BR');
}

function diffDays(d1: string, d2: string): number {
    return Math.floor((new Date(d1).getTime() - new Date(d2).getTime()) / (1000 * 60 * 60 * 24));
}

export function DialogPagamento({ open, onOpenChange, cobranca }: Props) {
    const hoje = new Date().toISOString().split('T')[0];
    const [dataPagamento, setDataPagamento] = useState(hoje);
    const [metodoPagamento, setMetodoPagamento] = useState('');
    const [valorPago, setValorPago] = useState<number | null>(null);
    const [observacoes, setObservacoes] = useState('');
    const [saving, setSaving] = useState(false);

    const contrato = cobranca.contrato;
    const imovel = contrato.imovel;
    const titulo = imovel.complemento || `${imovel.logradouro}, ${imovel.numero}`;
    const valorTotal = parseFloat(cobranca.valor_total);
    const multaPct = parseFloat(contrato.multa_atraso_pct);
    const jurosPctDia = parseFloat(contrato.juros_atraso_pct_dia);
    const diasCarencia = contrato.dias_carencia;
    const descontoPct = contrato.desconto_pontualidade_pct ? parseFloat(contrato.desconto_pontualidade_pct) : 0;

    // Calcular acréscimos/descontos baseado na data de pagamento
    const diasAposVenc = diffDays(dataPagamento, cobranca.data_vencimento);

    let desconto = 0;
    let multa = 0;
    let juros = 0;

    if (diasAposVenc <= 0 && descontoPct > 0) {
        // Pagou no prazo
        desconto = Math.round(valorTotal * descontoPct / 100 * 100) / 100;
    } else if (diasAposVenc > diasCarencia) {
        // Pagou após carência
        const diasAtraso = diasAposVenc - diasCarencia;
        multa = Math.round(valorTotal * multaPct / 100 * 100) / 100;
        juros = Math.round(valorTotal * jurosPctDia / 100 * diasAtraso * 100) / 100;
    }

    const valorCalculado = Math.round((valorTotal - desconto + multa + juros) * 100) / 100;

    // Pré-preencher valor_pago com o calculado quando muda
    const valorFinal = valorPago ?? valorCalculado;
    const diferenca = Math.round((valorFinal - valorCalculado) * 100) / 100;

    function handleSubmit() {
        if (!metodoPagamento) {
            toast.error('Selecione o método de pagamento.');
            return;
        }
        setSaving(true);
        router.patch(`/financeiro/cobrancas/${cobranca.id}/pagamento`, {
            data_pagamento: dataPagamento,
            metodo_pagamento: metodoPagamento,
            valor_pago: valorFinal,
            observacoes: observacoes || null,
        }, {
            onSuccess: () => { setSaving(false); onOpenChange(false); },
            onError: () => { setSaving(false); toast.error('Erro ao registrar pagamento.'); },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Registrar pagamento</DialogTitle>
                    <DialogDescription>Cobrança {cobranca.referencia} — {titulo}</DialogDescription>
                </DialogHeader>

                {/* Resumo */}
                <div className="rounded-md bg-[#F7F8F7] p-3 text-sm">
                    <div className="flex justify-between">
                        <span className="text-[#6B7370]">Valor da cobrança</span>
                        <span className="font-mono font-medium text-[#1E2D30]">{formataMoeda(valorTotal)}</span>
                    </div>
                    <div className="mt-0.5 text-xs text-[#8A918E]">
                        Vencimento: {formatDate(cobranca.data_vencimento)}
                        {diasAposVenc > 0 && <span className="ml-1 text-[#A83232]">({diasAposVenc} dia(s) atrasado)</span>}
                    </div>
                </div>

                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Data do pagamento</Label>
                            <Input type="date" value={dataPagamento} onChange={(e) => { setDataPagamento(e.target.value); setValorPago(null); }} className="bg-white border-[#D8DCDA]" />
                        </div>
                        <div>
                            <Label>Método de pagamento</Label>
                            <Select value={metodoPagamento} onValueChange={setMetodoPagamento}>
                                <SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue placeholder="Selecione" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="boleto">Boleto</SelectItem>
                                    <SelectItem value="pix">PIX</SelectItem>
                                    <SelectItem value="transferencia">Transferência</SelectItem>
                                    <SelectItem value="dinheiro">Dinheiro</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    {/* Cálculo */}
                    <div className="rounded-md border border-[#D8DCDA] p-3 text-sm">
                        <div className="flex justify-between">
                            <span className="text-[#3A4240]">Valor da cobrança</span>
                            <span className="font-mono">{formataMoeda(valorTotal)}</span>
                        </div>
                        {desconto > 0 && (
                            <div className="mt-1 flex justify-between text-[#1B6B3A]">
                                <span>Desconto pontualidade ({descontoPct}%)</span>
                                <span className="font-mono">-{formataMoeda(desconto)}</span>
                            </div>
                        )}
                        {multa > 0 && (
                            <div className="mt-1 flex justify-between text-[#A83232]">
                                <span>Multa ({multaPct}%)</span>
                                <span className="font-mono">+{formataMoeda(multa)}</span>
                            </div>
                        )}
                        {juros > 0 && (
                            <div className="mt-1 flex justify-between text-[#A83232]">
                                <span>Juros ({diasAposVenc - diasCarencia} dias)</span>
                                <span className="font-mono">+{formataMoeda(juros)}</span>
                            </div>
                        )}
                        <div className="mt-2 flex justify-between border-t border-[#EEF0EF] pt-2 font-medium">
                            <span className="text-[#1E2D30]">Valor a pagar</span>
                            <span className="font-mono text-[#0A4F5C]">{formataMoeda(valorCalculado)}</span>
                        </div>
                    </div>

                    {/* Override de valor */}
                    <div>
                        <Label>Valor recebido</Label>
                        <InputMoeda value={valorFinal} onChange={(v) => setValorPago(v)} />
                        <p className="mt-1 text-[10px] text-[#8A918E]">Altere somente se o valor recebido foi diferente do calculado</p>
                        {diferenca !== 0 && (
                            <p className="mt-1 text-xs text-[#8C5A10]">
                                Diferença de {formataMoeda(Math.abs(diferenca))} {diferenca > 0 ? 'a mais' : 'a menos'} do calculado.
                            </p>
                        )}
                    </div>

                    <div>
                        <Label>Observações <span className="text-[#8A918E]">(opcional)</span></Label>
                        <textarea value={observacoes} onChange={(e) => setObservacoes(e.target.value)} rows={2}
                            className="w-full rounded-md border border-[#D8DCDA] bg-white px-3 py-2 text-sm focus:border-[#0A4F5C] focus:outline-none focus:ring-1 focus:ring-[#0A4F5C]" />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)} className="border-[#D8DCDA]">Cancelar</Button>
                    <Button onClick={handleSubmit} disabled={saving || !metodoPagamento} className="bg-[#1B6B3A] text-white hover:bg-[#155A2F]">
                        {saving && <Spinner />}
                        Confirmar pagamento
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
