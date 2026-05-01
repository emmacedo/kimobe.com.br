import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import {
    ProprietarioForm,
    type ProprietarioFormData,
} from '@/components/proprietario-form';
import { Button } from '@/components/ui/button';
import type { Proprietario } from '@/types/models';

type Props = {
    proprietario: Proprietario;
    errors?: Record<string, string>;
};

export default function EditarProprietario({ proprietario, errors = {} }: Props) {
    const { flash } = usePage().props as any;
    const [processing, setProcessing] = useState(false);
    const [confirmSair, setConfirmSair] = useState(false);
    const [dirty, setDirty] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    const dadosIniciais: ProprietarioFormData = {
        name: proprietario.name,
        tipo_pessoa: proprietario.tipo_pessoa,
        documento: proprietario.documento ?? '',
        telefone: proprietario.telefone ?? '',
        email: proprietario.email ?? '', // null quando placeholder — vem como '' aqui
    };

    function handleSubmit(dados: ProprietarioFormData) {
        setProcessing(true);
        router.put(`/proprietarios/${proprietario.vinculo_id}`, dados, {
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
            router.visit('/proprietarios');
        }
    }

    return (
        <>
            <Head title={`Editar ${proprietario.name}`} />
            <div className="space-y-4">
                <PageHeader titulo="Editar proprietário" subtitulo={proprietario.name}>
                    <Button variant="outline" size="sm" onClick={handleVoltar} className="border-[#D8DCDA]">
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Voltar
                    </Button>
                </PageHeader>

                <ProprietarioForm
                    dados={dadosIniciais}
                    errors={errors}
                    processing={processing}
                    onSubmit={handleSubmit}
                    onDirtyChange={setDirty}
                    onCancel={handleVoltar}
                    textoBotao="Salvar alterações"
                    avisoEmailPlaceholder={proprietario.email_placeholder}
                />
            </div>

            <ConfirmDialog
                open={confirmSair}
                onOpenChange={setConfirmSair}
                titulo="Sair sem salvar?"
                descricao="Tem certeza que deseja sair? As alterações não salvas serão perdidas."
                textoConfirmar="Sair"
                variante="destructive"
                onConfirm={() => router.visit('/proprietarios')}
            />
        </>
    );
}
