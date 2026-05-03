import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
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

type ContratoDisponivel = {
    id: number;
    valor_aluguel: string;
    dia_vencimento: number;
    imovel: { logradouro: string; numero: string; complemento: string | null };
    inquilino: { user: { name: string } };
};

type Props = {
    contratos: ContratoDisponivel[];
};

export default function CriarFatura({ contratos }: Props) {
    const { errors } = usePage().props as any;
    const [processing, setProcessing] = useState(false);

    const [contratoId, setContratoId] = useState<number | null>(null);
    const [referencia, setReferencia] = useState('');
    const [dataVencimento, setDataVencimento] = useState('');
    const [observacoes, setObservacoes] = useState('');

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setProcessing(true);
        router.post('/financeiro/faturas', {
            contrato_id: contratoId,
            referencia,
            data_vencimento: dataVencimento,
            observacoes: observacoes || null,
        }, {
            onSuccess: () => setProcessing(false),
            onError: () => { setProcessing(false); toast.error('Verifique os campos.'); },
        });
    }

    return (
        <>
            <Head title="Nova fatura" />
            <div className="space-y-4">
                <PageHeader titulo="Nova fatura">
                    <Button variant="outline" size="sm" onClick={() => router.visit('/financeiro/faturas')} className="border-[#D8DCDA]">
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
                                <Select value={contratoId?.toString() ?? ''} onValueChange={(v) => setContratoId(parseInt(v))}>
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

                    <div className="rounded-[10px] border border-[#FFE5B0] bg-[#FFF4E5] p-4 text-xs text-[#8C5A10]">
                        Os itens da fatura (aluguel, condomínio, IPTU, etc.) serão adicionados após a criação.
                        A gestão completa de itens será habilitada em breve.
                    </div>

                    <div className="flex items-center justify-end gap-3">
                        <Button type="button" variant="outline" onClick={() => router.visit('/financeiro/faturas')} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button type="submit" disabled={processing} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                            {processing && <Spinner />}Criar fatura
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
