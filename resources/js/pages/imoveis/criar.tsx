import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ImovelForm, type ImovelFormData } from '@/components/imovel-form';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';

const dadosIniciais: ImovelFormData = {
    cep: '',
    logradouro: '',
    numero: '',
    complemento: '',
    bairro: '',
    cidade: '',
    uf: '',
    tipo: '',
    status: 'disponivel',
    quartos: null,
    suites: null,
    banheiros: null,
    vagas_garagem: null,
    andar: null,
    area_m2: null,
    valor_aluguel_sugerido: null,
    observacoes: '',
};

export default function CriarImovel() {
    const { errors } = usePage().props as any;
    const [processing, setProcessing] = useState(false);
    const [confirmSair, setConfirmSair] = useState(false);
    const [dirty, setDirty] = useState(false);

    function handleSubmit(dados: ImovelFormData) {
        setProcessing(true);
        router.post('/imoveis', dados, {
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
            router.visit('/imoveis');
        }
    }

    return (
        <>
            <Head title="Novo imóvel" />
            <div className="space-y-4">
                <PageHeader titulo="Novo imóvel">
                    <Button variant="outline" size="sm" onClick={handleVoltar} className="border-[#D8DCDA]">
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Voltar
                    </Button>
                </PageHeader>

                <ImovelForm
                    dados={dadosIniciais}
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
                onConfirm={() => router.visit('/imoveis')}
            />
        </>
    );
}
