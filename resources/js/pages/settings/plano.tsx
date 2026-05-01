import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { toast } from 'sonner';
import { PlanoCard } from '@/components/public/plano-card';
import type { PlanoData } from '@/components/public/plano-card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import KimobeLayout from '@/layouts/app/kimobe-layout';
import { formataMoeda } from '@/lib/utils';

type Subscription = {
    fullflow_id: string;
    plan_code: string | null;
    status: string;
    trial_until: string | null;
    current_period_start: string | null;
    current_period_end: string | null;
    amount: string;
    billing_cycle: string;
};

type Charge = {
    valor: number;
    tipo: string;
    status: string;
    vencimento: string;
    pago_em?: string | null;
    link_pagamento?: string | null;
};

type Props = {
    plano_atual: PlanoData | null;
    subscription: Subscription | null;
    cortesia: boolean;
    imoveis_count: number;
    faturas: Charge[];
    planos: PlanoData[];
};

const STATUS_BADGE: Record<string, { bg: string; text: string }> = {
    trial: { bg: 'bg-[#FBF6E5]', text: 'text-[#5D4A0E]' },
    ativa: { bg: 'bg-[#E5F5EC]', text: 'text-[#1B6B3A]' },
    past_due: { bg: 'bg-[#FFF4E5]', text: 'text-[#7A4A0A]' },
    suspensa: { bg: 'bg-[#FCE8E8]', text: 'text-[#5A1010]' },
    cancelamento_agendado: { bg: 'bg-[#EEF0EF]', text: 'text-[#3A4240]' },
    cancelada: { bg: 'bg-[#EEF0EF]', text: 'text-[#3A4240]' },
};

function formatDate(d: string | null) {
    return d ? new Date(d).toLocaleDateString('pt-BR') : '—';
}

