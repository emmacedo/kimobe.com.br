import { Link } from '@inertiajs/react';
import { ArrowRightLeft, ChevronDown, FileText, Home, Receipt, TrendingUp, User } from 'lucide-react';
import { JsonLd } from '@/components/json-ld';
import { FeatureCard } from '@/components/public/feature-card';
import { PlanoCard } from '@/components/public/plano-card';
import { SectionTitle } from '@/components/public/section-title';
import { SeoHead } from '@/components/seo-head';
import { formataMoeda } from '@/lib/utils';

type PlanoData = { id: number; nome: string; descricao: string | null; limite_imoveis: number; valor_mensal: string; ordem: number };
type Props = { planos: PlanoData[] };

const features = [
    { icone: Home, titulo: 'Gestão de imóveis', descricao: 'Cadastre imóveis, fotos, titulares e acompanhe a ocupação da sua carteira.' },
    { icone: FileText, titulo: 'Contratos completos', descricao: 'Contratos com responsabilidades, garantias, fiadores e reajuste automático.' },
    { icone: Receipt, titulo: 'Cobranças automáticas', descricao: 'Gere cobranças mensais automaticamente com IPTU, condomínio e extras inclusos.' },
    { icone: ArrowRightLeft, titulo: 'Repasses inteligentes', descricao: 'Repasse automático por recebimento ou garantido, com split para múltiplos proprietários.' },
    { icone: TrendingUp, titulo: 'Painel do proprietário', descricao: 'Proprietários acompanham rendimentos, repasses e extratos em tempo real.' },
    { icone: User, titulo: 'Painel do inquilino', descricao: 'Inquilinos acessam cobranças, contratos e enviam comprovantes de pagamento.' },
];

const steps = [
    { num: '1', titulo: 'Crie sua conta', desc: 'Cadastre-se e escolha o plano ideal para sua carteira.' },
    { num: '2', titulo: 'Cadastre seus imóveis', desc: 'Adicione imóveis, proprietários e inquilinos ao sistema.' },
    { num: '3', titulo: 'Gerencie tudo', desc: 'Cobranças, repasses e contratos no piloto automático.' },
];

