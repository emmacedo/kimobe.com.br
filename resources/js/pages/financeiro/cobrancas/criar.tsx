import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { InputMoeda } from '@/components/input-moeda';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { formataMoeda } from '@/lib/utils';

type ContratoDisponivel = {
    id: number;
    valor_aluguel: string;
    dia_vencimento: number;
    imovel: { logradouro: string; numero: string; complemento: string | null };
    inquilino: { user: { name: string } };
    responsabilidades: Array<{ descricao: string; responsavel: string; valor: string | null; periodicidade: string }>;
};

type Props = {
    contratos: ContratoDisponivel[];
};

export default function CriarCobranca({ contratos }: Props) {
    const { errors } = usePage().props as any;
    const [processing, setProcessing] = useState(false);
    const [confirmSair, setConfirmSair] = useState(false);

    const [contratoId, setContratoId] = useState<number | null>(null);
    const [referencia, setReferencia] = useState('');
    const [valorAluguel, setValorAluguel] = useState<number | null>(null);
    const [valorCondominio, setValorCondominio] = useState<number | null>(null);
    const [valorIptu, setValorIptu] = useState<number | null>(null);
    const [valorSeguro, setValorSeguro] = useState<number | null>(null);
    const [valorBombeiros, setValorBombeiros] = useState<number | null>(null);
    const [valorExtra, setValorExtra] = useState<number | null>(null);
    const [dataVencimento, setDataVencimento] = useState('');
    const [observacoes, setObservacoes] = useState('');

    const contrato = contratos.find((c) => c.id === contratoId);
    const total = (valorAluguel ?? 0) + (valorCondominio ?? 0) + (valorIptu ?? 0) + (valorSeguro ?? 0) + (valorBombeiros ?? 0) + (valorExtra ?? 0);

    // Ao selecionar contrato, pré-preencher valores
    function handleContratoChange(id: string) {
        const cid = parseInt(id);
        setContratoId(cid);
        const c = contratos.find((ct) => ct.id === cid);
        if (!c) return;

        setValorAluguel(parseFloat(c.valor_aluguel));

        // Mapear responsabilidades do inquilino
        const resps = c.responsabilidades.filter((r) => r.responsavel === 'inquilino');
        resps.forEach((r) => {
            const desc = r.descricao.toLowerCase();
            const val = r.valor ? parseFloat(r.valor) : null;
            if (desc.includes('condomínio') && !desc.includes('extra')) setValorCondominio(val);
            else if (desc.includes('condominio') && !desc.includes('extra')) setValorCondominio(val);
            else if (desc.includes('iptu')) setValorIptu(val);
            else if (desc.includes('seguro') && desc.includes('incêndio')) setValorSeguro(val);
            else if (desc.includes('seguro') && desc.includes('incendio')) setValorSeguro(val);
            else if (desc.includes('bombeiro')) setValorBombeiros(val);
            else if (desc.includes('extra')) setValorExtra(val);
        });
    }

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setProcessing(true);
        router.post('/financeiro/cobrancas', {
            contrato_id: contratoId,
            referencia,
            valor_aluguel: valorAluguel,
            valor_condominio: valorCondominio,
            valor_iptu: valorIptu,
            valor_seguro_incendio: valorSeguro,
            valor_taxa_bombeiros: valorBombeiros,
            valor_taxa_extra_condominio: valorExtra,
            data_vencimento: dataVencimento,
            observacoes: observacoes || null,
        }, {
            onSuccess: () => setProcessing(false),
            onError: () => { setProcessing(false); toast.error('Verifique os campos.'); },
        });
    }

    return (
        <>
            <Head title="Nova cobrança" />
            <div className="space-y-4">
                <PageHeader titulo="Nova cobrança">
                    <Button variant="outline" size="sm" onClick={() => router.visit('/financeiro/cobrancas')} className="border-[#D8DCDA]">
                        <ArrowLeft className="mr-1 h-4 w-4" />Voltar
                    </Button>
                </PageHeader>

                <form onSubmit={handleSubmit} className="space-y-4">
                    {/* Contrato */}
                    <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                        <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Contrato</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <Label>Contrato</Label>
                                <Select value={contratoId?.toString() ?? ''} onValueChange={handleContratoChange}>
                                    <SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue placeholder="Selecione" /></SelectTrigger>
                                    <SelectContent>
                                        {contratos.map((c) => (
                                            <SelectItem key={c.id} value={c.id.toString()}>
                                                {c.imovel.complemento || `${c.imovel.logradouro}, ${c.imovel.numero}`} — {c.inquilino.user.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors?.contrato_id} />
                            </div>
                            <div>
                                <Label>Referência (MM/YYYY)</Label>
                                <Input value={referencia} onChange={(e) => setReferencia(e.target.value)} placeholder="04/2026" maxLength={7} className="bg-white border-[#D8DCDA]" />
                                <InputError message={errors?.referencia} />
                            </div>
                        </div>
                    </div>

                    {/* Valores */}
                    <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                        <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Valores</h2>
                        {contrato && (
                            <p className="mb-3 text-xs text-[#8A918E]">Valores pré-preenchidos com base nas responsabilidades do contrato. Ajuste conforme necessário.</p>
                        )}
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div><Label>Aluguel</Label><InputMoeda value={valorAluguel} onChange={setValorAluguel} /><InputError message={errors?.valor_aluguel} /></div>
                            <div><Label>Condomínio</Label><InputMoeda value={valorCondominio} onChange={setValorCondominio} /></div>
                            <div><Label>IPTU</Label><InputMoeda value={valorIptu} onChange={setValorIptu} /></div>
                            <div><Label>Seguro incêndio</Label><InputMoeda value={valorSeguro} onChange={setValorSeguro} /></div>
                            <div><Label>Taxa dos Bombeiros</Label><InputMoeda value={valorBombeiros} onChange={setValorBombeiros} /></div>
                            <div><Label>Taxa extra condomínio</Label><InputMoeda value={valorExtra} onChange={setValorExtra} /></div>
                        </div>
                        <div className="mt-4 flex justify-between rounded-md bg-[#F7F8F7] p-3 text-sm font-medium">
                            <span className="text-[#3A4240]">Total da cobrança</span>
                            <span className="font-mono text-[#0A4F5C]">{formataMoeda(total)}</span>
                        </div>
                    </div>

                    {/* Vencimento */}
                    <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                        <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Vencimento</h2>
                        <div className="max-w-xs">
                            <Label>Data de vencimento</Label>
                            <Input type="date" value={dataVencimento} onChange={(e) => setDataVencimento(e.target.value)} className="bg-white border-[#D8DCDA]" />
                            <InputError message={errors?.data_vencimento} />
                        </div>
                    </div>

                    {/* Observações */}
                    <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                        <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Observações</h2>
                        <textarea value={observacoes} onChange={(e) => setObservacoes(e.target.value)} rows={3} placeholder="Notas internas..."
                            className="w-full rounded-md border border-[#D8DCDA] bg-white px-3 py-2 text-sm focus:border-[#0A4F5C] focus:outline-none focus:ring-1 focus:ring-[#0A4F5C]" />
                    </div>

                    <div className="flex items-center justify-end gap-3">
                        <Button type="button" variant="outline" onClick={() => router.visit('/financeiro/cobrancas')} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button type="submit" disabled={processing} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                            {processing && <Spinner />}Criar cobrança
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
