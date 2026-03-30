import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Bath,
    BedDouble,
    Building2,
    Calendar,
    Camera,
    Car,
    ExternalLink,
    Landmark,
    Layers,
    MapPin,
    Pencil,
    Ruler,
    Trash2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { usePermissions } from '@/hooks/use-permissions';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formataCep, formataMoeda } from '@/lib/utils';

// Labels legíveis para tipos de imóvel
const tipoLabels: Record<string, string> = {
    apartamento: 'Apartamento',
    casa: 'Casa',
    sala: 'Sala comercial',
    loja: 'Loja',
    galpao: 'Galpão',
};

const papelLabels: Record<string, string> = {
    responsavel: 'Responsável',
    observador: 'Observador',
};

type Titular = {
    id: number;
    percentual: string;
    papel: string;
    tipo_titular: string;
    vinculo: { user: { name: string } };
    dados_bancarios: { banco_nome: string; agencia: string; conta: string } | null;
};

type Foto = {
    id: number;
    caminho: string;
    url: string;
    legenda: string | null;
    ordem: number;
};

type ContratoResumo = {
    id: number;
    valor_aluguel: string;
    status: string;
    data_inicio: string;
    data_fim: string;
    inquilino: { user: { name: string } };
};

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
    created_at: string;
    updated_at: string;
    foto_principal: Foto | null;
    fotos: Foto[];
    titularidades: Titular[];
    contratos: ContratoResumo[];
};

type Props = {
    imovel: ImovelData;
};

