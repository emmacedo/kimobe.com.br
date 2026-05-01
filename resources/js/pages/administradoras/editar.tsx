import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
    AdministradoraForm,
    type AdministradoraFormData,
} from '@/components/administradora-form';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import type { Administradora } from '@/types/models';

type Props = {
    administradora: Administradora;
    errors?: Record<string, string>;
};

export default function EditarAdministradora({ administradora, errors = {} }: Props) {
    const { flash } = usePage().props as any;
    const [processing, setProcessing] = useState(false);
    const [confirmSair, setConfirmSair] = useState(false);
    const [dirty, setDirty] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    const dadosIniciais: AdministradoraFormData = {
        nome: administradora.nome,
        cpf_cnpj: administradora.cpf_cnpj ?? '',
        telefone: administradora.telefone ?? '',
        email: administradora.email ?? '',
        site: administradora.site ?? '',
        contato_interno_nome: administradora.contato_interno_nome ?? '',
        cep: administradora.cep ?? '',
        logradouro: administradora.logradouro ?? '',
        numero: administradora.numero ?? '',
        complemento: administradora.complemento ?? '',
        bairro: administradora.bairro ?? '',
        cidade: administradora.cidade ?? '',
        uf: administradora.uf ?? '',
        observacoes: administradora.observacoes ?? '',
    };

    function handleSubmit(dados: AdministradoraFormData) {
        setProcessing(true);
        router.put(`/administradoras/${administradora.id}`, dados, {
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
            <Head title={`Editar ${administradora.nome}`} />
            <div className="space-y-4">
                <PageHeader titulo="Editar administradora" subtitulo={administradora.nome}>
                    <Button variant="outline" size="sm" onClick={handleVoltar} className="border-[#D8DCDA]">
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Voltar
                    </Button>
                </PageHeader>

                <AdministradoraForm
                    dados={dadosIniciais}
                    errors={errors}
                    processing={processing}
                    onSubmit={handleSubmit}
                    onDirtyChange={setDirty}
                    onCancel={handleVoltar}
                    textoBotao="Salvar alterações"
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
