import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { ContratoForm, type ContratoFormData } from '@/components/contrato-form';
import { GerenciadorFiadores } from '@/components/gerenciador-fiadores';
import { GerenciadorInquilinos } from '@/components/gerenciador-inquilinos';
import { GerenciadorResponsabilidades } from '@/components/gerenciador-responsabilidades';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';

type Props = {
    contrato: any;
    errors?: Record<string, string>;
};

export default function EditarContrato({ contrato, errors = {} }: Props) {
    const { flash } = usePage().props as any;
    const [processing, setProcessing] = useState(false);
    const [confirmSair, setConfirmSair] = useState(false);
    const [dirty, setDirty] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    const imovel = contrato.imovel;
    const titulo = imovel.complemento || `${imovel.logradouro}, ${imovel.numero}`;

    const dadosIniciais: ContratoFormData = {
        imovel_id: contrato.imovel_id,
        valor_aluguel: parseFloat(contrato.valor_aluguel),
        dia_vencimento: contrato.dia_vencimento,
        data_inicio: contrato.data_inicio?.split('T')[0] ?? '',
        data_fim: contrato.data_fim?.split('T')[0] ?? '',
        indice_reajuste: contrato.indice_reajuste,
        mes_reajuste: contrato.mes_reajuste,
        modelo_repasse: contrato.modelo_repasse,
        taxa_administracao_pct: parseFloat(contrato.taxa_administracao_pct),
        taxa_seguro_inadimplencia_pct: contrato.taxa_seguro_inadimplencia_pct ? parseFloat(contrato.taxa_seguro_inadimplencia_pct) : null,
        multa_atraso_pct: parseFloat(contrato.multa_atraso_pct),
        juros_atraso_pct_dia: parseFloat(contrato.juros_atraso_pct_dia),
        dias_carencia: contrato.dias_carencia,
        multa_rescisoria_pct: contrato.multa_rescisoria_pct ? parseFloat(contrato.multa_rescisoria_pct) : null,
        desconto_pontualidade_pct: contrato.desconto_pontualidade_pct ? parseFloat(contrato.desconto_pontualidade_pct) : null,
        tipo_garantia: contrato.tipo_garantia,
        garantia_valor: contrato.garantia?.valor ? parseFloat(contrato.garantia.valor) : null,
        garantia_seguradora: contrato.garantia?.seguradora ?? '',
        garantia_numero_apolice: contrato.garantia?.numero_apolice ?? '',
        garantia_numero_titulo: contrato.garantia?.numero_titulo ?? '',
        garantia_data_inicio: contrato.garantia?.data_inicio?.split('T')[0] ?? '',
        garantia_data_fim: contrato.garantia?.data_fim?.split('T')[0] ?? '',
        observacoes: contrato.observacoes ?? '',
    };

    function handleSubmit(dados: ContratoFormData) {
        setProcessing(true);
        router.put(`/contratos/${contrato.id}`, dados, {
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
            router.visit(`/contratos/${contrato.id}`);
        }
    }

    return (
        <>
            <Head title="Editar contrato" />
            <div className="space-y-4">
                <PageHeader titulo="Editar contrato" subtitulo={titulo}>
                    <Button variant="outline" size="sm" onClick={handleVoltar} className="border-[#D8DCDA]">
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Voltar
                    </Button>
                </PageHeader>

                <ContratoForm
                    dados={dadosIniciais}
                    errors={errors}
                    processing={processing}
                    onSubmit={handleSubmit}
                    onDirtyChange={setDirty}
                    onCancel={handleVoltar}
                    textoBotao="Salvar alterações"
                    modoEdicao
                    imovelAtual={imovel}
                />

                {/* Inquilinos do contrato (gerenciador via endpoints) */}
                <GerenciadorInquilinos
                    modo="editar"
                    contratoId={contrato.id}
                    inquilinos={contrato.inquilinos ?? []}
                />

                {/* Responsabilidades financeiras */}
                <GerenciadorResponsabilidades
                    contratoId={contrato.id}
                    responsabilidades={contrato.responsabilidades ?? []}
                />

                {/* Fiadores (só aparece se tipo_garantia é fiador) */}
                <GerenciadorFiadores
                    contratoId={contrato.id}
                    fiadores={contrato.fiadores ?? []}
                    tipoGarantia={contrato.tipo_garantia}
                />
            </div>

            <ConfirmDialog
                open={confirmSair}
                onOpenChange={setConfirmSair}
                titulo="Sair sem salvar?"
                descricao="Tem certeza que deseja sair? As alterações não salvas serão perdidas."
                textoConfirmar="Sair"
                variante="destructive"
                onConfirm={() => router.visit(`/contratos/${contrato.id}`)}
            />
        </>
    );
}
