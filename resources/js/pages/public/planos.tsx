import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { JsonLd } from '@/components/json-ld';
import { PlanoCard } from '@/components/public/plano-card';
import { SectionTitle } from '@/components/public/section-title';
import { SeoHead } from '@/components/seo-head';

type PlanoData = { id: number; nome: string; descricao: string | null; limite_imoveis: number; valor_mensal: string; ordem: number };
type Props = { planos: PlanoData[] };

const faqs = [
    { q: 'Posso trocar de plano depois?', a: 'Sim, a qualquer momento. O novo valor será aplicado na próxima fatura. Se fizer downgrade, não poderá cadastrar novos imóveis até adequar ao novo limite.' },
    { q: 'Tem período de teste?', a: 'No momento não oferecemos período de teste gratuito, mas você pode começar com o plano Starter e fazer upgrade quando precisar.' },
    { q: 'Como funciona o pagamento?', a: 'Fatura mensal gerada automaticamente. Aceitamos PIX, boleto bancário e cartão de crédito.' },
    { q: 'Posso cancelar a qualquer momento?', a: 'Sim, sem multa ou fidelidade. Ao cancelar, seus dados ficam preservados por 90 dias caso queira reativar.' },
];

export default function PlanosPage({ planos }: Props) {
    const [openFaq, setOpenFaq] = useState<number | null>(null);

    return (
        <>
            <SeoHead
                title="Planos e Preços — Kimobe"
                description="Conheça os planos do Kimobe. Gestão completa de aluguéis para imobiliárias e proprietários — escolha o plano ideal para o tamanho da sua carteira."
            >
                <JsonLd
                    data={{
                        '@context': 'https://schema.org',
                        '@type': 'ItemList',
                        name: 'Planos Kimobe',
                        itemListElement: planos.map((plano, idx) => ({
                            '@type': 'ListItem',
                            position: idx + 1,
                            item: {
                                '@type': 'Product',
                                name: `Kimobe ${plano.nome}`,
                                description: plano.descricao ?? `Plano ${plano.nome} — até ${plano.limite_imoveis} imóveis administrados.`,
                                brand: { '@type': 'Brand', name: 'Kimobe' },
                                offers: {
                                    '@type': 'Offer',
                                    price: Number(plano.valor_mensal).toFixed(2),
                                    priceCurrency: 'BRL',
                                    availability: 'https://schema.org/InStock',
                                    priceSpecification: {
                                        '@type': 'UnitPriceSpecification',
                                        price: Number(plano.valor_mensal).toFixed(2),
                                        priceCurrency: 'BRL',
                                        unitCode: 'MON',
                                        billingDuration: 1,
                                    },
                                },
                            },
                        })),
                    }}
                />
            </SeoHead>

            {/* Hero */}
            <section className="bg-[#0A4F5C] px-4 pt-28 pb-16 text-center">
                <h1 className="text-3xl font-bold text-white sm:text-4xl">Planos e preços</h1>
                <p className="mt-4 text-lg text-[#B3DDE5]">Escolha o plano ideal para o tamanho da sua carteira.</p>
            </section>

            {/* Cards de planos */}
            <section className="px-4 py-16 md:py-20">
                <div className="mx-auto max-w-6xl">
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        {planos.map((p, i) => (
                            <PlanoCard key={p.id} plano={p} destaque={i === 1} detalhado />
                        ))}
                    </div>
                </div>
            </section>

            {/* FAQ */}
            <section id="faq" className="bg-[#F7F8F7] px-4 py-16 md:py-20">
                <div className="mx-auto max-w-2xl">
                    <SectionTitle titulo="Perguntas frequentes" />
                    <div className="mt-10 space-y-3">
                        {faqs.map((faq, i) => (
                            <div key={i} className="rounded-lg border border-[#D8DCDA] bg-white">
                                <button
                                    onClick={() => setOpenFaq(openFaq === i ? null : i)}
                                    className="flex w-full items-center justify-between px-5 py-4 text-left"
                                >
                                    <span className="text-sm font-medium text-[#1E2D30]">{faq.q}</span>
                                    <ChevronDown className={`h-4 w-4 shrink-0 text-[#8A918E] transition-transform ${openFaq === i ? 'rotate-180' : ''}`} />
                                </button>
                                {openFaq === i && (
                                    <div className="border-t border-[#EEF0EF] px-5 py-4">
                                        <p className="text-sm text-[#6B7370]">{faq.a}</p>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            </section>
        </>
    );
}
