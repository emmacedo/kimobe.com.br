import { Head, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import {
    EntidadeExternaForm,
    type EntidadeExternaFormData,
} from '@/components/entidade-externa-form';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import type { EntidadeExterna } from '@/types/models';

type Props = {
    entidade: EntidadeExterna;
    errors?: Record<string, string>;
};

export default function EditarEntidadeExterna({ entidade, errors = {} }: Props) {
    const { flash } = usePage().props as any;
    const [processing, setProcessing] = useState(false);
    const [confirmSair, setConfirmSair] = useState(false);
    const [dirty, setDirty] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    const dadosIniciais: EntidadeExternaFormData = {
        nome: entidade.nome,
        tipo: entidade.tipo,
        cpf_cnpj: entidade.cpf_cnpj ?? '',
        telefone: entidade.telefone ?? '',
        email: entidade.email ?? '',
        site: entidade.site ?? '',
        contato_interno_nome: entidade.contato_interno_nome ?? '',
        cep: entidade.cep ?? '',
        logradouro: entidade.logradouro ?? '',
        numero: entidade.numero ?? '',
        complemento: entidade.complemento ?? '',
        bairro: entidade.bairro ?? '',
        cidade: entidade.cidade ?? '',
        uf: entidade.uf ?? '',
        observacoes: entidade.observacoes ?? '',
    };

    function handleSubmit(dados: EntidadeExternaFormData) {
        setProcessing(true);
        router.put(`/entidades-externas/${entidade.id}`, dados, {
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
            <Head title={`Editar ${entidade.nome}`} />
            <div className="space-y-4">
                <PageHeader titulo="Editar entidade externa" subtitulo={entidade.nome}>
                    <Button variant="outline" size="sm" onClick={handleVoltar} className="border-[#D8DCDA]">
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Voltar
                    </Button>
                </PageHeader>

                <EntidadeExternaForm
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
                onConfirm={() => router.visit('/entidades-externas')}
            />
        </>
    );
}
