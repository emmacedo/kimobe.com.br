import { Head, Link, router, usePage } from '@inertiajs/react';
import { FileEdit } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Template = { id: number; modulo: string; chave: string; nome: string; assunto: string; ativo: boolean; envios_count: number };
type Props = { templates: Template[] };

export default function TemplatesIndex({ templates }: Props) {
    const { flash } = usePage().props as any;
    const [tab, setTab] = useState<'kimobe' | 'admin'>('kimobe');

    useEffect(() => { if (flash?.success) toast.success(flash.success); }, [flash?.success]);

    const filtrados = templates.filter((t) => t.modulo === tab);

    return (
        <>
            <Head title="Admin — Templates de email" />
            <div className="space-y-4">
                <PageHeader titulo="Templates de email" />

                <div className="flex gap-2">
                    {[{ key: 'kimobe' as const, label: 'Kimobe → Assinante' }, { key: 'admin' as const, label: 'Administrador → Clientes' }].map((t) => (
                        <button key={t.key} onClick={() => setTab(t.key)}
                            className={`rounded-md px-4 py-1.5 text-sm font-medium transition-colors ${tab === t.key ? 'bg-[#0A4F5C] text-white' : 'bg-white text-[#6B7370] border border-[#D8DCDA]'}`}>
                            {t.label}
                        </button>
                    ))}
                </div>

                <div className="grid gap-3 sm:grid-cols-2">
                    {filtrados.map((t) => (
                        <div key={t.id} className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <div className="mb-2 flex items-center justify-between">
                                <h3 className="text-sm font-medium text-[#1E2D30]">{t.nome}</h3>
                                <Badge variant="secondary" className={t.ativo ? 'bg-[#E7F7ED] text-[#1B6B3A]' : 'bg-[#F7F8F7] text-[#6B7370]'}>
                                    {t.ativo ? 'Ativo' : 'Desativado'}
                                </Badge>
                            </div>
                            <p className="mb-1 font-mono text-[10px] text-[#8A918E]">{t.chave}</p>
                            <p className="mb-3 text-xs text-[#6B7370]">Assunto: {t.assunto}</p>
                            <div className="flex items-center justify-between">
                                <span className="text-[10px] text-[#8A918E]">{t.envios_count} email(s) enviado(s)</span>
                                <div className="flex gap-2">
                                    <Button variant="outline" size="sm" className="border-[#D8DCDA] text-xs"
                                        onClick={() => router.patch(`/admin/templates/${t.id}/toggle-status`)}>
                                        {t.ativo ? 'Desativar' : 'Ativar'}
                                    </Button>
                                    <Button size="sm" className="bg-[#0A4F5C] text-white hover:bg-[#073B45] text-xs" asChild>
                                        <Link href={`/admin/templates/${t.id}/editar`}><FileEdit className="mr-1 h-3 w-3" />Editar</Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}
