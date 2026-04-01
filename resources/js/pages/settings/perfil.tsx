import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { InputCpf } from '@/components/input-cpf';
import InputError from '@/components/input-error';
import { InputTelefone } from '@/components/input-telefone';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    mustVerifyEmail?: boolean;
    status?: string;
};

export default function PerfilPage({ mustVerifyEmail, status }: Props) {
    const { auth, errors, flash } = usePage().props as any;
    const user = auth.user;

    const [name, setName] = useState(user.name ?? '');
    const [email, setEmail] = useState(user.email ?? '');
    const [telefone, setTelefone] = useState(user.telefone ?? '');
    const [saving, setSaving] = useState(false);

    useEffect(() => { if (flash?.success) toast.success(flash.success); }, [flash?.success]);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setSaving(true);
        router.put('/settings/perfil', { name, email, telefone }, {
            onFinish: () => setSaving(false),
            onError: () => setSaving(false),
        });
    }

    return (
        <>
            <Head title="Meu perfil" />

            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Dados pessoais</h2>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <Label>Nome completo</Label>
                        <Input value={name} onChange={(e) => setName(e.target.value)} className="bg-white border-[#D8DCDA]" />
                        <InputError message={errors?.name} />
                    </div>
                    <div>
                        <Label>Email</Label>
                        <Input type="email" value={email} onChange={(e) => setEmail(e.target.value)} className="bg-white border-[#D8DCDA]" />
                        <InputError message={errors?.email} />
                        {mustVerifyEmail && !user.email_verified_at && (
                            <p className="mt-1 text-xs text-[#C9A84C]">Seu email ainda não foi verificado. Verifique sua caixa de entrada.</p>
                        )}
                        {email !== user.email && (
                            <p className="mt-1 text-xs text-[#8A918E]">Ao alterar o email, um link de confirmação será enviado para o novo endereço.</p>
                        )}
                    </div>
                    <div>
                        <Label>Telefone</Label>
                        <InputTelefone value={telefone} onChange={setTelefone} />
                    </div>
                    <div>
                        <Label>CPF</Label>
                        <InputCpf value={user.cpf ?? ''} onChange={() => {}} disabled />
                        <p className="mt-1 text-[10px] text-[#8A918E]">O CPF não pode ser alterado após o cadastro.</p>
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={saving} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                            {saving && <Spinner />}Salvar alterações
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