function PlanoPage({ plano_atual, subscription, cortesia, imoveis_count, faturas, planos }: Props) {
    const { errors } = usePage().props as { errors?: Record<string, string> };
    const [acceptAutoUpgrade, setAcceptAutoUpgrade] = useState(false);
    const [planoSelecionado, setPlanoSelecionado] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);

    const limite = plano_atual?.modules?.find((m) => m.slug === 'imoveis')?.pivot?.quota_value ?? null;
    const percentUso = limite ? Math.min(100, Math.round((imoveis_count / limite) * 100)) : 0;
    const corBarra = percentUso >= 95 ? 'bg-[#A83232]' : percentUso >= 80 ? 'bg-[#C9A84C]' : 'bg-[#0A4F5C]';

    function subscribe() {
        if (!planoSelecionado) return;
        setProcessing(true);
        router.post(
            '/settings/plano/contratar',
            { plan_code: planoSelecionado, accept_auto_upgrade: acceptAutoUpgrade },
            {
                onFinish: () => setProcessing(false),
                onSuccess: () => toast.success('Assinatura contratada!'),
                onError: () => toast.error('Verifique os campos.'),
            },
        );
    }

    function changePlan(planCode: string) {
        if (!confirm(`Confirmar mudança para o plano ${planCode}?`)) return;
        setProcessing(true);
        router.post(
            '/settings/plano/mudar',
            { plan_code: planCode },
            {
                onFinish: () => setProcessing(false),
                onSuccess: () => toast.success('Plano alterado.'),
                onError: () => toast.error('Não foi possível mudar o plano.'),
            },
        );
    }

    function cancelarAssinatura() {
        const motivo = prompt('Por que você está cancelando? (opcional)');
        if (motivo === null) return;
        if (!confirm('Confirmar cancelamento da assinatura?')) return;
        setProcessing(true);
        router.post(
            '/settings/plano/cancelar',
            { motivo: motivo || '', confirmacao: true },
            {
                onFinish: () => setProcessing(false),
                onSuccess: () => toast.success('Cancelamento processado.'),
                onError: () => toast.error('Não foi possível cancelar.'),
            },
        );
    }

    return (
        <>
            <Head title="Meu plano — Kimobe" />

            <div className="space-y-5">
                <h1 className="text-lg font-medium text-[#1E2D30]">Meu plano</h1>

                {cortesia && (
                    <div className="rounded-[10px] border-l-4 border-[#1B6B3A] bg-[#E5F5EC] p-4 text-sm text-[#1B6B3A]">
                        <p className="font-medium">Conta cortesia</p>
                        <p className="text-xs">Sua conta está com acesso liberado sem cobrança.</p>
                    </div>
                )}

                {subscription ? (
                    <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                        <div className="mb-3 flex items-center justify-between">
                            <p className="text-sm font-medium text-[#1E2D30]">Sua assinatura</p>
                            <span className={`rounded-full px-2 py-0.5 text-xs ${(STATUS_BADGE[subscription.status] ?? STATUS_BADGE.cancelada).bg} ${(STATUS_BADGE[subscription.status] ?? STATUS_BADGE.cancelada).text}`}>
                                {subscription.status}
                            </span>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 text-sm">
                            <div>
                                <p className="text-xs uppercase tracking-wide text-[#8A918E]">Plano</p>
                                <p className="font-medium text-[#1E2D30]">{plano_atual?.name ?? subscription.plan_code} — {formataMoeda(subscription.amount)} / {subscription.billing_cycle}</p>
                            </div>
                            {subscription.trial_until && (
                                <div>
                                    <p className="text-xs uppercase tracking-wide text-[#8A918E]">Trial até</p>
                                    <p>{formatDate(subscription.trial_until)}</p>
                                </div>
                            )}
                            {subscription.current_period_end && (
                                <div>
                                    <p className="text-xs uppercase tracking-wide text-[#8A918E]">Período atual</p>
                                    <p>{formatDate(subscription.current_period_start)} → {formatDate(subscription.current_period_end)}</p>
                                </div>
                            )}
                        </div>

                        {limite !== null && (
                            <div className="mt-4 rounded-md bg-[#F7F8F7] p-3">
                                <div className="mb-1 flex items-center justify-between text-xs">
                                    <span className="text-[#6B7370]">Imóveis cadastrados</span>
                                    <span className="font-medium text-[#1E2D30]">{imoveis_count} / {limite}</span>
                                </div>
                                <div className="h-1.5 w-full overflow-hidden rounded-full bg-white">
                                    <div className={`h-full rounded-full ${corBarra}`} style={{ width: `${percentUso}%` }} />
                                </div>
                                {percentUso >= 80 && (
                                    <p className="mt-1 text-xs font-medium text-[#7A4A0A]">{percentUso}% do limite usado</p>
                                )}
                            </div>
                        )}

                        <div className="mt-4 flex flex-wrap gap-2">
                            {['trial', 'ativa', 'past_due', 'suspensa'].includes(subscription.status) && (
                                <Button variant="outline" size="sm" onClick={cancelarAssinatura} disabled={processing} className="border-[#D8DCDA] text-[#A83232]">
                                    Cancelar assinatura
                                </Button>
                            )}
                        </div>
                    </div>
                ) : (
                    !cortesia && (
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-4 text-sm font-medium text-[#1E2D30]">Você ainda não tem uma assinatura ativa.</p>
                            <p className="text-sm text-[#6B7370]">Escolha um plano abaixo para começar.</p>
                        </div>
                    )
                )}

                {!cortesia && planos.length > 0 && (
                    <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                        <p className="mb-4 text-sm font-medium text-[#1E2D30]">{subscription ? 'Mudar de plano' : 'Escolha seu plano'}</p>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {planos.map((p, i) => (
                                <PlanoCard
                                    key={p.code}
                                    plano={p}
                                    destaque={i === 1}
                                    detalhado
                                    selecionavel={!subscription}
                                    selecionado={planoSelecionado === p.code}
                                    onSelect={!subscription ? () => setPlanoSelecionado(p.code) : undefined}
                                />
                            ))}
                        </div>

                        {subscription ? (
                            <div className="mt-4 grid gap-2 sm:grid-cols-3">
                                {planos
                                    .filter((p) => p.code !== subscription.plan_code)
                                    .map((p) => (
                                        <Button
                                            key={p.code}
                                            variant="outline"
                                            size="sm"
                                            onClick={() => changePlan(p.code)}
                                            disabled={processing}
                                            className="border-[#D8DCDA]"
                                        >
                                            Mudar para {p.name}
                                        </Button>
                                    ))}
                            </div>
                        ) : (
                            <div className="mt-4 space-y-3">
                                <label className="flex items-start gap-2 rounded-lg border border-[#FBF6E5] bg-[#FBF6E5]/40 p-3 text-xs text-[#5D4A0E]">
                                    <Checkbox checked={acceptAutoUpgrade} onCheckedChange={(c) => setAcceptAutoUpgrade(!!c)} className="mt-0.5" />
                                    <span>
                                        Aceito que ao ultrapassar limites do plano, o Kimobe fará <strong>upgrade automático</strong> com cobrança proporcional. Posso desativar no perfil.
                                    </span>
                                </label>
                                {errors?.accept_auto_upgrade && <p className="text-xs text-[#A83232]">{errors.accept_auto_upgrade}</p>}
                                {errors?.plan_code && <p className="text-xs text-[#A83232]">{errors.plan_code}</p>}
                                <Button onClick={subscribe} disabled={!planoSelecionado || !acceptAutoUpgrade || processing} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                                    Contratar plano
                                </Button>
                            </div>
                        )}
                    </div>
                )}

                {faturas.length > 0 && (
                    <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                        <p className="mb-4 text-sm font-medium text-[#1E2D30]">Faturas</p>
                        <ul className="divide-y divide-[#EEF0EF]">
                            {faturas.map((c, idx) => (
                                <li key={idx} className="flex items-center justify-between py-3 text-sm">
                                    <div>
                                        <p className="font-medium text-[#1E2D30]">{formataMoeda(c.valor)} <span className="text-xs font-normal text-[#8A918E]">{c.tipo}</span></p>
                                        <p className="text-xs text-[#6B7370]">
                                            {c.pago_em ? `pago em ${formatDate(c.pago_em)}` : `vence em ${formatDate(c.vencimento)}`}
                                        </p>
                                    </div>
                                    {c.link_pagamento && c.status !== 'paga' && (
                                        <a href={c.link_pagamento} target="_blank" rel="noopener noreferrer" className="rounded-md bg-[#C9A84C] px-3 py-1.5 text-xs font-medium text-[#2E2410] hover:bg-[#B8993F]">
                                            Pagar agora
                                        </a>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </>
    );
}

PlanoPage.layout = (page: React.ReactNode) => <KimobeLayout>{page}</KimobeLayout>;

export default PlanoPage;
