import { Check } from 'lucide-react';
import { Link } from '@inertiajs/react';
import { formataMoeda } from '@/lib/utils';

type PlanoData = {
    id: number;
    nome: string;
    descricao: string | null;
    limite_imoveis: number;
    valor_mensal: string;
};

type Props = {
    plano: PlanoData;
    destaque?: boolean;
    detalhado?: boolean;
};

const featuresBase = [
    'Gestão de imóveis',
    'Contratos completos',
    'Cobranças automáticas',
    'Repasses inteligentes',
    'Painel do proprietário',
    'Painel do inquilino',
    'Suporte por email',
];

const featuresExtra: Record<string, string[]> = {
    Profissional: ['Relatórios avançados'],
    Business: ['Relatórios avançados', 'API de integração'],
    Enterprise: ['Relatórios avançados', 'API de integração', 'Suporte prioritário'],
};

export function PlanoCard({ plano, destaque = false, detalhado = false }: Props) {
    const valor = parseFloat(plano.valor_mensal);
    const ilimitado = plano.limite_imoveis === 0;
    const features = [...featuresBase, ...(featuresExtra[plano.nome] ?? [])];

    return (
        <div className={`relative flex flex-col rounded-xl border-2 bg-white p-6 ${destaque ? 'border-[#C9A84C] shadow-lg' : 'border-[#D8DCDA]'} ${detalhado ? 'p-8' : ''}`}>
            {destaque && (
                <span className="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-[#C9A84C] px-4 py-1 text-xs font-medium text-white">
                    Mais popular
                </span>
            )}

            <h3 className={`text-xl font-semibold text-[#1E2D30] ${detalhado ? 'text-2xl' : ''}`}>{plano.nome}</h3>

            <div className="mt-4 flex items-baseline gap-1">
                <span className={`font-mono font-bold text-[#0A4F5C] ${detalhado ? 'text-4xl' : 'text-3xl'}`}>
                    {formataMoeda(valor)}
                </span>
                <span className="text-sm text-[#8A918E]">/mês</span>
            </div>

            <p className="mt-2 text-sm text-[#6B7370]">
                {ilimitado ? 'Imóveis ilimitados' : `Até ${plano.limite_imoveis} imóveis`}
            </p>

            {plano.descricao && detalhado && (
                <p className="mt-3 text-sm text-[#8A918E]">{plano.descricao}</p>
            )}

            {detalhado && (
                <ul className="mt-6 space-y-2.5 flex-1">
                    {features.map((f) => (
                        <li key={f} className="flex items-center gap-2 text-sm text-[#3A4240]">
                            <Check className="h-4 w-4 shrink-0 text-[#1B6B3A]" />
                            {f}
                        </li>
                    ))}
                </ul>
            )}

            <Link
                href={`/registro?plano=${plano.id}`}
                className={`mt-6 block rounded-lg py-2.5 text-center text-sm font-medium transition-colors ${destaque ? 'bg-[#C9A84C] text-[#2E2410] hover:bg-[#B8993F]' : 'bg-[#0A4F5C] text-white hover:bg-[#073B45]'}`}
            >
                {detalhado ? 'Começar agora' : 'Escolher plano'}
            </Link>
        </div>
    );
}
