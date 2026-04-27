import { SeoHead } from '@/components/seo-head';

type Props = {
    titulo: string;
    conteudo: string;
    meta_description: string | null;
    updated_at: string;
};

export default function PaginaInstitucional({ titulo, conteudo, meta_description, updated_at }: Props) {
    return (
        <>
            <SeoHead
                title={`${titulo} — Kimobe`}
                description={meta_description ?? `${titulo} — Kimobe.`}
                type="article"
            />

            {/* Hero */}
            <section className="bg-[#0A4F5C] px-4 pt-28 pb-12 text-center">
                <h1 className="text-3xl font-bold text-white">{titulo}</h1>
            </section>

            {/* Conteúdo */}
            <section className="px-4 py-10 md:py-14">
                <div className="mx-auto max-w-[800px]">
                    <div className="rounded-xl border border-[#D8DCDA] bg-white p-6 md:p-10">
                        <div
                            className="conteudo-institucional"
                            dangerouslySetInnerHTML={{ __html: conteudo }}
                        />
                        <div className="mt-10 border-t border-[#EEF0EF] pt-4">
                            <p className="text-xs text-[#8A918E]">Última atualização: {updated_at}</p>
                        </div>
                    </div>
                </div>
            </section>

            {/* Estilos do conteúdo HTML renderizado */}
            <style>{`
                .conteudo-institucional h2 {
                    font-size: 1.375rem;
                    font-weight: 600;
                    color: #0A4F5C;
                    margin-top: 2rem;
                    margin-bottom: 0.75rem;
                    line-height: 1.3;
                }
                .conteudo-institucional h2:first-child {
                    margin-top: 0;
                }
                .conteudo-institucional h3 {
                    font-size: 1.125rem;
                    font-weight: 500;
                    color: #1E2D30;
                    margin-top: 1.5rem;
                    margin-bottom: 0.5rem;
                    line-height: 1.4;
                }
                .conteudo-institucional p {
                    font-size: 1rem;
                    color: #3A4240;
                    line-height: 1.8;
                    margin-bottom: 1rem;
                }
                .conteudo-institucional ul,
                .conteudo-institucional ol {
                    padding-left: 1.5rem;
                    margin-bottom: 1rem;
                    color: #3A4240;
                }
                .conteudo-institucional ul {
                    list-style-type: disc;
                }
                .conteudo-institucional ol {
                    list-style-type: decimal;
                }
                .conteudo-institucional li {
                    font-size: 1rem;
                    line-height: 1.8;
                    margin-bottom: 0.25rem;
                }
                .conteudo-institucional strong {
                    font-weight: 600;
                }
                .conteudo-institucional a {
                    color: #C9A84C;
                    text-decoration: none;
                }
                .conteudo-institucional a:hover {
                    text-decoration: underline;
                }
            `}</style>
        </>
    );
}
