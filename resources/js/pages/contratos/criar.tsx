import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { ContratoForm, type ContratoFormData } from '@/components/contrato-form';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';

const dadosIniciais: ContratoFormData = {
    imovel_id: null,
    inquilino_vinculo_id: null,
    valor_aluguel: null,
    dia_vencimento: null,
    data_inicio: '',
    data_fim: '',
    indice_reajuste: '',
    mes_reajuste: null,
    modelo_repasse: 'por_recebimento',
    taxa_administracao_pct: 10,
    taxa_seguro_inadimplencia_pct: null,
    multa_atraso_pct: 2,
    juros_atraso_pct_dia: 0.0333,
    dias_carencia: 0,
    multa_rescisoria_pct: null,
    desconto_pontualidade_pct: null,
    tipo_garantia: '',
    garantia_valor: null,
    garantia_seguradora: '',
    garantia_numero_apolice: '',
    garantia_numero_titulo: '',
    garantia_data_inicio: '',
    garantia_data_fim: '',
    observacoes: '',
};

type Props = {
    imoveisDisponiveis: any[];
    inquilinosDisponiveis: any[];
};

export default function CriarContrato({ imoveisDisponiveis, inquilinosDisponiveis }: Props) {
    const { errors } = usePage().props as any;
    const [processing, setProcessing] = useState(false);
    const [confirmSair, setConfirmSair] = useState(false);
    const [dirty, setDirty] = useState(false);

    function handleSubmit(dados: ContratoFormData) {
        setProcessing(true);
        router.post('/contratos', dados, {
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
            router.visit('/contratos');
        }
    }

    return (
        <>
            <Head title="Novo contrato" />
            <div className="space-y-4">
                <PageHeader titulo="Novo contrato">
                    <Button variant="outline" size="sm" onClick={handleVoltar} className="border-[#D8DCDA]">
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Voltar
                    </Button>
                </PageHeader>

                <ContratoForm
                    dados={dadosIniciais}
                    errors={errors ?? {}}
                    processing={processing}
                    onSubmit={handleSubmit}
                    onDirtyChange={setDirty}
                    onCancel={handleVoltar}
                    textoBotao="Salvar contrato"
                    imoveisDisponiveis={imoveisDisponiveis}
                    inquilinosDisponiveis={inquilinosDisponiveis}
                />
            </div>

            <ConfirmDialog
                open={confirmSair}
                onOpenChange={setConfirmSair}
                titulo="Sair sem salvar?"
                descricao="Tem certeza que deseja sair? As alterações não salvas serão perdidas."
                textoConfirmar="Sair"
                variante="destructive"
                onConfirm={() => router.visit('/contratos')}
            />
        </>
    );
}
