import { Head, Link, router, usePage } from '@inertiajs/react';
import { Mail, Phone } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import { InputTelefone } from '@/components/input-telefone';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

const assuntos = ['Quero conhecer o Kimobe', 'Dúvida sobre planos', 'Suporte técnico', 'Financeiro / cobranças', 'Parceria comercial', 'Outro'];

export default function ContatoPage() {
    const { flash, errors } = usePage().props as any;
    const [nome, setNome] = useState('');
    const [email, setEmail] = useState('');
    const [telefone, setTelefone] = useState('');
    const [assunto, setAssunto] = useState('');
    const [mensagem, setMensagem] = useState('');
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        if (flash?.success) { toast.success(flash.success); setNome(''); setEmail(''); setTelefone(''); setAssunto(''); setMensagem(''); }
    }, [flash?.success]);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault(); setProcessing(true);
        router.post('/contato', { nome, email, telefone, assunto, mensagem }, { onFinish: () => setProcessing(false) });
    }

    return (
        <>
            <Head title="Contato — Kimobe"><meta name="description" content="Entre em contato com a equipe Kimobe." /></Head>

            <section className="bg-[#0A4F5C] px-4 pt-28 pb-16 text-center">
                <h1 className="text-3xl font-bold text-white sm:text-4xl">Fale conosco</h1>
                <p className="mt-4 text-lg text-[#B3DDE5]">Estamos aqui para ajudar</p>
            </section>

            <section className="px-4 py-16 md:py-20">
                <div className="mx-auto grid max-w-5xl gap-10 lg:grid-cols-5">
                    <div className="lg:col-span-3">
                        <form onSubmit={handleSubmit} className="space-y-4 rounded-xl border border-[#D8DCDA] bg-white p-6">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div><Label>Nome</Label><Input value={nome} onChange={(e) => setNome(e.target.value)} className="bg-white border-[#D8DCDA]" /><InputError message={errors?.nome} /></div>
                                <div><Label>Email</Label><Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} className="bg-white border-[#D8DCDA]" /><InputError message={errors?.email} /></div>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div><Label>Telefone <span className="text-[#8A918E]">(opcional)</span></Label><InputTelefone value={telefone} onChange={setTelefone} /></div>
                                <div><Label>Assunto</Label><Select value={assunto} onValueChange={setAssunto}><SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue placeholder="Selecione" /></SelectTrigger><SelectContent>{assuntos.map((a) => <SelectItem key={a} value={a}>{a}</SelectItem>)}</SelectContent></Select><InputError message={errors?.assunto} /></div>
                            </div>
                            <div><Label>Mensagem</Label><textarea value={mensagem} onChange={(e) => setMensagem(e.target.value)} rows={5} className="w-full rounded-md border border-[#D8DCDA] bg-white px-3 py-2 text-sm focus:border-[#0A4F5C] focus:outline-none focus:ring-1 focus:ring-[#0A4F5C]" /><InputError message={errors?.mensagem} /></div>
                            <Button type="submit" disabled={processing} className="bg-[#C9A84C] text-[#2E2410] hover:bg-[#B8993F]">{processing && <Spinner />}Enviar mensagem</Button>
                        </form>
                    </div>
                    <div className="space-y-4 lg:col-span-2">
                        <div className="rounded-xl bg-[#F7F8F7] p-6">
                            <h3 className="mb-4 text-sm font-medium text-[#1E2D30]">Informações de contato</h3>
                            <div className="space-y-3 text-sm text-[#6B7370]">
                                <div className="flex items-center gap-2"><Mail className="h-4 w-4 text-[#0A4F5C]" />contato@kimobe.com.br</div>
                                <div className="flex items-center gap-2"><Phone className="h-4 w-4 text-[#0A4F5C]" />(21) 99999-9999</div>
                                <p className="text-xs text-[#8A918E]">Seg a Sex, 9h às 18h</p>
                            </div>
                        </div>
                        <div className="rounded-xl border border-[#D8DCDA] bg-white p-6">
                            <h3 className="mb-2 text-sm font-medium text-[#1E2D30]">Já é cliente?</h3>
                            <p className="mb-4 text-xs text-[#6B7370]">Acesse o sistema para suporte mais rápido.</p>
                            <Link href="/login" className="inline-block rounded-lg bg-[#0A4F5C] px-4 py-2 text-sm font-medium text-white hover:bg-[#073B45]">Acessar sistema</Link>
                        </div>
                    </div>
                </div>
            </section>
        </>
    );
}
