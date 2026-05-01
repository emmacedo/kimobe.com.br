import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';

type Module = {
    slug: string;
    label: string;
    type: 'boolean' | 'quantity';
    pivot?: { quota_value: number | null };
};

export type PlanoData = {
    code: string;
    name: string;
    description: string | null;
    amount: string;
    billing_cycle: string;
    trial_days: number;
    modules?: Module[];
};

type Props = {
    plano: PlanoData;
    destaque?: boolean;
    detalhado?: boolean;
    /** Modo seleção: exibe card clicável sem o link de ação */
    selecionavel?: boolean;
    selecionado?: boolean;
    onSelect?: () => void;
};

/** Funcionalidades comuns a todos os planos (apresentação visual). */
const featuresBase = [
    'Gestão de imóveis',
    'Contratos completos',
    'Cobranças automáticas',
    'Repasses inteligentes',
    'Painel do proprietário',
    'Painel do inquilino',
    'Suporte por email',
];

/** Mapa slug → label visual para os módulos extras (boolean) do FullFlow. */
const moduleLabels: Record<string, string> = {
    integracao_meio_pagamento: 'Integração com meio de pagamento',
    dominio_proprio: 'Domínio próprio (whitelabel)',
};

/** Formata valor para exibição separada: { reais: "129", centavos: "90" } */
function formatarPreco(valorStr: string): { reais: string; centavos: string } {
    const valor = parseFloat(valorStr);
    const partes = valor.toFixed(2).split('.');
    const reais = parseInt(partes[0]).toLocaleString('pt-BR');
    return { reais, centavos: partes[1] };
}

/** Quota numérica do módulo `imoveis` (ou null se não houver). */
function quotaImoveis(plano: PlanoData): number | null {
    const m = plano.modules?.find((m) => m.slug === 'imoveis');
    return m?.pivot?.quota_value ?? null;
}

/** Lista de features extra a partir dos módulos boolean do plano. */
function featuresExtraDoPlano(plano: PlanoData): string[] {
    return (plano.modules ?? [])
        .filter((m) => m.type === 'boolean' && m.slug in moduleLabels)
        .map((m) => moduleLabels[m.slug]);
}

export function PlanoCard({ plano, destaque = false, detalhado = false, selecionavel = false, selecionado = false, onSelect }: Props) {
    const limite = quotaImoveis(plano);
    const ilimitado = limite === null || limite >= 9999;
    const features = [...featuresBase, ...featuresExtraDoPlano(plano)];
    const preco = formatarPreco(plano.amount);

    const cardClasses = [
        'relative flex flex-col rounded-xl bg-white overflow-visible',
        detalhado ? 'p-8' : 'p-6',
        selecionado
            ? 'border-2 border-[#0A4F5C] bg-[#F0F7F8] shadow-md'
            : destaque
              ? 'border-2 border-[#C9A84C] shadow-lg'
              : 'border border-[#D8DCDA]',
        selecionavel ? 'cursor-pointer transition-all hover:shadow-md' : '',
    ].join(' ');

    const innerPt = destaque ? 'pt-3' : '';

    return (
        <div className={cardClasses} onClick={selecionavel ? onSelect : undefined}>
            {destaque && (
                <span
                    className="absolute left-1/2 -translate-x-1/2 rounded-full bg-[#C9A84C] px-5 py-1 text-xs font-medium text-white shadow-sm"
                    style={{ top: '-14px' }}
                >
                    Mais popular
                </span>
            )}

            <div className={innerPt}>
                <h3 className={`font-semibold text-[#1E2D30] ${detalhado ? 'text-xl' : 'text-lg'}`}>{plano.name}</h3>

                <div className="mt-2 flex items-baseline gap-1">
                    <span className="text-sm font-normal text-[#6B7370]">R$</span>
                    <span className={`font-semibold tracking-tight text-[#0A4F5C] ${detalhado ? 'text-4xl' : 'text-3xl'}`}>
                        {preco.reais},{preco.centavos}
                    </span>
                    <span className="text-sm font-normal text-[#8A918E]">/{plano.billing_cycle}</span>
                </div>

                <p className="mt-1 text-sm text-[#6B7370]">{ilimitado ? 'Imóveis ilimitados' : `Até ${limite} imóveis`}</p>

                {plano.trial_days > 0 && (
                    <p className="mt-1 text-xs font-medium text-[#1B6B3A]">{plano.trial_days} dias de teste grátis</p>
                )}

                {plano.description && detalhado && <p className="mt-3 text-sm text-[#8A918E]">{plano.description}</p>}

                {detalhado && (
                    <ul className="mt-6 flex-1 space-y-2.5">
                        {features.map((f) => (
                            <li key={f} className="flex items-center gap-2 text-sm text-[#3A4240]">
                                <Check className="h-4 w-4 shrink-0 text-[#1B6B3A]" />
                                {f}
                            </li>
                        ))}
                    </ul>
                )}

                {!selecionavel && (
                    <Link
                        href={`/registro?plano=${plano.code}`}
                        className={`mt-6 block rounded-lg py-2.5 text-center text-sm font-medium transition-colors ${
                            destaque ? 'bg-[#C9A84C] text-[#2E2410] hover:bg-[#B8993F]' : 'bg-[#0A4F5C] text-white hover:bg-[#073B45]'
                        }`}
                    >
                        {detalhado ? 'Começar agora' : 'Escolher plano'}
                    </Link>
                )}
            </div>
        </div>
    );
}
