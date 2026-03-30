import { Head, router, usePage } from '@inertiajs/react';
import { CreditCard, Landmark, Pencil, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { usePermissions } from '@/hooks/use-permissions';

type ContaBancaria = {
    id: number;
    vinculo_id: number;
    apelido: string;
    banco_codigo: string;
    banco_nome: string;
    agencia: string;
    conta: string;
    tipo_conta: string;
    pix_tipo: string | null;
    pix_chave: string | null;
    imoveis_count: number;
    vinculo: { user: { name: string } };
};

type Props = {
    contas: ContaBancaria[];
};

const BANCOS = [
    { codigo: '001', nome: 'Banco do Brasil' },
    { codigo: '033', nome: 'Santander' },
    { codigo: '104', nome: 'Caixa Econômica Federal' },
    { codigo: '237', nome: 'Bradesco' },
    { codigo: '341', nome: 'Itaú Unibanco' },
    { codigo: '260', nome: 'Nubank' },
    { codigo: '077', nome: 'Inter' },
    { codigo: '212', nome: 'Banco Original' },
    { codigo: '756', nome: 'Sicoob' },
    { codigo: '748', nome: 'Sicredi' },
];

type FormData = {
    apelido: string;
    banco_codigo: string;
    banco_nome: string;
    agencia: string;
    conta: string;
    tipo_conta: string;
    pix_tipo: string;
    pix_chave: string;
};

const formInicial: FormData = {
    apelido: '', banco_codigo: '', banco_nome: '',
    agencia: '', conta: '', tipo_conta: 'corrente',
    pix_tipo: '', pix_chave: '',
};

export default function DadosBancariosIndex({ contas }: Props) {
    const { flash } = usePage().props as any;
    const { isAdmin } = usePermissions();
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editando, setEditando] = useState<ContaBancaria | null>(null);
    const [form, setForm] = useState<FormData>(formInicial);
    const [saving, setSaving] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<ContaBancaria | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    function abrirCriar() {
        setEditando(null);
        setForm(formInicial);
        setDialogOpen(true);
    }

    function abrirEditar(conta: ContaBancaria) {
        setEditando(conta);
        setForm({
            apelido: conta.apelido,
            banco_codigo: conta.banco_codigo,
            banco_nome: conta.banco_nome,
            agencia: conta.agencia,
            conta: conta.conta,
            tipo_conta: conta.tipo_conta,
            pix_tipo: conta.pix_tipo ?? '',
            pix_chave: conta.pix_chave ?? '',
        });
        setDialogOpen(true);
    }

    function handleBancoChange(codigo: string) {
        const banco = BANCOS.find((b) => b.codigo === codigo);
        setForm((p) => ({ ...p, banco_codigo: codigo, banco_nome: banco?.nome ?? '' }));
    }

    function handleSalvar() {
        setSaving(true);
        const dados = {
            ...form,
            pix_tipo: form.pix_tipo || null,
            pix_chave: form.pix_chave || null,
        };

        if (editando) {
            router.put(`/dados-bancarios/${editando.id}`, dados, {
                onFinish: () => { setSaving(false); setDialogOpen(false); },
                onError: () => { setSaving(false); toast.error('Erro ao salvar.'); },
            });
        } else {
            // Para criação via Inertia, precisamos do vinculo_id
            // Proprietário usa seu próprio vínculo
            // Para simplicidade, o store aceita vinculo_id no request
            router.post('/dados-bancarios', {
                ...dados,
                vinculo_id: (contas[0]?.vinculo_id ?? 0), // será resolvido pelo backend
            }, {
                onFinish: () => { setSaving(false); setDialogOpen(false); },
                onError: () => { setSaving(false); toast.error('Erro ao salvar.'); },
            });
        }
    }

    function handleExcluir() {
        if (!deleteTarget) return;
        setDeleteLoading(true);
        router.delete(`/dados-bancarios/${deleteTarget.id}`, {
            onFinish: () => { setDeleteLoading(false); setDeleteTarget(null); },
        });
    }

    return (
        <>
            <Head title="Dados bancários" />
            <div className="space-y-4">
                <PageHeader titulo="Dados bancários" subtitulo="Contas para recebimento de repasses">
                    <Button className="bg-[#0A4F5C] text-white hover:bg-[#073B45]" size="sm" onClick={abrirCriar}>
                        <Plus className="h-4 w-4" />
                        Nova conta
                    </Button>
                </PageHeader>

                {contas.length === 0 ? (
                    <EmptyState
                        icone={CreditCard}
                        titulo="Nenhuma conta bancária cadastrada"
                        descricao="Adicione uma conta para receber repasses dos seus imóveis."
                        acao={
                            <Button className="bg-[#0A4F5C] text-white hover:bg-[#073B45]" size="sm" onClick={abrirCriar}>
                                <Plus className="mr-1 h-4 w-4" />
                                Cadastrar conta
                            </Button>
                        }
                    />
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {contas.map((conta) => (
                            <div key={conta.id} className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                                <div className="mb-3 flex items-center justify-between">
                                    <h3 className="text-sm font-medium text-[#1E2D30]">{conta.apelido}</h3>
                                    <div className="flex gap-1">
                                        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => abrirEditar(conta)} aria-label="Editar">
                                            <Pencil className="h-3.5 w-3.5 text-[#6B7370]" />
                                        </Button>
                                        <Button variant="ghost" size="icon" className="h-7 w-7" onClick={() => setDeleteTarget(conta)} aria-label="Remover">
                                            <Trash2 className="h-3.5 w-3.5 text-[#A83232]" />
                                        </Button>
                                    </div>
                                </div>

                                <div className="space-y-1.5 text-sm">
                                    <div className="flex items-center gap-2 text-[#3A4240]">
                                        <Landmark className="h-3.5 w-3.5 text-[#8A918E]" />
                                        {conta.banco_codigo} — {conta.banco_nome}
                                    </div>
                                    <p className="text-[#6B7370]">Ag {conta.agencia} · CC {conta.conta}</p>
                                    <Badge variant="secondary" className="text-[10px]">
                                        {conta.tipo_conta === 'corrente' ? 'Corrente' : 'Poupança'}
                                    </Badge>

                                    {conta.pix_chave ? (
                                        <p className="text-xs text-[#0A4F5C]">PIX ({conta.pix_tipo}): {conta.pix_chave}</p>
                                    ) : (
                                        <p className="text-xs text-[#8A918E]">Sem PIX cadastrado</p>
                                    )}

                                    {isAdmin && (
                                        <p className="text-xs text-[#8A918E]">Proprietário: {conta.vinculo.user.name}</p>
                                    )}

                                    {conta.imoveis_count > 0 && (
                                        <p className="text-xs text-[#0A4F5C]">Usada em {conta.imoveis_count} imóvel(is)</p>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {/* Dialog criar/editar */}
            <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>{editando ? 'Editar conta' : 'Nova conta bancária'}</DialogTitle>
                    </DialogHeader>
                    <div className="space-y-3">
                        <div><Label>Nome da conta</Label><Input value={form.apelido} onChange={(e) => setForm((p) => ({ ...p, apelido: e.target.value }))} placeholder="Ex: Conta Itaú principal" className="bg-white border-[#D8DCDA]" /></div>
                        <div className="grid grid-cols-2 gap-3">
                            <div><Label>Banco</Label>
                                <Select value={form.banco_codigo} onValueChange={handleBancoChange}>
                                    <SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue placeholder="Selecione" /></SelectTrigger>
                                    <SelectContent>{BANCOS.map((b) => <SelectItem key={b.codigo} value={b.codigo}>{b.codigo} — {b.nome}</SelectItem>)}</SelectContent>
                                </Select>
                            </div>
                            <div><Label>Tipo</Label>
                                <Select value={form.tipo_conta} onValueChange={(v) => setForm((p) => ({ ...p, tipo_conta: v }))}>
                                    <SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue /></SelectTrigger>
                                    <SelectContent><SelectItem value="corrente">Corrente</SelectItem><SelectItem value="poupanca">Poupança</SelectItem></SelectContent>
                                </Select>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div><Label>Agência</Label><Input value={form.agencia} onChange={(e) => setForm((p) => ({ ...p, agencia: e.target.value }))} className="bg-white border-[#D8DCDA]" /></div>
                            <div><Label>Conta</Label><Input value={form.conta} onChange={(e) => setForm((p) => ({ ...p, conta: e.target.value }))} className="bg-white border-[#D8DCDA]" /></div>
                        </div>
                        <div className="grid grid-cols-2 gap-3">
                            <div><Label>Tipo PIX <span className="text-[#8A918E]">(opcional)</span></Label>
                                <Select value={form.pix_tipo} onValueChange={(v) => setForm((p) => ({ ...p, pix_tipo: v }))}>
                                    <SelectTrigger className="bg-white border-[#D8DCDA]"><SelectValue placeholder="Nenhum" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="cpf">CPF</SelectItem><SelectItem value="cnpj">CNPJ</SelectItem>
                                        <SelectItem value="email">Email</SelectItem><SelectItem value="telefone">Telefone</SelectItem>
                                        <SelectItem value="aleatoria">Aleatória</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            {form.pix_tipo && (
                                <div><Label>Chave PIX</Label><Input value={form.pix_chave} onChange={(e) => setForm((p) => ({ ...p, pix_chave: e.target.value }))} className="bg-white border-[#D8DCDA]" /></div>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDialogOpen(false)} className="border-[#D8DCDA]">Cancelar</Button>
                        <Button onClick={handleSalvar} disabled={saving || !form.apelido || !form.banco_codigo} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                            {saving && <Spinner />}{editando ? 'Salvar' : 'Cadastrar'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Dialog excluir */}
            <ConfirmDialog
                open={!!deleteTarget}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                titulo="Remover conta bancária"
                descricao={
                    deleteTarget ? (
                        <div className="space-y-2">
                            <p>Remover a conta "{deleteTarget.apelido}"?</p>
                            {deleteTarget.imoveis_count > 0 && (
                                <p className="rounded-md bg-[#FFF4E5] p-3 text-sm text-[#8C5A10]">
                                    Esta conta está sendo usada para repasse em {deleteTarget.imoveis_count} imóvel(is). Ao remover, esses imóveis ficarão sem conta de repasse definida.
                                </p>
                            )}
                        </div>
                    ) : ''
                }
                textoConfirmar="Remover"
                variante="destructive"
                loading={deleteLoading}
                onConfirm={handleExcluir}
            />
        </>
    );
}
