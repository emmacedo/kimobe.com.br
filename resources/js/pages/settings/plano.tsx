import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';
import { formataMoeda } from '@/lib/utils';

type PlanoData = { id: number; nome: string; valor_mensal: string; limite_imoveis: number };
type PlanoCard = PlanoData & { descricao: string | null };
type FaturaData = { id: number; referencia: string; valor: string; data_vencimento: string; data_pagamento: string | null; status: string };

type Props = {
    plano: PlanoData | null;
    cortesia: boolean;
    imoveis_count: number;
    faturas: FaturaData[];
    planos_ativos: PlanoCard[];
};

const statusConfig: Record<string, { label: string; classes: string }> = {
    pendente: { label: 'Pendente', classes: 'bg-[#FDF8E8] text-[#6B5420]' },
    pago: { label: 'Pago', classes: 'bg-[#E7F7ED] text-[#1B6B3A]' },
    atrasado: { label: 'Atrasado', classes: 'bg-[#FDECEC] text-[#A83232]' },
    cancelado: { label: 'Cancelado', classes: 'bg-[#F7F8F7] text-[#6B7370]' },
};

export default function PlanoPage({ plano, cortesia, imoveis_count, faturas, planos_ativos }: Props) {
    const { errors, flash } = usePage().props as any;
    const [dialogOpen, setDialogOpen] = useState(false);
    const [planoSelecionado, setPlanoSelecionado] = useState<number | null>(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => { if (flash?.success) toast.success(flash.success); }, [flash?.success]);

    // Barra de progresso: uso de imóveis
    const limite = plano?.limite_imoveis ?? 0;
    const ilimitado = limite === 0;
    const usoPct = ilimitado ? 0 : Math.min((imoveis_count / limite) * 100, 100);
    const usoCor = usoPct >= 100 ? '#A83232' : usoPct >= 80 ? '#C9A84C' : '#1B6B3A';

    // Próxima fatura pendente
    const proximaFatura = faturas.find((f) => f.status === 'pendente');

    function handleAlterarPlano() {
        if (!planoSelecionado) return;
        setSaving(true);
        router.post('/settings/plano', { plano_id: planoSelecionado }, {
            onFinish: () => { setSaving(false); setDialogOpen(false); },
            onError: () => setSaving(false),
        });
    }

    return (
        <>
            <Head title="Meu plano" />

            {/* Card plano atual */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <div className="flex items-center justify-between">
                    <h2 className="text-sm font-medium text-[#1E2D30]">Plano atual</h2>
                    {cortesia && <Badge className="bg-[#FDF8E8] text-[#6B5420]">Cortesia</Badge>}
                </div>

                {plano ? (
                    <div className="mt-4">
                        <h3 className="text-xl font-semibold text-[#0A4F5C]">{plano.nome}</h3>
                        <div className="mt-1 flex items-baseline gap-1">
                            {cortesia ? (
                                <>
                                    <span className="text-sm text-[#8A918E] line-through">{formataMoeda(plano.valor_mensal)}</span>
                                    <span className="text-sm font-medium text-[#1B6B3A]">Isento</span>
                                    <span className="text-xs text-[#8A918E]">/mês</span>
                                </>
                            ) : (
                                <>
                                    <span className="text-2xl font-semibold tracking-tight text-[#1E2D30]">{formataMoeda(plano.valor_mensal)}</span>
                                    <span className="text-sm text-[#8A918E]">/mês</span>
                                </>
                            )}
                        </div>

                        {/* Barra de uso */}
                        <div className="mt-4">
                            <div className="flex items-center justify-between text-xs text-[#6B7370]">
                                <span>Imóveis cadastrados</span>
                                <span>{imoveis_count} de {ilimitado ? '∞' : limite}</span>
                            </div>
                            {!ilimitado && (
                                <div className="mt-1.5 h-2 rounded-full bg-[#EEF0EF]">
                                    <div className="h-full rounded-full transition-all" style={{ width: `${usoPct}%`, backgroundColor: usoCor }} />
                                </div>
                            )}
                            <p className="mt-1 text-xs text-[#8A918E]">{ilimitado ? 'Imóveis ilimitados' : `Até ${limite} imóveis`}</p>
                        </div>

                        {cortesia && (
                            <p className="mt-3 text-xs text-[#6B5420] bg-[#FDF8E8] rounded-lg px-3 py-2">Sua conta é isenta de cobrança.</p>
                        )}

                        <div className="mt-4">
                            <Button variant="outline" size="sm" className="border-[#D8DCDA]" onClick={() => { setPlanoSelecionado(null); setDialogOpen(true); }}>
                                Alterar plano
                            </Button>
                        </div>
                    </div>
                ) : (
                    <p className="mt-2 text-sm text-[#8A918E]">Nenhum plano associado.</p>
                )}
            </div>

            {/* Card próxima fatura */}
            {!cortesia && proximaFatura && (
                <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                    <h2 className="mb-3 text-sm font-medium text-[#1E2D30]">Próxima fatura</h2>
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-lg font-semibold text-[#1E2D30]">{formataMoeda(proximaFatura.valor)}</p>
                            <p className="text-xs text-[#6B7370]">Vencimento: {proximaFatura.data_vencimento}</p>
                        </div>
                        <Badge className={statusConfig[proximaFatura.status]?.classes ?? 'bg-[#F7F8F7] text-[#6B7370]'}>
                            {statusConfig[proximaFatura.status]?.label ?? proximaFatura.status}
                        </Badge>
                    </div>
                </div>
            )}

            {/* Card faturas */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-3 text-sm font-medium text-[#1E2D30]">Faturas</h2>
                {cortesia ? (
                    <p className="text-sm text-[#8A918E]">Sua conta é isenta de cobrança. Nenhuma fatura é gerada.</p>
                ) : faturas.length === 0 ? (
                    <p className="text-sm text-[#8A918E]">Nenhuma fatura gerada ainda.</p>
                ) : (
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Referência</TableHead>
                                <TableHead className="text-right text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Valor</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Vencimento</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Pagamento</TableHead>
                                <TableHead className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {faturas.map((f) => (
                                <TableRow key={f.id} className="border-b border-[#F7F8F7]">
                                    <TableCell className="text-sm font-mono text-[#1E2D30]">{f.referencia}</TableCell>
                                    <TableCell className="text-right text-sm font-mono">{formataMoeda(f.valor)}</TableCell>
                                    <TableCell className="text-xs text-[#6B7370]">{f.data_vencimento}</TableCell>
                                    <TableCell className="text-xs text-[#6B7370]">{f.data_pagamento ?? '—'}</TableCell>
                                    <TableCell>
                                        <Badge variant="secondary" className={statusConfig[f.status]?.classes ?? ''}>
                                            {statusConfig[f.status]?.label ?? f.status}
                                        </Badge>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>

            {/* Dialog alterar plano */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-2xl">
                    <DialogHeader><DialogTitle>Alterar plano</DialogTitle></DialogHeader>
                    <div className="grid gap-3 sm:grid-cols-2">
                        {planos_ativos.map((p) => {
                            const isAtual = p.id === plano?.id;
                            const isSelecionado = p.id === planoSelecionado;
                            // Verifica se downgrade é possível
                            const limiteNovo = p.limite_imoveis;
                            const downgradeBloqueado = limiteNovo > 0 && imoveis_count > limiteNovo;

                            return (
                                <button
                                    key={p.id}
                                    type="button"
                                    disabled={isAtual || downgradeBloqueado}
                                    onClick={() => setPlanoSelecionado(p.id)}
                                    className={`relative rounded-xl border-2 p-4 text-left transition-all ${
                                        isAtual ? 'border-[#D8DCDA] bg-[#F7F8F7] opacity-70 cursor-default'
                                        : isSelecionado ? 'border-[#0A4F5C] bg-[#F0F7F8] shadow-md'
                                        : downgradeBloqueado ? 'border-[#D8DCDA] opacity-50 cursor-not-allowed'
                                        : 'border-[#D8DCDA] hover:border-[#8A918E] cursor-pointer'
                                    }`}
                                >
                                    {isAtual && <Badge className="absolute -top-2 right-3 bg-[#0A4F5C] text-white text-[10px]">Atual</Badge>}
                                    <h4 className="font-semibold text-[#1E2D30]">{p.nome}</h4>
                                    <p className="mt-1 text-lg font-semibold tracking-tight text-[#0A4F5C]">{formataMoeda(p.valor_mensal)}<span className="text-xs font-normal text-[#8A918E]">/mês</span></p>
                                    <p className="mt-1 text-xs text-[#6B7370]">{p.limite_imoveis === 0 ? 'Imóveis ilimitados' : `Até ${p.limite_imoveis} imóveis`}</p>
                                    {downgradeBloqueado && (
                                        <p className="mt-2 text-[10px] text-[#A83232]">Você tem {imoveis_count} imóveis. Reduza para no máximo {limiteNovo} antes do downgrade.</p>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                    {errors?.plano_id && <p className="text-xs text-[#A83232]">{errors.plano_id}</p>}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDialogOpen(false)} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button onClick={handleAlterarPlano} disabled={saving || !planoSelecionado} className="bg-[#C9A84C] text-[#2E2410] hover:bg-[#B8993F]">
                            {saving && <Spinner />}Confirmar alteração
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
