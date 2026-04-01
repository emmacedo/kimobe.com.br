import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ExternalLink } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type PaginaData = {
    id: number;
    slug: string;
    titulo: string;
    conteudo: string;
    meta_description: string | null;
    publicado: boolean;
    updated_at: string;
    atualizado_por_nome: string | null;
};

type Props = { pagina: PaginaData };

/**
 * Estilos CSS do conteúdo institucional — usados no iframe de preview.
 */
const previewStyles = `
    body { font-family: system-ui, -apple-system, sans-serif; padding: 24px; margin: 0; color: #3A4240; }
    h2 { font-size: 1.375rem; font-weight: 600; color: #0A4F5C; margin-top: 2rem; margin-bottom: 0.75rem; line-height: 1.3; }
    h2:first-child { margin-top: 0; }
    h3 { font-size: 1.125rem; font-weight: 500; color: #1E2D30; margin-top: 1.5rem; margin-bottom: 0.5rem; line-height: 1.4; }
    p { font-size: 1rem; color: #3A4240; line-height: 1.8; margin-bottom: 1rem; }
    ul, ol { padding-left: 1.5rem; margin-bottom: 1rem; }
    ul { list-style-type: disc; }
    ol { list-style-type: decimal; }
    li { font-size: 1rem; line-height: 1.8; margin-bottom: 0.25rem; }
    strong { font-weight: 600; }
    a { color: #C9A84C; text-decoration: none; }
    a:hover { text-decoration: underline; }
`;

export default function EditarPagina({ pagina }: Props) {
    const { flash } = usePage().props as any;
    const [titulo, setTitulo] = useState(pagina.titulo);
    const [conteudo, setConteudo] = useState(pagina.conteudo);
    const [metaDescription, setMetaDescription] = useState(pagina.meta_description ?? '');
    const [publicado, setPublicado] = useState(pagina.publicado);
    const [saving, setSaving] = useState(false);
    const iframeRef = useRef<HTMLIFrameElement>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    // Atualiza o preview com debounce
    const debounceRef = useRef<ReturnType<typeof setTimeout>>();
    const atualizarPreview = useCallback((html: string) => {
        clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => {
            const iframe = iframeRef.current;
            if (!iframe) return;
            const doc = iframe.contentDocument;
            if (!doc) return;
            doc.open();
            doc.write(`<!DOCTYPE html><html><head><style>${previewStyles}</style></head><body>${html}</body></html>`);
            doc.close();
        }, 300);
    }, []);

    // Preview inicial + a cada mudança
    useEffect(() => {
        atualizarPreview(conteudo);
    }, [conteudo, atualizarPreview]);

    function handleSalvar() {
        setSaving(true);
        router.put(`/admin/paginas/${pagina.id}`, {
            titulo,
            conteudo,
            meta_description: metaDescription || null,
            publicado,
        }, {
            onFinish: () => setSaving(false),
            onError: () => { setSaving(false); toast.error('Erro ao salvar.'); },
        });
    }

    // URL pública da página
    const urlPublica = `/${pagina.slug}`;

    return (
        <>
            <Head title={`Admin — Editar: ${pagina.titulo}`} />
            <div className="space-y-4">
                <PageHeader titulo={`Editar: ${pagina.titulo}`}>
                    <Button variant="outline" size="sm" className="border-[#D8DCDA]" onClick={() => window.history.back()}>
                        <ArrowLeft className="mr-1 h-3.5 w-3.5" />Voltar
                    </Button>
                </PageHeader>

                {/* Alerta jurídico */}
                <div className="rounded-lg border border-[#E4CC82] bg-[#FDF8E8] p-4">
                    <p className="text-sm text-[#6B5420]">
                        <strong>Atenção:</strong> este conteúdo é um modelo inicial. Consulte um advogado para validar os termos antes de publicar oficialmente.
                    </p>
                </div>

                <div className="grid gap-4 lg:grid-cols-5">
                    {/* Coluna esquerda — editor */}
                    <div className="space-y-4 lg:col-span-3">
                        {/* Card configurações */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5 space-y-3">
                            <div>
                                <Label>Título</Label>
                                <Input value={titulo} onChange={(e) => setTitulo(e.target.value)} className="bg-white border-[#D8DCDA]" />
                            </div>
                            <div>
                                <Label>Slug (URL)</Label>
                                <Input value={`/${pagina.slug}`} disabled className="bg-[#F7F8F7] font-mono text-sm" />
                                <p className="mt-1 text-[10px] text-[#8A918E]">O slug não pode ser alterado para não quebrar links existentes.</p>
                            </div>
                            <div>
                                <Label>Meta description <span className="text-[#8A918E]">(SEO)</span></Label>
                                <Input value={metaDescription} onChange={(e) => setMetaDescription(e.target.value)} placeholder="Descrição para mecanismos de busca" className="bg-white border-[#D8DCDA]" maxLength={255} />
                                <p className="mt-1 text-[10px] text-[#8A918E]">{metaDescription.length}/255 caracteres</p>
                            </div>
                            <div className="flex items-center gap-3">
                                <Label>Status</Label>
                                <button
                                    type="button"
                                    onClick={() => setPublicado(!publicado)}
                                    className={`relative h-6 w-11 rounded-full transition-colors ${publicado ? 'bg-[#1B6B3A]' : 'bg-[#D8DCDA]'}`}
                                >
                                    <span className={`absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white transition-transform ${publicado ? 'translate-x-5' : ''}`} />
                                </button>
                                <span className="text-sm text-[#3A4240]">{publicado ? 'Publicada' : 'Rascunho'}</span>
                            </div>
                        </div>

                        {/* Card editor de conteúdo */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <Label>Conteúdo (HTML)</Label>
                            <textarea
                                value={conteudo}
                                onChange={(e) => setConteudo(e.target.value)}
                                rows={24}
                                className="mt-2 w-full rounded-md border border-[#D8DCDA] bg-white px-3 py-2 font-mono text-sm leading-relaxed focus:border-[#0A4F5C] focus:outline-none focus:ring-1 focus:ring-[#0A4F5C]"
                            />
                        </div>

                        {/* Rodapé */}
                        <div className="flex items-center justify-between">
                            <p className="text-xs text-[#8A918E]">
                                Última edição em {pagina.updated_at}
                                {pagina.atualizado_por_nome && ` por ${pagina.atualizado_por_nome}`}
                            </p>
                            <div className="flex gap-2">
                                <a href={urlPublica} target="_blank" rel="noopener noreferrer">
                                    <Button variant="outline" size="sm" className="border-[#D8DCDA]">
                                        <ExternalLink className="mr-1 h-3.5 w-3.5" />Visualizar página
                                    </Button>
                                </a>
                                <Button
                                    onClick={handleSalvar}
                                    disabled={saving || !titulo || !conteudo}
                                    size="sm"
                                    className="bg-[#C9A84C] text-[#2E2410] hover:bg-[#B8993F]"
                                >
                                    {saving && <Spinner />}Salvar
                                </Button>
                            </div>
                        </div>
                    </div>

                    {/* Coluna direita — preview */}
                    <div className="lg:col-span-2">
                        <div className="sticky top-4 rounded-[10px] border border-[#D8DCDA] bg-white">
                            <div className="border-b border-[#EEF0EF] px-4 py-2">
                                <p className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Preview</p>
                            </div>
                            <iframe
                                ref={iframeRef}
                                title="Preview"
                                className="h-[600px] w-full rounded-b-[10px]"
                                sandbox="allow-same-origin"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
