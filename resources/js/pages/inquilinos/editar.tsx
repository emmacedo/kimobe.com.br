import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { PessoaForm, type PessoaFormData } from '@/components/pessoa-form';
import { Button } from '@/components/ui/button';
import type { Inquilino } from '@/types/models';

const AVISO_EMAIL_INQUILINO = 'Este inquilino foi cadastrado sem email. Adicione um email real se quiser que ele tenha acesso ao sistema futuramente.';

type Props = {
    inquilino: Inquilino;
    errors?: Record<string, string>;
};

export default function EditarInquilino({ inquilino, errors = {} }: Props) {
    const { flash } = usePage().props as any;
    const [processing, setProcessing] = useState(false);
    const [confirmSair, setConfirmSair] = useState(false);
    const [dirty, setDirty] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    const dadosIniciais: PessoaFormData = {
        name: inquilino.name,
        tipo_pessoa: inquilino.tipo_pessoa,
        documento: inquilino.documento ?? '',
        telefone: inquilino.telefone ?? '',
        email: inquilino.email ?? '',
    };

    function handleSubmit(dados: PessoaFormData) {
        setProcessing(true);
        router.put(`/inquilinos/${inquilino.vinculo_id}`, dados, {
            onSuccess: () => setProcessing(false),
            onError: () => {
                setProcessing(false);
                toast.error('Verifique os campos e tente novamente.');
            },
        });
    }

    function handleVoltar() {
        if (dirty) {
            setConfirmSair(true);
        } else {
            router.visit('/inquilinos');
        }
    }

    return (
        <>
            <Head title={`Editar ${inquilino.name}`} />
            <div className="space-y-4">
                <PageHeader titulo="Editar inquilino" subtitulo={inquilino.name}>
                    <Button variant="outline" size="sm" onClick={handleVoltar} className="border-[#D8DCDA]">
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Voltar
                    </Button>
                </PageHeader>

                <PessoaForm
                    dados={dadosIniciais}
                    titulo="Dados do inquilino"
                    avisoEmailTexto={AVISO_EMAIL_INQUILINO}
                    errors={errors}
                    processing={processing}
                    onSubmit={handleSubmit}
                    onDirtyChange={setDirty}
                    onCancel={handleVoltar}
                    textoBotao="Salvar alterações"
                    avisoEmailPlaceholder={inquilino.email_placeholder}
                />
            </div>

            <ConfirmDialog
                open={confirmSair}
                onOpenChange={setConfirmSair}
                titulo="Sair sem salvar?"
                descricao="Tem certeza que deseja sair? As alterações não salvas serão perdidas."
                textoConfirmar="Sair"
                variante="destructive"
                onConfirm={() => router.visit('/inquilinos')}
            />
        </>
    );
}
