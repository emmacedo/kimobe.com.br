import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Pagina = {
    id: number;
    slug: string;
    titulo: string;
    publicado: boolean;
    updated_at: string;
    atualizado_por_nome: string | null;
};

type Props = { paginas: Pagina[] };

export default function PaginasIndex({ paginas }: Props) {
    return (
        <>
            <Head title="Admin — Páginas" />
            <div className="space-y-4">
                <PageHeader titulo="Páginas institucionais" />

                <div className="grid gap-4 sm:grid-cols-2">
                    {paginas.map((p) => (
                        <div key={p.id} className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <h3 className="text-base font-medium text-[#1E2D30]">{p.titulo}</h3>
                                    <Badge
                                        variant="secondary"
                                        className={p.publicado ? 'bg-[#E7F7ED] text-[#1B6B3A]' : 'bg-[#F7F8F7] text-[#6B7370]'}
                                    >
                                        {p.publicado ? 'Publicada' : 'Rascunho'}
                                    </Badge>
                                </div>
                                <Link href={`/admin/paginas/${p.id}/editar`}>
                                    <Button variant="ghost" size="icon" className="h-7 w-7">
                                        <Pencil className="h-3.5 w-3.5 text-[#6B7370]" />
                                    </Button>
                                </Link>
                            </div>
                            <p className="mt-1 font-mono text-xs text-[#8A918E]">/{p.slug}</p>
                            <p className="mt-2 text-xs text-[#8A918E]">
                                Atualizada em {p.updated_at}
                                {p.atualizado_por_nome && ` por ${p.atualizado_por_nome}`}
                            </p>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}
