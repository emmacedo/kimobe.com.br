import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import {
    EntidadeExternaForm,
    type EntidadeExternaFormData,
    dadosIniciaisEntidadeExterna,
} from '@/components/entidade-externa-form';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';

export default function CriarEntidadeExterna() {
    const { errors } = usePage().props as any;
    const [processing, setProcessing] = useState(false);
    const [confirmSair, setConfirmSair] = useState(false);
    const [dirty, setDirty] = useState(false);

    function handleSubmit(dados: EntidadeExternaFormData) {
        setProcessing(true);
        router.post('/entidades-externas', dados, {
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
            router.visit('/entidades-externas');
        }
    }

    return (
        <>
            <Head title="Nova entidade externa" />
            <div className="space-y-4">
                <PageHeader titulo="Nova entidade externa">
                    <Button variant="outline" size="sm" onClick={handleVoltar} className="border-[#D8DCDA]">
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Voltar
                    </Button>
                </PageHeader>

                <EntidadeExternaForm
                    dados={dadosIniciaisEntidadeExterna}
                    errors={errors ?? {}}
                    processing={processing}
                    onSubmit={handleSubmit}
                    onDirtyChange={setDirty}
                    onCancel={handleVoltar}
                    textoBotao="Salvar"
                />
            </div>

            <ConfirmDialog
                open={confirmSair}
                onOpenChange={setConfirmSair}
                titulo="Sair sem salvar?"
                descricao="Tem certeza que deseja sair? As alterações não salvas serão perdidas."
                textoConfirmar="Sair"
                variante="destructive"
                onConfirm={() => router.visit('/entidades-externas')}
            />
        </>
    );
}
