import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';

type Config = { dias_aviso_antes_vencimento: number; aviso_no_dia_vencimento: boolean; dias_graca_apos_vencimento: number; dias_aviso_bloqueio: number; aviso_ao_bloquear: boolean; dia_vencimento_fatura: number };
type Props = { config: Config };

export default function ConfiguracoesIndex({ config }: Props) {
    const { flash, errors } = usePage().props as any;
    const [form, setForm] = useState<Config>(config);
    const [saving, setSaving] = useState(false);

    useEffect(() => { if (flash?.success) toast.success(flash.success); }, [flash?.success]);

    function handleSalvar() {
        setSaving(true);
        router.put('/admin/configuracoes', form, { onFinish: () => setSaving(false), onError: () => setSaving(false) });
    }

    return (
        <>
            <Head title="Admin — Configurações" />
            <div className="space-y-4">
                <PageHeader titulo="Configurações" />

                <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                    <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Configurações de cobrança</h2>
                    <div className="space-y-4 max-w-lg">
                        <div><Label>Dias de aviso antes do vencimento</Label><Input type="number" min={0} max={30} value={form.dias_aviso_antes_vencimento} onChange={(e) => setForm({ ...form, dias_aviso_antes_vencimento: parseInt(e.target.value) || 0 })} className="bg-white border-[#D8DCDA]" /><p className="mt-1 text-[10px] text-[#8A918E]">Quantos dias antes do vencimento o assinante recebe o email de cobrança</p></div>

                        <div className="flex items-center gap-3"><Checkbox checked={form.aviso_no_dia_vencimento} onCheckedChange={(c) => setForm({ ...form, aviso_no_dia_vencimento: !!c })} /><div><Label>Aviso no dia do vencimento</Label><p className="text-[10px] text-[#8A918E]">Enviar lembrete por email no dia do vencimento</p></div></div>

                        <div><Label>Dias de graça após vencimento</Label><Input type="number" min={0} max={60} value={form.dias_graca_apos_vencimento} onChange={(e) => setForm({ ...form, dias_graca_apos_vencimento: parseInt(e.target.value) || 0 })} className="bg-white border-[#D8DCDA]" /><p className="mt-1 text-[10px] text-[#8A918E]">Quantos dias o assinante continua acessando após o vencimento da fatura</p></div>

                        <div><Label>Dias para aviso de bloqueio</Label><Input type="number" min={0} value={form.dias_aviso_bloqueio} onChange={(e) => setForm({ ...form, dias_aviso_bloqueio: parseInt(e.target.value) || 0 })} className="bg-white border-[#D8DCDA]" /><p className="mt-1 text-[10px] text-[#8A918E]">Deve ser menor que os dias de graça ({form.dias_graca_apos_vencimento})</p>{errors?.dias_aviso_bloqueio && <p className="text-xs text-[#A83232]">{errors.dias_aviso_bloqueio}</p>}</div>

                        <div className="flex items-center gap-3"><Checkbox checked={form.aviso_ao_bloquear} onCheckedChange={(c) => setForm({ ...form, aviso_ao_bloquear: !!c })} /><div><Label>Aviso ao bloquear</Label><p className="text-[10px] text-[#8A918E]">Enviar email informando quando o acesso for bloqueado</p></div></div>

                        <div><Label>Dia de vencimento das faturas</Label>
                            <Select value={form.dia_vencimento_fatura.toString()} onValueChange={(v) => setForm({ ...form, dia_vencimento_fatura: parseInt(v) })}>
                                <SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue /></SelectTrigger>
                                <SelectContent>{Array.from({ length: 28 }, (_, i) => i + 1).map((d) => <SelectItem key={d} value={d.toString()}>Dia {d}</SelectItem>)}</SelectContent>
                            </Select>
                            <p className="mt-1 text-[10px] text-[#8A918E]">Dia fixo do mês para todas as faturas</p>
                        </div>

                        <Button onClick={handleSalvar} disabled={saving} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">{saving && <Spinner />}Salvar configurações</Button>
                    </div>
                </div>

                {/* Timeline visual */}
                <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                    <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Fluxo de cobrança</h2>
                    <div className="flex items-center gap-2 text-xs overflow-x-auto pb-2">
                        <div className="shrink-0 rounded-md bg-[#E8F4F6] px-3 py-2 text-[#0A4F5C]">📧 Aviso<br />Dia {form.dia_vencimento_fatura - form.dias_aviso_antes_vencimento}</div>
                        <span className="text-[#D8DCDA]">→</span>
                        <div className="shrink-0 rounded-md bg-[#FFF4E5] px-3 py-2 text-[#8C5A10]">📅 Vencimento<br />Dia {form.dia_vencimento_fatura}</div>
                        <span className="text-[#D8DCDA]">→</span>
                        <div className="shrink-0 rounded-md bg-[#FEE2D5] px-3 py-2 text-[#9A3412]">⚠️ Aviso bloqueio<br />+{form.dias_aviso_bloqueio} dias</div>
                        <span className="text-[#D8DCDA]">→</span>
                        <div className="shrink-0 rounded-md bg-[#FDECEC] px-3 py-2 text-[#A83232]">🔒 Bloqueio<br />+{form.dias_graca_apos_vencimento} dias</div>
                    </div>
                </div>
            </div>
        </>
    );
}
