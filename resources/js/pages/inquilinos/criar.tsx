import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { PessoaForm, dadosIniciaisPessoa, type PessoaFormData } from '@/components/pessoa-form';
import { Button } from '@/components/ui/button';

const AVISO_EMAIL_INQUILINO = 'Este inquilino foi cadastrado sem email. Adicione um email real se quiser que ele tenha acesso ao sistema futuramente.';

export default function CriarInquilino() {
    const { errors } = usePage().props as any;
    const [processing, setProcessing] = useState(false);
    const [confirmSair, setConfirmSair] = useState(false);
    const [dirty, setDirty] = useState(false);

    function handleSubmit(dados: PessoaFormData) {
        setProcessing(true);
        router.post('/inquilinos', dados, {
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
            <Head title="Novo inquilino" />
            <div className="space-y-4">
                <PageHeader titulo="Novo inquilino">
                    <Button variant="outline" size="sm" onClick={handleVoltar} className="border-[#D8DCDA]">
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Voltar
                    </Button>
                </PageHeader>

                <PessoaForm
                    dados={dadosIniciaisPessoa}
                    titulo="Dados do inquilino"
                    avisoEmailTexto={AVISO_EMAIL_INQUILINO}
                    errors={errors ?? {}}
                    processing={processing}
                    onSubmit={handleSubmit}
                    onDirtyChange={setDirty}
                    onCancel={handleVoltar}
                    textoBotao="Salvar"
                    avisoEmailPlaceholder
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
