import { Check } from 'lucide-react';
import { Link } from '@inertiajs/react';

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
    /** Modo seleção: exibe card clicável sem o link de ação */
    selecionavel?: boolean;
    selecionado?: boolean;
    onSelect?: () => void;
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

/**
 * Formata valor para exibição separada: { reais: "129", centavos: "90" }
 */
function formatarPreco(valorStr: string): { reais: string; centavos: string } {
    const valor = parseFloat(valorStr);
    const partes = valor.toFixed(2).split('.');
    // Formata a parte inteira com separador de milhar pt-BR
    const reais = parseInt(partes[0]).toLocaleString('pt-BR');
    return { reais, centavos: partes[1] };
}

export function PlanoCard({ plano, destaque = false, detalhado = false, selecionavel = false, selecionado = false, onSelect }: Props) {
    const ilimitado = plano.limite_imoveis === 0;
    const features = [...featuresBase, ...(featuresExtra[plano.nome] ?? [])];
    const preco = formatarPreco(plano.valor_mensal);

    // Monta as classes do card conforme estado
    const cardClasses = [
        'relative flex flex-col rounded-xl bg-white overflow-visible',
        detalhado ? 'p-8' : 'p-6',
        // Borda e sombra conforme estado
        selecionado
            ? 'border-2 border-[#0A4F5C] bg-[#F0F7F8] shadow-md'
            : destaque
              ? 'border-2 border-[#C9A84C] shadow-lg'
              : 'border border-[#D8DCDA]',
        // Hover para cards selecionáveis
        selecionavel ? 'cursor-pointer transition-all hover:shadow-md' : '',
    ].join(' ');

    // Padding-top extra quando tem badge para não sobrepor o conteúdo
    const innerPt = destaque ? 'pt-3' : '';

    const cardContent = (
        <div className={cardClasses} onClick={selecionavel ? onSelect : undefined}>
            {/* Badge "Mais popular" — centralizado como selo sobre a borda superior */}
            {destaque && (
                <span
                    className="absolute left-1/2 -translate-x-1/2 rounded-full bg-[#C9A84C] px-5 py-1 text-xs font-medium text-white shadow-sm"
                    style={{ top: '-14px' }}
                >
                    Mais popular
                </span>
            )}

            <div className={innerPt}>
                {/* Nome do plano — fonte do sistema, semibold */}
                <h3 className={`font-semibold text-[#1E2D30] ${detalhado ? 'text-xl' : 'text-lg'}`}>
                    {plano.nome}
                </h3>

                {/* Preço — R$ discreto, valor grande e compacto, /mês discreto */}
                <div className="mt-2 flex items-baseline gap-1">
                    <span className="text-sm font-normal text-[#6B7370]">R$</span>
                    <span className={`font-semibold tracking-tight text-[#0A4F5C] ${detalhado ? 'text-4xl' : 'text-3xl'}`}>
                        {preco.reais},{preco.centavos}
                    </span>
                    <span className="text-sm font-normal text-[#8A918E]">/mês</span>
                </div>

                {/* Limite de imóveis */}
                <p className="mt-1 text-sm text-[#6B7370]">
                    {ilimitado ? 'Imóveis ilimitados' : `Até ${plano.limite_imoveis} imóveis`}
                </p>

                {/* Descrição (apenas modo detalhado) */}
                {plano.descricao && detalhado && (
                    <p className="mt-3 text-sm text-[#8A918E]">{plano.descricao}</p>
                )}

                {/* Lista de features (apenas modo detalhado) */}
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

                {/* Botão de ação — não aparece no modo selecionável (registro usa botão externo "Próximo") */}
                {!selecionavel && (
                    <Link
                        href={`/registro?plano=${plano.id}`}
                        className={`mt-6 block rounded-lg py-2.5 text-center text-sm font-medium transition-colors ${
                            destaque
                                ? 'bg-[#C9A84C] text-[#2E2410] hover:bg-[#B8993F]'
                                : 'bg-[#0A4F5C] text-white hover:bg-[#073B45]'
                        }`}
                    >
                        {detalhado ? 'Começar agora' : 'Escolher plano'}
                    </Link>
                )}
            </div>
        </div>
    );

    return cardContent;
}
