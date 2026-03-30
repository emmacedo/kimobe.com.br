import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { GerenciadorFotos } from '@/components/gerenciador-fotos';
import { GerenciadorTitulares } from '@/components/gerenciador-titulares';
import { ImovelForm, type ImovelFormData } from '@/components/imovel-form';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import type { ImovelFoto, Titularidade, Vinculo } from '@/types/models';

type ImovelData = {
    id: number;
    cep: string;
    logradouro: string;
    numero: string;
    complemento: string | null;
    bairro: string;
    cidade: string;
    uf: string;
    tipo: string;
    status: string;
    quartos: number | null;
    suites: number | null;
    banheiros: number | null;
    vagas_garagem: number | null;
    andar: number | null;
    area_m2: string | null;
    valor_aluguel_sugerido: string | null;
    observacoes: string | null;
    fotos: ImovelFoto[];
    titularidades: Titularidade[];
};

type Props = {
    imovel: ImovelData;
    proprietariosDisponiveis: Vinculo[];
    errors?: Record<string, string>;
};

export default function EditarImovel({ imovel, proprietariosDisponiveis = [], errors = {} }: Props) {
    const { flash } = usePage().props as any;
    const [processing, setProcessing] = useState(false);
    const [confirmSair, setConfirmSair] = useState(false);
    const [dirty, setDirty] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    const dadosIniciais: ImovelFormData = {
        cep: imovel.cep,
        logradouro: imovel.logradouro,
        numero: imovel.numero,
        complemento: imovel.complemento ?? '',
        bairro: imovel.bairro,
        cidade: imovel.cidade,
        uf: imovel.uf,
        tipo: imovel.tipo,
        status: imovel.status,
        quartos: imovel.quartos,
        suites: imovel.suites,
        banheiros: imovel.banheiros,
        vagas_garagem: imovel.vagas_garagem,
        andar: imovel.andar,
        area_m2: imovel.area_m2 ? parseFloat(imovel.area_m2) : null,
        valor_aluguel_sugerido: imovel.valor_aluguel_sugerido ? parseFloat(imovel.valor_aluguel_sugerido) : null,
        observacoes: imovel.observacoes ?? '',
    };

    const enderecoResumo = imovel.complemento
        ? `${imovel.complemento} — ${imovel.logradouro}, ${imovel.numero}`
        : `${imovel.logradouro}, ${imovel.numero}`;

    function handleSubmit(dados: ImovelFormData) {
        setProcessing(true);
        router.put(`/imoveis/${imovel.id}`, dados, {
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
            router.visit(`/imoveis/${imovel.id}`);
        }
    }

    return (
        <>
            <Head title="Editar imóvel" />
            <div className="space-y-4">
                <PageHeader titulo="Editar imóvel" subtitulo={enderecoResumo}>
                    <Button variant="outline" size="sm" onClick={handleVoltar} className="border-[#D8DCDA]">
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Voltar
                    </Button>
                </PageHeader>

                <ImovelForm
                    dados={dadosIniciais}
                    errors={errors}
                    processing={processing}
                    onSubmit={handleSubmit}
                    onDirtyChange={setDirty}
                    onCancel={handleVoltar}
                    textoBotao="Salvar alterações"
                    mostrarPlaceholders={false}
                />

                {/* Fotos — componente real */}
                <GerenciadorFotos imovelId={imovel.id} fotos={imovel.fotos ?? []} />

                {/* Titulares — componente real */}
                <GerenciadorTitulares
                    imovelId={imovel.id}
                    titularidades={imovel.titularidades ?? []}
                    proprietariosDisponiveis={proprietariosDisponiveis}
                />
            </div>

            <ConfirmDialog
                open={confirmSair}
                onOpenChange={setConfirmSair}
                titulo="Sair sem salvar?"
                descricao="Tem certeza que deseja sair? As alterações não salvas serão perdidas."
                textoConfirmar="Sair"
                variante="destructive"
                onConfirm={() => router.visit(`/imoveis/${imovel.id}`)}
            />
        </>
    );
}
