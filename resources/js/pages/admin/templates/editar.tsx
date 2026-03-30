import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft, Eye, Send } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/components/ui/table';

type Variavel = { var: string; desc: string };
type Template = { id: number; modulo: string; chave: string; nome: string; descricao: string | null; assunto: string; corpo_html: string; corpo_texto: string | null; variaveis_disponiveis: Variavel[]; ativo: boolean; envios_count: number };
type Props = { template: Template };

function getCsrfToken() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''; }

export default function EditarTemplate({ template }: Props) {
    const { flash, errors } = usePage().props as any;
    const [nome, setNome] = useState(template.nome);
    const [assunto, setAssunto] = useState(template.assunto);
    const [corpoHtml, setCorpoHtml] = useState(template.corpo_html);
    const [corpoTexto, setCorpoTexto] = useState(template.corpo_texto ?? '');
    const [ativo, setAtivo] = useState(template.ativo);
    const [saving, setSaving] = useState(false);
    const [previewHtml, setPreviewHtml] = useState('');
    const [previewWidth, setPreviewWidth] = useState(600);
    const [testeSending, setTesteSending] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => { if (flash?.success) toast.success(flash.success); }, [flash?.success]);

    // Preview com debounce
    const atualizarPreview = useCallback(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(async () => {
            try {
                const resp = await fetch(`/admin/templates/${template.id}/preview`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
                    body: JSON.stringify({ corpo_html: corpoHtml }),
                });
                const data = await resp.json();
                setPreviewHtml(data.html);
            } catch { /* ignore */ }
        }, 500);
    }, [corpoHtml, template.id]);

    useEffect(() => { atualizarPreview(); }, [atualizarPreview]);

    function handleSalvar() {
        setSaving(true);
        router.put(`/admin/templates/${template.id}`, { nome, assunto, corpo_html: corpoHtml, corpo_texto: corpoTexto || null, ativo }, {
            onFinish: () => setSaving(false),
        });
    }

    async function handleEnviarTeste() {
        setTesteSending(true);
        try {
            const resp = await fetch(`/admin/templates/${template.id}/enviar-teste`, {
                method: 'POST', headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
            });
            const data = await resp.json();
            toast.success(data.message);
        } catch { toast.error('Erro ao enviar teste.'); }
        finally { setTesteSending(false); }
    }

    function inserirVariavel(varName: string) {
        setCorpoHtml((prev) => prev + `{{${varName}}}`);
    }

    return (
        <>
            <Head title={`Admin — Editar template: ${template.nome}`} />
            <div className="space-y-4">
                <PageHeader titulo={template.nome}>
                    <div className="flex items-center gap-2">
                        <Badge variant="secondary" className={ativo ? 'bg-[#E7F7ED] text-[#1B6B3A]' : 'bg-[#F7F8F7] text-[#6B7370]'}>
                            {ativo ? 'Ativo' : 'Desativado'}
                        </Badge>
                        <Button variant="outline" size="sm" asChild className="border-[#D8DCDA]">
                            <a href="/admin/templates"><ArrowLeft className="mr-1 h-3.5 w-3.5" />Voltar</a>
                        </Button>
                    </div>
                </PageHeader>

                <div className="grid gap-4 lg:grid-cols-2">
                    {/* Editor */}
                    <div className="space-y-4">
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Configurações</h2>
                            <div className="space-y-3">
                                <div><Label>Nome do template</Label><Input value={nome} onChange={(e) => setNome(e.target.value)} className="bg-white border-[#D8DCDA]" /><InputError message={errors?.nome} /></div>
                                <div><Label>Assunto do email</Label><Input value={assunto} onChange={(e) => setAssunto(e.target.value)} className="bg-white border-[#D8DCDA]" /><p className="mt-1 text-[10px] text-[#8A918E]">Use {'{{variavel}}'} para inserir valores dinâmicos</p><InputError message={errors?.assunto} /></div>
                                <div className="flex items-center gap-2"><Checkbox checked={ativo} onCheckedChange={(c) => setAtivo(!!c)} /><Label>Template ativo</Label></div>
                                {!ativo && <p className="rounded-md bg-[#FFF4E5] p-2 text-xs text-[#8C5A10]">Este email não será enviado quando o evento ocorrer.</p>}
                            </div>
                        </div>

                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-sm font-medium text-[#1E2D30]">Corpo do email (HTML)</h2>
                                <div className="flex gap-1">
                                    {template.variaveis_disponiveis.slice(0, 4).map((v) => (
                                        <button key={v.var} type="button" onClick={() => inserirVariavel(v.var)}
                                            className="rounded bg-[#E8F4F6] px-2 py-0.5 text-[10px] text-[#0A4F5C] hover:bg-[#0A4F5C] hover:text-white">
                                            {`{{${v.var}}}`}
                                        </button>
                                    ))}
                                </div>
                            </div>
                            <textarea value={corpoHtml} onChange={(e) => setCorpoHtml(e.target.value)} rows={14}
                                className="w-full rounded-md border border-[#D8DCDA] bg-white px-3 py-2 font-mono text-xs focus:border-[#0A4F5C] focus:outline-none focus:ring-1 focus:ring-[#0A4F5C]" />
                        </div>

                        {/* Variáveis disponíveis */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <h2 className="mb-3 text-sm font-medium text-[#1E2D30]">Variáveis disponíveis</h2>
                            <Table>
                                <TableHeader><TableRow className="bg-[#F7F8F7] hover:bg-[#F7F8F7]">
                                    <TableHead className="text-[10px] uppercase text-[#8A918E]">Variável</TableHead>
                                    <TableHead className="text-[10px] uppercase text-[#8A918E]">Descrição</TableHead>
                                </TableRow></TableHeader>
                                <TableBody>
                                    {template.variaveis_disponiveis.map((v) => (
                                        <TableRow key={v.var} className="border-b border-[#F7F8F7] cursor-pointer hover:bg-[#FAFBFA]" onClick={() => inserirVariavel(v.var)}>
                                            <TableCell className="font-mono text-xs text-[#0A4F5C]">{`{{${v.var}}}`}</TableCell>
                                            <TableCell className="text-xs text-[#6B7370]">{v.desc}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>

                        <div className="flex items-center justify-between">
                            <Button variant="outline" onClick={() => router.visit('/admin/templates')} className="border-[#D8DCDA]">Cancelar</Button>
                            <div className="flex gap-2">
                                <Button variant="outline" size="sm" onClick={handleEnviarTeste} disabled={testeSending} className="border-[#D8DCDA]">
                                    {testeSending ? <Spinner /> : <Send className="mr-1 h-3.5 w-3.5" />}Enviar teste
                                </Button>
                                <Button onClick={handleSalvar} disabled={saving} className="bg-[#C9A84C] text-[#2E2410] hover:bg-[#B8993F]">{saving && <Spinner />}Salvar template</Button>
                            </div>
                        </div>
                    </div>

                    {/* Preview */}
                    <div className="space-y-4">
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-sm font-medium text-[#1E2D30]"><Eye className="mr-1 inline h-4 w-4" />Preview</h2>
                                <div className="flex gap-1">
                                    <button onClick={() => setPreviewWidth(600)} className={`rounded px-2 py-0.5 text-[10px] ${previewWidth === 600 ? 'bg-[#0A4F5C] text-white' : 'bg-[#F7F8F7] text-[#6B7370]'}`}>Desktop</button>
                                    <button onClick={() => setPreviewWidth(320)} className={`rounded px-2 py-0.5 text-[10px] ${previewWidth === 320 ? 'bg-[#0A4F5C] text-white' : 'bg-[#F7F8F7] text-[#6B7370]'}`}>Mobile</button>
                                </div>
                            </div>
                            <div className="mx-auto overflow-hidden rounded-md border border-[#EEF0EF] bg-[#EEF0EF]" style={{ width: previewWidth, maxWidth: '100%' }}>
                                {previewHtml ? (
                                    <iframe srcDoc={previewHtml} title="Preview" className="h-[500px] w-full bg-white" sandbox="" />
                                ) : (
                                    <div className="flex h-[500px] items-center justify-center text-sm text-[#8A918E]">Carregando preview...</div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
