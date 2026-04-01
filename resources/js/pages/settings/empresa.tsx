import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { InputCep } from '@/components/input-cep';
import InputError from '@/components/input-error';
import { InputTelefone } from '@/components/input-telefone';
import { SelectUf } from '@/components/select-uf';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type TenantData = {
    nome: string;
    tipo: string;
    documento: string;
    cep: string | null;
    logradouro: string | null;
    numero: string | null;
    complemento: string | null;
    bairro: string | null;
    cidade: string | null;
    uf: string | null;
    email_contato: string | null;
    telefone_comercial: string | null;
    whatsapp: string | null;
    site: string | null;
};

type Props = { tenant: TenantData };

export default function EmpresaPage({ tenant }: Props) {
    const { errors, flash } = usePage().props as any;

    // Dados básicos
    const [nome, setNome] = useState(tenant.nome);
    const [savingDados, setSavingDados] = useState(false);

    // Endereço
    const [cep, setCep] = useState(tenant.cep ?? '');
    const [logradouro, setLogradouro] = useState(tenant.logradouro ?? '');
    const [numero, setNumero] = useState(tenant.numero ?? '');
    const [complemento, setComplemento] = useState(tenant.complemento ?? '');
    const [bairro, setBairro] = useState(tenant.bairro ?? '');
    const [cidade, setCidade] = useState(tenant.cidade ?? '');
    const [uf, setUf] = useState(tenant.uf ?? '');
    const [savingEndereco, setSavingEndereco] = useState(false);

    // Contato
    const [emailContato, setEmailContato] = useState(tenant.email_contato ?? '');
    const [telefoneComercial, setTelefoneComercial] = useState(tenant.telefone_comercial ?? '');
    const [whatsapp, setWhatsapp] = useState(tenant.whatsapp ?? '');
    const [site, setSite] = useState(tenant.site ?? '');
    const [savingContato, setSavingContato] = useState(false);

    useEffect(() => { if (flash?.success) toast.success(flash.success); }, [flash?.success]);

    const tipoLabel = tenant.tipo === 'imobiliaria' ? 'Imobiliária' : 'Proprietário direto';

    function handleAddressFound(endereco: { logradouro: string; bairro: string; localidade: string; uf: string }) {
        setLogradouro(endereco.logradouro || logradouro);
        setBairro(endereco.bairro || bairro);
        setCidade(endereco.localidade || cidade);
        setUf(endereco.uf || uf);
    }

    return (
        <>
            <Head title="Minha empresa" />

            {/* Card dados básicos */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Dados da empresa</h2>
                <div className="space-y-4">
                    <div>
                        <Label>Nome da empresa</Label>
                        <Input value={nome} onChange={(e) => setNome(e.target.value)} className="bg-white border-[#D8DCDA]" />
                        <InputError message={errors?.nome} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <Label>Tipo</Label>
                            <div className="mt-1"><Badge variant="secondary" className="bg-[#E8F4F6] text-[#0A4F5C]">{tipoLabel}</Badge></div>
                            <p className="mt-1 text-[10px] text-[#8A918E]">O tipo não pode ser alterado após o cadastro.</p>
                        </div>
                        <div>
                            <Label>Documento ({tenant.tipo === 'imobiliaria' ? 'CNPJ' : 'CPF'})</Label>
                            <Input value={tenant.documento} disabled className="bg-[#F7F8F7] font-mono text-sm" />
                            <p className="mt-1 text-[10px] text-[#8A918E]">O documento não pode ser alterado.</p>
                        </div>
                    </div>
                    <div className="flex justify-end">
                        <Button
                            disabled={savingDados}
                            className="bg-[#0A4F5C] text-white hover:bg-[#073B45]"
                            onClick={() => {
                                setSavingDados(true);
                                router.put('/settings/empresa', { nome }, { onFinish: () => setSavingDados(false) });
                            }}
                        >
                            {savingDados && <Spinner />}Salvar alterações
                        </Button>
                    </div>
                </div>
            </div>

            {/* Card endereço */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Endereço da empresa</h2>
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <Label>CEP</Label>
                            <InputCep value={cep} onChange={setCep} onAddressFound={handleAddressFound} />
                        </div>
                        <div className="sm:col-span-2">
                            <Label>Logradouro</Label>
                            <Input value={logradouro} onChange={(e) => setLogradouro(e.target.value)} className="bg-white border-[#D8DCDA]" />
                        </div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-4">
                        <div>
                            <Label>Número</Label>
                            <Input value={numero} onChange={(e) => setNumero(e.target.value)} className="bg-white border-[#D8DCDA]" />
                        </div>
                        <div className="sm:col-span-3">
                            <Label>Complemento</Label>
                            <Input value={complemento} onChange={(e) => setComplemento(e.target.value)} placeholder="Sala, andar... (opcional)" className="bg-white border-[#D8DCDA]" />
                        </div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div><Label>Bairro</Label><Input value={bairro} onChange={(e) => setBairro(e.target.value)} className="bg-white border-[#D8DCDA]" /></div>
                        <div><Label>Cidade</Label><Input value={cidade} onChange={(e) => setCidade(e.target.value)} className="bg-white border-[#D8DCDA]" /></div>
                        <div><Label>UF</Label><SelectUf value={uf} onValueChange={setUf} className="bg-white border-[#D8DCDA]" /></div>
                    </div>
                    <div className="flex justify-end">
                        <Button
                            disabled={savingEndereco}
                            className="bg-[#0A4F5C] text-white hover:bg-[#073B45]"
                            onClick={() => {
                                setSavingEndereco(true);
                                router.put('/settings/empresa/endereco', { cep, logradouro, numero, complemento, bairro, cidade, uf }, { onFinish: () => setSavingEndereco(false) });
                            }}
                        >
                            {savingEndereco && <Spinner />}Salvar endereço
                        </Button>
                    </div>
                </div>
            </div>

            {/* Card contato */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Contato da empresa</h2>
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div><Label>Email de contato</Label><Input type="email" value={emailContato} onChange={(e) => setEmailContato(e.target.value)} placeholder="contato@empresa.com" className="bg-white border-[#D8DCDA]" /></div>
                        <div><Label>Telefone comercial</Label><InputTelefone value={telefoneComercial} onChange={setTelefoneComercial} /></div>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div><Label>WhatsApp</Label><InputTelefone value={whatsapp} onChange={setWhatsapp} placeholder="(00) 00000-0000" /></div>
                        <div><Label>Site</Label><Input value={site} onChange={(e) => setSite(e.target.value)} placeholder="https://www.empresa.com" className="bg-white border-[#D8DCDA]" /></div>
                    </div>
                    <div className="flex justify-end">
                        <Button
                            disabled={savingContato}
                            className="bg-[#0A4F5C] text-white hover:bg-[#073B45]"
                            onClick={() => {
                                setSavingContato(true);
                                router.put('/settings/empresa/contato', { email_contato: emailContato, telefone_comercial: telefoneComercial, whatsapp, site }, { onFinish: () => setSavingContato(false) });
                            }}
                        >
                            {savingContato && <Spinner />}Salvar contato
                        </Button>
                    </div>
                </div>
            </div>

            {/* Card logo — placeholder */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-2 text-sm font-medium text-[#1E2D30]">Logo da empresa</h2>
                <div className="flex items-center gap-3 rounded-lg bg-[#F7F8F7] p-4">
                    <div className="flex h-12 w-12 items-center justify-center rounded-lg border border-dashed border-[#D8DCDA] bg-white text-[#8A918E]">
                        <span className="text-lg">🏢</span>
                    </div>
                    <p className="text-xs text-[#8A918E]">Em breve você poderá adicionar a logo da sua empresa.</p>
                </div>
            </div>
        </>
    );
}
