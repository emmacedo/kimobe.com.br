import { Head, router, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { InputCnpj } from '@/components/input-cnpj';
import { InputCpf } from '@/components/input-cpf';
import InputError from '@/components/input-error';
import { InputTelefone } from '@/components/input-telefone';
import { PlanoCard } from '@/components/public/plano-card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type PlanoData = { id: number; nome: string; descricao: string | null; limite_imoveis: number; valor_mensal: string };
type Props = { planos: PlanoData[]; plano_selecionado: number | null };

function getCsrfToken() { return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''; }

const stepLabels = ['Seu plano', 'Seus dados', 'Sua empresa'];

export default function RegistroPage({ planos, plano_selecionado }: Props) {
    const { errors } = usePage().props as any;
    const [step, setStep] = useState(plano_selecionado ? 1 : 0);
    const [planoId, setPlanoId] = useState<number | null>(plano_selecionado);
    const [nome, setNome] = useState(''); const [email, setEmail] = useState('');
    const [telefone, setTelefone] = useState(''); const [cpf, setCpf] = useState('');
    const [senha, setSenha] = useState(''); const [senhaConfirm, setSenhaConfirm] = useState('');
    const [emailDisponivel, setEmailDisponivel] = useState<boolean | null>(null);
    const [tipoTenant, setTipoTenant] = useState('imobiliaria');
    const [nomeTenant, setNomeTenant] = useState(''); const [cnpj, setCnpj] = useState('');
    const [termos, setTermos] = useState(false);
    const [processing, setProcessing] = useState(false);

    const planoAtual = planos.find((p) => p.id === planoId);

    async function verificarEmail() {
        if (!email) return;
        try {
            const resp = await fetch('/registro/verificar-email', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' }, body: JSON.stringify({ email }) });
            const data = await resp.json();
            setEmailDisponivel(data.disponivel);
        } catch { /* ignore */ }
    }

    function handleSubmit() {
        setProcessing(true);
        router.post('/registro', {
            plano_id: planoId, nome, email, telefone, cpf, senha, senha_confirmation: senhaConfirm,
            tipo_tenant: tipoTenant, nome_tenant: nomeTenant, cnpj: tipoTenant === 'imobiliaria' ? cnpj : undefined,
            termos,
        }, {
            onFinish: () => setProcessing(false),
            onError: () => { setProcessing(false); toast.error('Verifique os campos e tente novamente.'); },
        });
    }

    // Força da senha
    const forcaSenha = senha.length === 0 ? 0 : senha.length < 8 ? 1 : /(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(senha) ? 3 : 2;
    const forcaLabels = ['', 'Fraca', 'Média', 'Forte'];
    const forcaCores = ['', '#A83232', '#C9A84C', '#1B6B3A'];

    return (
        <>
            <Head title="Criar conta — Kimobe"><meta name="description" content="Crie sua conta no Kimobe e comece a gerenciar seus aluguéis." /></Head>

            <section className="bg-[#0A4F5C] px-4 pt-28 pb-12 text-center">
                <h1 className="text-3xl font-bold text-white">Crie sua conta</h1>
                <p className="mt-3 text-lg text-[#B3DDE5]">Comece a gerenciar seus aluguéis em minutos</p>

                {/* Stepper */}
                <div className="mx-auto mt-8 flex max-w-md items-center justify-center gap-4">
                    {stepLabels.map((label, i) => (
                        <div key={i} className="flex items-center gap-2">
                            <div className={`flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium ${i < step ? 'bg-[#1B6B3A] text-white' : i === step ? 'bg-[#C9A84C] text-[#2E2410]' : 'bg-white/20 text-white/60'}`}>
                                {i < step ? <Check className="h-4 w-4" /> : i + 1}
                            </div>
                            <span className={`hidden text-xs sm:inline ${i === step ? 'text-white font-medium' : 'text-white/60'}`}>{label}</span>
                            {i < 2 && <div className="h-px w-8 bg-white/20" />}
                        </div>
                    ))}
                </div>
            </section>

            <section className="px-4 py-10 md:py-14">
                <div className="mx-auto max-w-4xl">
                    {/* Step 0: Plano */}
                    {step === 0 && (
                        <div>
                            <h2 className="mb-6 text-center text-xl font-medium text-[#1E2D30]">Escolha seu plano</h2>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {planos.map((p, i) => (
                                    <PlanoCard
                                        key={p.id}
                                        plano={p}
                                        destaque={i === 1}
                                        selecionavel
                                        selecionado={planoId === p.id}
                                        onSelect={() => setPlanoId(p.id)}
                                    />
                                ))}
                            </div>
                            <div className="mt-8 text-center">
                                <Button
                                    disabled={!planoId}
                                    onClick={() => setStep(1)}
                                    className={planoId
                                        ? 'bg-[#0A4F5C] px-8 text-white hover:bg-[#073B45]'
                                        : 'bg-[#D8DCDA] px-8 text-[#8A918E] cursor-not-allowed'}
                                >
                                    Próximo
                                </Button>
                            </div>
                        </div>
                    )}

                    {/* Step 1: Dados pessoais */}
                    {step === 1 && (
                        <div className="mx-auto max-w-lg rounded-xl border border-[#D8DCDA] bg-white p-6">
                            <h2 className="mb-6 text-lg font-medium text-[#1E2D30]">Seus dados</h2>
                            <div className="space-y-4">
                                <div><Label>Nome completo</Label><Input value={nome} onChange={(e) => setNome(e.target.value)} className="bg-white border-[#D8DCDA]" /><InputError message={errors?.nome} /></div>
                                <div><Label>Email</Label><Input type="email" value={email} onChange={(e) => { setEmail(e.target.value); setEmailDisponivel(null); }} onBlur={verificarEmail} className="bg-white border-[#D8DCDA]" />
                                    {emailDisponivel === false && <p className="mt-1 text-xs text-[#A83232]">Este email já está cadastrado. <a href="/login" className="underline">Faça login.</a></p>}
                                    <InputError message={errors?.email} /></div>
                                <div><Label>Telefone</Label><InputTelefone value={telefone} onChange={setTelefone} /></div>
                                <div><Label>CPF</Label><InputCpf value={cpf} onChange={setCpf} /><InputError message={errors?.cpf} /></div>
                                <div><Label>Senha</Label><Input type="password" value={senha} onChange={(e) => setSenha(e.target.value)} className="bg-white border-[#D8DCDA]" />
                                    {senha.length > 0 && (
                                        <div className="mt-1.5 flex items-center gap-2">
                                            <div className="h-1.5 flex-1 rounded-full bg-[#EEF0EF]"><div className="h-full rounded-full transition-all" style={{ width: `${(forcaSenha / 3) * 100}%`, backgroundColor: forcaCores[forcaSenha] }} /></div>
                                            <span className="text-[10px]" style={{ color: forcaCores[forcaSenha] }}>{forcaLabels[forcaSenha]}</span>
                                        </div>
                                    )}
                                    <InputError message={errors?.senha} /></div>
                                <div><Label>Confirmar senha</Label><Input type="password" value={senhaConfirm} onChange={(e) => setSenhaConfirm(e.target.value)} className="bg-white border-[#D8DCDA]" /></div>
                            </div>
                            <div className="mt-6 flex justify-between">
                                <Button variant="outline" onClick={() => setStep(0)} className="border-[#D8DCDA]">Voltar</Button>
                                <Button onClick={() => setStep(2)} disabled={!nome || !email || !cpf || !senha || senha !== senhaConfirm || emailDisponivel === false} className="bg-[#C9A84C] text-[#2E2410] hover:bg-[#B8993F]">Próximo</Button>
                            </div>
                        </div>
                    )}

                    {/* Step 2: Empresa */}
                    {step === 2 && (
                        <div className="grid gap-6 lg:grid-cols-5">
                            <div className="rounded-xl border border-[#D8DCDA] bg-white p-6 lg:col-span-3">
                                <h2 className="mb-6 text-lg font-medium text-[#1E2D30]">Sua empresa</h2>
                                <div className="space-y-4">
                                    <div>
                                        <Label>Tipo</Label>
                                        <div className="mt-2 grid grid-cols-2 gap-3">
                                            {[{ value: 'imobiliaria', label: 'Imobiliária' }, { value: 'proprietario_direto', label: 'Proprietário direto' }].map((t) => (
                                                <button key={t.value} type="button" onClick={() => setTipoTenant(t.value)}
                                                    className={`rounded-lg border-2 p-3 text-sm font-medium transition-all ${tipoTenant === t.value ? 'border-[#0A4F5C] bg-[#E8F4F6] text-[#0A4F5C]' : 'border-[#D8DCDA] text-[#6B7370]'}`}>
                                                    {t.label}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                    <div><Label>{tipoTenant === 'imobiliaria' ? 'Razão social' : 'Nome de identificação'}</Label><Input value={nomeTenant} onChange={(e) => setNomeTenant(e.target.value)} className="bg-white border-[#D8DCDA]" /><InputError message={errors?.nome_tenant} /></div>
                                    {tipoTenant === 'imobiliaria' ? (
                                        <div><Label>CNPJ</Label><InputCnpj value={cnpj} onChange={setCnpj} /><InputError message={errors?.cnpj} /></div>
                                    ) : (
                                        <div><Label>Documento (CPF)</Label><InputCpf value={cpf} onChange={() => {}} disabled /></div>
                                    )}
                                    <div className="flex items-start gap-2">
                                        <Checkbox checked={termos} onCheckedChange={(c) => setTermos(!!c)} className="mt-1" />
                                        <span className="text-xs text-[#6B7370]">Li e aceito os <a href="/termos-de-uso" target="_blank" rel="noopener noreferrer" className="text-[#0A4F5C] underline">Termos de Uso</a> e a <a href="/politica-de-privacidade" target="_blank" rel="noopener noreferrer" className="text-[#0A4F5C] underline">Política de Privacidade</a></span>
                                    </div>
                                    <InputError message={errors?.termos} />
                                </div>
                                <div className="mt-6 flex justify-between">
                                    <Button variant="outline" onClick={() => setStep(1)} className="border-[#D8DCDA]">Voltar</Button>
                                    <Button onClick={handleSubmit} disabled={processing || !nomeTenant || !termos || (tipoTenant === 'imobiliaria' && !cnpj)} className="bg-[#C9A84C] px-6 text-[#2E2410] hover:bg-[#B8993F]">
                                        {processing && <Spinner />}Criar minha conta
                                    </Button>
                                </div>
                            </div>

                            {/* Resumo lateral */}
                            {planoAtual && (
                                <div className="rounded-xl border border-[#D8DCDA] bg-white p-6 lg:col-span-2">
                                    <p className="mb-4 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Resumo do pedido</p>
                                    <p className="text-lg font-medium text-[#1E2D30]">{planoAtual.nome}</p>
                                    <div className="mt-1 flex items-baseline gap-1">
                                        <span className="text-sm font-normal text-[#6B7370]">R$</span>
                                        <span className="text-2xl font-semibold tracking-tight text-[#0A4F5C]">{parseFloat(planoAtual.valor_mensal).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                                        <span className="text-sm font-normal text-[#8A918E]">/mês</span>
                                    </div>
                                    <p className="mt-2 text-sm text-[#6B7370]">{planoAtual.limite_imoveis === 0 ? 'Imóveis ilimitados' : `Até ${planoAtual.limite_imoveis} imóveis`}</p>
                                    <div className="mt-4 border-t border-[#EEF0EF] pt-4 text-xs text-[#8A918E]">
                                        <p>Sua primeira fatura será gerada automaticamente.</p>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </section>
        </>
    );
}