export default function HomePage({ planos }: Props) {
    return (
        <>
            <SeoHead
                title="Kimobe — Gestão de Aluguéis Simplificada"
                description="Administre imóveis, contratos, cobranças e repasses em um só lugar. Plataforma SaaS para imobiliárias e proprietários gerenciarem aluguéis com simplicidade."
            >
                <JsonLd
                    data={{
                        '@context': 'https://schema.org',
                        '@type': 'SoftwareApplication',
                        name: 'Kimobe',
                        applicationCategory: 'BusinessApplication',
                        operatingSystem: 'Web',
                        description:
                            'Plataforma SaaS de gestão de aluguéis para imobiliárias e proprietários. Imóveis, contratos, cobranças, repasses e fiadores em um só lugar.',
                        offers: {
                            '@type': 'AggregateOffer',
                            priceCurrency: 'BRL',
                            lowPrice: planos.length ? Number(planos[0].valor_mensal).toFixed(2) : '49.90',
                            offerCount: planos.length || 4,
                        },
                        inLanguage: 'pt-BR',
                    }}
                />
            </SeoHead>

            {/* Hero */}
            <section className="relative flex min-h-[90vh] items-center bg-[#0A4F5C] px-4 pt-16">
                <div className="mx-auto w-full max-w-7xl">
                    <div className="max-w-2xl">
                        <h1 className="text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-6xl">
                            Gestão de aluguéis <span className="text-[#E4CC82]">simplificada</span>
                        </h1>
                        <p className="mt-6 text-lg text-[#B3DDE5] sm:text-xl">
                            Administre imóveis, contratos, cobranças e repasses em um só lugar. Para imobiliárias e proprietários.
                        </p>
                        <div className="mt-8 flex flex-wrap gap-4">
                            <Link href="/registro" className="rounded-lg bg-[#C9A84C] px-6 py-3 text-base font-medium text-[#2E2410] transition-colors hover:bg-[#B8993F]">
                                Comece agora
                            </Link>
                            <Link href="/planos" className="rounded-lg border border-white/30 px-6 py-3 text-base font-medium text-white transition-colors hover:bg-white/10">
                                Ver planos
                            </Link>
                        </div>
                    </div>
                </div>
                {/* Scroll indicator */}
                <div className="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce">
                    <ChevronDown className="h-6 w-6 text-white/40" />
                </div>
            </section>

            {/* Funcionalidades */}
            <section id="funcionalidades" className="px-4 py-20 md:py-28">
                <div className="mx-auto max-w-7xl">
                    <SectionTitle titulo="Tudo que você precisa para administrar aluguéis" />
                    <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {features.map((f) => <FeatureCard key={f.titulo} {...f} />)}
                    </div>
                </div>
            </section>

            {/* Como funciona */}
            <section className="bg-[#F7F8F7] px-4 py-20 md:py-28">
                <div className="mx-auto max-w-7xl">
                    <SectionTitle titulo="Comece em 3 passos" />
                    <div className="mt-12 grid gap-8 sm:grid-cols-3">
                        {steps.map((s) => (
                            <div key={s.num} className="text-center">
                                <span className="inline-flex h-14 w-14 items-center justify-center rounded-full bg-[#C9A84C] text-2xl font-bold text-white">
                                    {s.num}
                                </span>
                                <h3 className="mt-4 text-lg font-medium text-[#1E2D30]">{s.titulo}</h3>
                                <p className="mt-2 text-sm text-[#6B7370]">{s.desc}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* Números */}
            <section className="bg-[#0A4F5C] px-4 py-16 md:py-20">
                <div className="mx-auto grid max-w-5xl gap-8 sm:grid-cols-3 text-center">
                    {[
                        { valor: `A partir de ${planos.length > 0 ? formataMoeda(planos[0].valor_mensal) : 'R$ 49,90'}`, label: 'Valor acessível por mês' },
                        { valor: '100% online', label: 'Acesse de qualquer lugar' },
                        { valor: 'Múltiplos papéis', label: 'Admin, proprietário e inquilino' },
                    ].map((s) => (
                        <div key={s.valor}>
                            <p className="text-2xl font-bold text-[#E4CC82] sm:text-3xl">{s.valor}</p>
                            <p className="mt-2 text-sm text-[#B3DDE5]">{s.label}</p>
                        </div>
                    ))}
                </div>
            </section>

            {/* Planos */}
            <section id="planos" className="px-4 py-20 md:py-28">
                <div className="mx-auto max-w-7xl">
                    <SectionTitle titulo="Planos para cada tamanho de negócio" />
                    <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        {planos.map((p, i) => (
                            <PlanoCard key={p.id} plano={p} destaque={i === 1} />
                        ))}
                    </div>
                    <div className="mt-8 text-center">
                        <Link href="/planos" className="text-sm text-[#0A4F5C] hover:underline">Ver detalhes dos planos →</Link>
                    </div>
                </div>
            </section>

            {/* CTA Final */}
            <section className="bg-[#073B45] px-4 py-20 md:py-28">
                <div className="mx-auto max-w-2xl text-center">
                    <h2 className="text-3xl font-semibold text-white sm:text-4xl">
                        Pronto para simplificar a gestão dos seus aluguéis?
                    </h2>
                    <p className="mt-4 text-lg text-[#8DCAD6]">Crie sua conta em minutos e comece a usar hoje.</p>
                    <Link href="/registro" className="mt-8 inline-block rounded-lg bg-[#C9A84C] px-8 py-3 text-base font-medium text-[#2E2410] transition-colors hover:bg-[#B8993F]">
                        Criar conta grátis
                    </Link>
                    <p className="mt-3 text-xs text-[#8DCAD6]/70">Sem compromisso. Cancele quando quiser.</p>
                </div>
            </section>
        </>
    );
}
