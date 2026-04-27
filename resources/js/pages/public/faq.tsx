import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { JsonLd } from '@/components/json-ld';
import { SectionTitle } from '@/components/public/section-title';
import { SeoHead } from '@/components/seo-head';

const categorias = [
    { nome: 'Sobre o Kimobe', perguntas: [
        { q: 'O que é o Kimobe?', a: 'O Kimobe é um sistema online de gestão de aluguéis para imobiliárias e proprietários de imóveis. Ele centraliza o controle de imóveis, contratos, cobranças e repasses em uma única plataforma.' },
        { q: 'Para quem o Kimobe é indicado?', a: 'Para imobiliárias que administram imóveis de terceiros e para proprietários que gerenciam seus próprios imóveis de aluguel. De 1 a centenas de imóveis.' },
        { q: 'Preciso instalar alguma coisa?', a: 'Não. O Kimobe é 100% online. Basta acessar pelo navegador do computador, tablet ou celular.' },
    ]},
    { nome: 'Planos e pagamento', perguntas: [
        { q: 'Quanto custa o Kimobe?', a: 'Os planos começam em R$ 49,90/mês. O valor depende da quantidade de imóveis que você administra.' },
        { q: 'Posso trocar de plano depois?', a: 'Sim, a qualquer momento. O upgrade é imediato e o novo valor é cobrado a partir da próxima fatura.' },
        { q: 'Quais as formas de pagamento?', a: 'Aceitamos PIX, boleto bancário, cartão de crédito e transferência bancária.' },
        { q: 'O que acontece se eu atrasar o pagamento?', a: 'Você recebe avisos por email e na plataforma. Após o período de carência, o acesso é temporariamente bloqueado até a regularização.' },
    ]},
    { nome: 'Funcionalidades', perguntas: [
        { q: 'Como funciona o repasse aos proprietários?', a: 'O Kimobe calcula automaticamente o valor do repasse descontando a taxa de administração. Você pode escolher entre repasse por recebimento ou repasse garantido.' },
        { q: 'Proprietários e inquilinos podem acessar o sistema?', a: 'Sim. Proprietários acompanham rendimentos e repasses. Inquilinos visualizam cobranças, contratos e enviam comprovantes de pagamento.' },
        { q: 'O sistema faz reajuste automático?', a: 'Sim. Você define o índice (IGPM, IPCA ou fixo) e o mês de reajuste no contrato.' },
    ]},
    { nome: 'Segurança e dados', perguntas: [
        { q: 'Meus dados estão seguros?', a: 'Sim. O Kimobe utiliza criptografia, autenticação com dois fatores (2FA) e isolamento completo de dados entre empresas.' },
        { q: 'Posso cancelar a qualquer momento?', a: 'Sim. Não há fidelidade nem multa por cancelamento.' },
    ]},
];

export default function FaqPage() {
    const [openItem, setOpenItem] = useState<string | null>(null);

    return (
        <>
            <SeoHead
                title="FAQ — Perguntas Frequentes — Kimobe"
                description="Tire suas dúvidas sobre o Kimobe. Saiba mais sobre planos, funcionalidades, pagamento, segurança e como gerenciar aluguéis na plataforma."
            >
                <JsonLd
                    data={{
                        '@context': 'https://schema.org',
                        '@type': 'FAQPage',
                        mainEntity: categorias.flatMap((cat) =>
                            cat.perguntas.map((p) => ({
                                '@type': 'Question',
                                name: p.q,
                                acceptedAnswer: {
                                    '@type': 'Answer',
                                    text: p.a,
                                },
                            })),
                        ),
                    }}
                />
            </SeoHead>

            <section className="bg-[#0A4F5C] px-4 pt-28 pb-16 text-center">
                <h1 className="text-3xl font-bold text-white sm:text-4xl">Perguntas frequentes</h1>
                <p className="mt-4 text-lg text-[#B3DDE5]">Tire suas dúvidas sobre o Kimobe</p>
            </section>

            <section className="px-4 py-16 md:py-20">
                <div className="mx-auto max-w-2xl space-y-10">
                    {categorias.map((cat) => (
                        <div key={cat.nome}>
                            <h2 className="mb-4 text-lg font-semibold text-[#1E2D30]">{cat.nome}</h2>
                            <div className="space-y-2">
                                {cat.perguntas.map((faq) => {
                                    const key = `${cat.nome}-${faq.q}`;
                                    const isOpen = openItem === key;
                                    return (
                                        <div key={key} className="rounded-lg border border-[#D8DCDA] bg-white">
                                            <button onClick={() => setOpenItem(isOpen ? null : key)} className="flex w-full items-center justify-between px-5 py-4 text-left">
                                                <span className="text-sm font-medium text-[#1E2D30]">{faq.q}</span>
                                                <ChevronDown className={`h-4 w-4 shrink-0 text-[#8A918E] transition-transform ${isOpen ? 'rotate-180' : ''}`} />
                                            </button>
                                            {isOpen && <div className="border-t border-[#EEF0EF] px-5 py-4"><p className="text-sm leading-relaxed text-[#6B7370]">{faq.a}</p></div>}
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            <section className="bg-[#F7F8F7] px-4 py-16 text-center">
                <p className="text-lg font-medium text-[#1E2D30]">Não encontrou sua resposta?</p>
                <div className="mt-4 flex justify-center gap-3">
                    <Link href="/contato" className="rounded-lg bg-[#0A4F5C] px-6 py-2.5 text-sm font-medium text-white hover:bg-[#073B45]">Entre em contato</Link>
                    <Link href="/registro" className="rounded-lg bg-[#C9A84C] px-6 py-2.5 text-sm font-medium text-[#2E2410] hover:bg-[#B8993F]">Criar conta</Link>
                </div>
            </section>
        </>
    );
}
