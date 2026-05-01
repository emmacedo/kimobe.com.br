import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import {
    AdministradoraForm,
    type AdministradoraFormData,
    dadosIniciaisAdministradora,
} from '@/components/administradora-form';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';

export default function CriarAdministradora() {
    const { errors } = usePage().props as any;
    const [processing, setProcessing] = useState(false);
    const [confirmSair, setConfirmSair] = useState(false);
    const [dirty, setDirty] = useState(false);

    function handleSubmit(dados: AdministradoraFormData) {
        setProcessing(true);
        router.post('/administradoras', dados, {
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
            router.visit('/administradoras');
        }
    }

    return (
        <>
            <Head title="Nova administradora" />
            <div className="space-y-4">
                <PageHeader titulo="Nova administradora">
                    <Button variant="outline" size="sm" onClick={handleVoltar} className="border-[#D8DCDA]">
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Voltar
                    </Button>
                </PageHeader>

                <AdministradoraForm
                    dados={dadosIniciaisAdministradora}
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
                onConfirm={() => router.visit('/administradoras')}
            />
        </>
    );
}
