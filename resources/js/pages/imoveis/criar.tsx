import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { GerenciadorTitulares, type TitularItem } from '@/components/gerenciador-titulares';
import { ImovelForm, condominioVazio, type ImovelFormData } from '@/components/imovel-form';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import type { Administradora } from '@/types/models';

const dadosIniciais: ImovelFormData = {
    cep: '',
    logradouro: '',
    numero: '',
    complemento: '',
    bairro: '',
    cidade: '',
    uf: '',
    inscricao_iptu: '',
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
    condominio: { ...condominioVazio },
};

type Props = {
    administradoras: Administradora[];
};

export default function CriarImovel({ administradoras }: Props) {
    const { errors } = usePage().props as any;
    const [processing, setProcessing] = useState(false);
    const [confirmSair, setConfirmSair] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [titulares, setTitulares] = useState<TitularItem[]>([]);
    const [dirtyTitulares, setDirtyTitulares] = useState(false);

    function handleSubmit(dados: ImovelFormData) {
        // Validação local: se há titulares, exige exatamente 1 responsável.
        if (titulares.length > 0) {
            const responsaveis = titulares.filter((t) => t.papel === 'responsavel').length;
            if (responsaveis !== 1) {
                toast.error('Marque exatamente 1 titular como responsável.');
                return;
            }
            const soma = titulares.reduce((acc, t) => acc + parseFloat(t.percentual || '0'), 0);
            if (soma > 100.005) {
                toast.error('A soma dos percentuais ultrapassa 100%.');
                return;
            }
        }

        const payload = {
            ...dados,
            titulares: titulares.map((t) => ({
                vinculo_id: t.vinculo_id,
                tipo_titular: t.tipo_titular,
                papel: t.papel,
                percentual: parseFloat(t.percentual),
                dados_bancarios_id: t.dados_bancarios_id,
            })),
        };

        setProcessing(true);
        router.post('/imoveis', payload, {
            onSuccess: () => setProcessing(false),
            onError: () => {
                setProcessing(false);
                toast.error('Verifique os campos e tente novamente.');
            },
        });
    }

    function handleTitularesChange(novos: TitularItem[]) {
        setTitulares(novos);
        setDirtyTitulares(novos.length > 0);
    }

    function handleVoltar() {
        if (dirty || dirtyTitulares) {
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
                    administradoras={administradoras}
                    errors={errors ?? {}}
                    processing={processing}
                    onSubmit={handleSubmit}
                    onDirtyChange={setDirty}
                    onCancel={handleVoltar}
                    textoBotao="Salvar"
                    mostrarPlaceholders={false}
                />

                <GerenciadorTitulares
                    modo="criar"
                    titulares={titulares}
                    onChange={handleTitularesChange}
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