export default function MostrarImovel({ imovel }: Props) {
    const { flash } = usePage().props as any;
    const { can } = usePermissions();
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleteLoading, setDeleteLoading] = useState(false);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
    }, [flash?.success]);

    // Título do imóvel
    const titulo = imovel.complemento || `${imovel.logradouro}, ${imovel.numero}`;

    // Endereço completo
    const enderecoCompleto = [
        `${imovel.logradouro}, ${imovel.numero}`,
        imovel.complemento,
        imovel.bairro,
    ].filter(Boolean).join(' — ');

    const cidadeUfCep = `${imovel.cidade} — ${imovel.uf}, ${formataCep(imovel.cep)}`;

    // URL do Google Maps
    const enderecoMaps = encodeURIComponent(
        `${imovel.logradouro}, ${imovel.numero}, ${imovel.bairro}, ${imovel.cidade} - ${imovel.uf}, ${imovel.cep}`,
    );
    const googleMapsUrl = `https://www.google.com/maps/search/?api=1&query=${enderecoMaps}`;

    // Contratos ativos para validação de exclusão
    const contratosAtivos = imovel.contratos.filter((c) => c.status === 'ativo').length;

    function handleDelete() {
        setDeleteLoading(true);
        router.delete(`/imoveis/${imovel.id}`, {
            onSuccess: () => setDeleteLoading(false),
            onError: (errors) => {
                setDeleteLoading(false);
                if (errors.imovel) toast.error(errors.imovel);
                setConfirmDelete(false);
            },
        });
    }

    // Características visíveis (esconde os que são null)
    const caracteristicas = [
        imovel.quartos !== null ? { icon: BedDouble, label: 'Quartos', value: imovel.quartos } : null,
        imovel.suites !== null ? { icon: BedDouble, label: 'Suítes', value: imovel.suites } : null,
        imovel.banheiros !== null ? { icon: Bath, label: 'Banheiros', value: imovel.banheiros } : null,
        imovel.vagas_garagem !== null ? { icon: Car, label: 'Vagas', value: imovel.vagas_garagem } : null,
        imovel.andar !== null ? { icon: Layers, label: 'Andar', value: `${imovel.andar}º` } : null,
        imovel.area_m2 ? { icon: Ruler, label: 'Área', value: `${parseFloat(imovel.area_m2).toLocaleString('pt-BR')} m²` } : null,
    ].filter(Boolean) as Array<{ icon: React.ElementType; label: string; value: string | number }>;

    return (
        <>
            <Head title={titulo} />
            <div className="space-y-4">
                {/* Header */}
                <PageHeader titulo={titulo}>
                    <div className="flex items-center gap-2">
                        <StatusBadge status={imovel.status} tipo="imovel" />
                        {can.manage_imoveis && (
                            <>
                                <Button variant="outline" size="sm" asChild className="border-[#D8DCDA]">
                                    <Link href={`/imoveis/${imovel.id}/editar`}>
                                        <Pencil className="mr-1 h-3.5 w-3.5" />
                                        Editar
                                    </Link>
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-8 w-8 text-[#A83232] hover:text-[#A83232]"
                                    onClick={() => setConfirmDelete(true)}
                                    aria-label="Excluir imóvel"
                                >
                                    <Trash2 className="h-4 w-4" />
                                </Button>
                            </>
                        )}
                    </div>
                </PageHeader>

                {/* Grid principal */}
                <div className="grid gap-4 lg:grid-cols-3">
                    {/* Coluna principal (2/3) */}
                    <div className="space-y-4 lg:col-span-2">
                        {/* Card Endereço */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <div className="mb-3 flex items-center gap-2 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">
                                <MapPin className="h-3.5 w-3.5" />
                                Endereço
                            </div>
                            <p className="text-sm font-medium text-[#1E2D30]">{enderecoCompleto}</p>
                            <p className="mt-1 text-sm text-[#6B7370]">{cidadeUfCep}</p>
                            <a
                                href={googleMapsUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="mt-3 inline-flex items-center gap-1 text-xs text-[#0A4F5C] hover:underline"
                            >
                                Ver no mapa <ExternalLink className="h-3 w-3" />
                            </a>
                        </div>

                        {/* Card Características */}
                        {caracteristicas.length > 0 && (
                            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                                <div className="mb-3 flex items-center justify-between">
                                    <span className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">
                                        Características
                                    </span>
                                    <span className="inline-flex items-center rounded-full bg-[#F7F8F7] px-2.5 py-0.5 text-xs font-medium text-[#3A4240]">
                                        {tipoLabels[imovel.tipo] ?? imovel.tipo}
                                    </span>
                                </div>
                                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                    {caracteristicas.map((c) => (
                                        <div key={c.label} className="flex items-center gap-2.5">
                                            <div className="flex h-8 w-8 items-center justify-center rounded-md bg-[#EEF0EF]">
                                                <c.icon className="h-4 w-4 text-[#6B7370]" />
                                            </div>
                                            <div>
                                                <p className="text-xs text-[#8A918E]">{c.label}</p>
                                                <p className="text-sm font-medium text-[#1E2D30]">{c.value}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Card Observações */}
                        {imovel.observacoes && (
                            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                                <p className="mb-2 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Observações</p>
                                <p className="whitespace-pre-line text-sm text-[#3A4240]">{imovel.observacoes}</p>
                            </div>
                        )}

                        {/* Card Contratos */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Contratos</p>
                            {imovel.contratos.length === 0 ? (
                                <p className="text-sm text-[#8A918E]">Nenhum contrato vinculado</p>
                            ) : (
                                <div className="space-y-3">
                                    {imovel.contratos.map((contrato) => (
                                        <Link
                                            key={contrato.id}
                                            href={`/contratos/${contrato.id}`}
                                            className="flex items-center justify-between rounded-md border border-[#EEF0EF] px-3 py-2.5 transition-colors hover:bg-[#FAFBFA]"
                                        >
                                            <div>
                                                <p className="text-sm font-medium text-[#1E2D30]">
                                                    {contrato.inquilino.user.name}
                                                </p>
                                                <p className="text-xs text-[#8A918E]">
                                                    {new Date(contrato.data_inicio).toLocaleDateString('pt-BR')} a{' '}
                                                    {new Date(contrato.data_fim).toLocaleDateString('pt-BR')}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <span className="font-mono text-sm font-medium text-[#1E2D30]">
                                                    {formataMoeda(contrato.valor_aluguel)}
                                                </span>
                                                <StatusBadge status={contrato.status} tipo="contrato" />
                                            </div>
                                        </Link>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Coluna lateral (1/3) */}
                    <div className="space-y-4">
                        {/* Card Foto principal */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Foto principal</p>
                            {imovel.foto_principal?.url ? (
                                <img
                                    src={imovel.foto_principal.url}
                                    alt={imovel.foto_principal.legenda ?? 'Foto do imóvel'}
                                    className="aspect-[4/3] w-full rounded-md object-cover"
                                />
                            ) : (
                                <div className="flex h-36 items-center justify-center rounded-md bg-[#EEF0EF]">
                                    <Camera className="h-8 w-8 text-[#8A918E]" />
                                </div>
                            )}
                            <p className="mt-2 text-center text-xs text-[#8A918E]">
                                {imovel.fotos.length} foto(s)
                            </p>
                        </div>

                        {/* Card Titulares */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Titulares</p>
                            {imovel.titularidades.length === 0 ? (
                                <p className="text-sm text-[#8A918E]">Nenhum titular cadastrado</p>
                            ) : (
                                <div className="space-y-3">
                                    {imovel.titularidades.map((tit) => (
                                        <div key={tit.id} className="flex items-center justify-between">
                                            <div>
                                                <p className="text-sm font-medium text-[#1E2D30]">
                                                    {tit.vinculo.user.name}
                                                </p>
                                                <Badge variant="secondary" className="mt-0.5 text-[10px]">
                                                    {papelLabels[tit.papel] ?? tit.papel}
                                                </Badge>
                                                {tit.dados_bancarios && (
                                                    <p className="mt-1 flex items-center gap-1 text-[10px] text-[#8A918E]">
                                                        <Landmark className="h-3 w-3" />
                                                        {tit.dados_bancarios.banco_nome}
                                                    </p>
                                                )}
                                            </div>
                                            <span className="text-sm font-medium text-[#0A4F5C]">
                                                {parseFloat(tit.percentual).toFixed(0)}%
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        {/* Card Valor sugerido */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-1 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Valor sugerido</p>
                            <p className="font-mono text-xl font-medium text-[#0A4F5C]">
                                {imovel.valor_aluguel_sugerido
                                    ? formataMoeda(imovel.valor_aluguel_sugerido)
                                    : 'Não definido'}
                            </p>
                        </div>

                        {/* Card Info do cadastro */}
                        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                            <p className="mb-3 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Informações</p>
                            <div className="space-y-2 text-sm">
                                <div className="flex items-center gap-2 text-[#6B7370]">
                                    <Calendar className="h-3.5 w-3.5" />
                                    <span>Criado em {new Date(imovel.created_at).toLocaleDateString('pt-BR')}</span>
                                </div>
                                <div className="flex items-center gap-2 text-[#6B7370]">
                                    <Calendar className="h-3.5 w-3.5" />
                                    <span>Atualizado em {new Date(imovel.updated_at).toLocaleDateString('pt-BR')}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {/* Modal de exclusão */}
            <ConfirmDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                titulo="Excluir imóvel"
                descricao={
                    <div className="space-y-2">
                        <p>
                            Tem certeza que deseja excluir o imóvel <strong>{titulo}</strong>?
                            Esta ação não pode ser desfeita.
                        </p>
                        {contratosAtivos > 0 && (
                            <p className="rounded-md bg-[#FFF4E5] p-3 text-sm text-[#8C5A10]">
                                Este imóvel possui {contratosAtivos} contrato(s) ativo(s). Não é possível
                                excluí-lo enquanto houver contratos vinculados.
                            </p>
                        )}
                    </div>
                }
                textoConfirmar="Excluir"
                variante="destructive"
                loading={deleteLoading}
                disabled={contratosAtivos > 0}
                onConfirm={handleDelete}
            />
        </>
    );
}
