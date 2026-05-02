import { Plus, Star, Trash2, UserPlus } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DialogAdicionarInquilino } from '@/components/dialog-adicionar-inquilino';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { getCsrfToken } from '@/lib/csrf';
import type { Inquilino } from '@/types/models';

/**
 * Item local: cobre tanto inquilinos persistidos (modo editar) quanto pendentes (modo criar).
 * IDs negativos indicam itens locais ainda não persistidos.
 */
export type InquilinoItem = {
    id: number;
    vinculo_id: number;
    principal: boolean;
    nome: string;
    tipo_pessoa: 'pf' | 'pj';
    documento: string | null;
};

type ContratoInquilinoBackend = {
    id: number;
    vinculo_id: number;
    principal: boolean;
    vinculo: {
        id: number;
        user: {
            name: string;
            tipo_pessoa?: 'pf' | 'pj';
            documento?: string | null;
        };
    };
};

type ModoCriarProps = {
    modo: 'criar';
    inquilinos: InquilinoItem[];
    onChange: (i: InquilinoItem[]) => void;
};

type ModoEditarProps = {
    modo: 'editar';
    contratoId: number;
    inquilinos: ContratoInquilinoBackend[];
};

type Props = ModoCriarProps | ModoEditarProps;

function backendParaItem(ci: ContratoInquilinoBackend): InquilinoItem {
    return {
        id: ci.id,
        vinculo_id: ci.vinculo_id,
        principal: ci.principal,
        nome: ci.vinculo.user.name,
        tipo_pessoa: ci.vinculo.user.tipo_pessoa ?? 'pf',
        documento: ci.vinculo.user.documento ?? null,
    };
}

export function GerenciadorInquilinos(props: Props) {
    // Estado local inicializado lazy a partir das props. Após o mount, mudanças
    // vêm exclusivamente dos handlers internos (fetch no modo editar; setInquilinos
    // direto no modo criar). Sem sincronização com props pra evitar lint suppression
    // e dependências instáveis. Se o pai precisar resetar a lista, força remount via key.
    const [inquilinos, setInquilinos] = useState<InquilinoItem[]>(() =>
        props.modo === 'criar' ? props.inquilinos : props.inquilinos.map(backendParaItem),
    );

    // Modo criar: propaga estado para o pai a cada mudança.
    const onChangeRef = useRef(props.modo === 'criar' ? props.onChange : null);
    if (props.modo === 'criar') {
        onChangeRef.current = props.onChange;
    }
    useEffect(() => {
        onChangeRef.current?.(inquilinos);
    }, [inquilinos]);

    const [dialogOpen, setDialogOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<InquilinoItem | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);

    const excludeVinculoIds = useMemo(() => inquilinos.map((i) => i.vinculo_id), [inquilinos]);
    const jaTemPrincipal = inquilinos.some((i) => i.principal);

    async function handleAdicionar(inquilino: Inquilino, principal: boolean) {
        if (props.modo === 'criar') {
            setInquilinos((prev) => {
                let novos = prev;
                if (principal) {
                    novos = novos.map((i) => ({ ...i, principal: false }));
                }
                const item: InquilinoItem = {
                    id: -Date.now(),
                    vinculo_id: inquilino.vinculo_id,
                    principal,
                    nome: inquilino.name,
                    tipo_pessoa: inquilino.tipo_pessoa,
                    documento: inquilino.documento,
                };
                return [...novos, item];
            });
            setDialogOpen(false);
            return;
        }

        // Modo editar: persiste via fetch.
        setSaving(true);
        try {
            const response = await fetch(`/contratos/${props.contratoId}/inquilinos`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ vinculo_id: inquilino.vinculo_id, principal }),
            });

            if (!response.ok) {
                const data = await response.json();
                toast.error(data.message ?? 'Erro ao adicionar inquilino.');
                return;
            }

            const ci: ContratoInquilinoBackend = await response.json();
            const novoItem = backendParaItem(ci);

            setInquilinos((prev) => {
                const lista = novoItem.principal
                    ? prev.map((i) => ({ ...i, principal: false }))
                    : prev;
                return [...lista, novoItem];
            });
            toast.success('Inquilino adicionado.');
            setDialogOpen(false);
        } catch {
            toast.error('Erro ao adicionar inquilino.');
        } finally {
            setSaving(false);
        }
    }

    async function handleTornarPrincipal(item: InquilinoItem) {
        if (item.principal) return;

        if (props.modo === 'criar') {
            setInquilinos((prev) => prev.map((i) => ({ ...i, principal: i.id === item.id })));
            return;
        }

        try {
            const response = await fetch(`/contratos/${props.contratoId}/inquilinos/${item.id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ principal: true }),
            });

            if (!response.ok) {
                const data = await response.json();
                toast.error(data.message ?? 'Erro ao atualizar inquilino.');
                return;
            }

            setInquilinos((prev) => prev.map((i) => ({ ...i, principal: i.id === item.id })));
            toast.success('Inquilino marcado como principal.');
        } catch {
            toast.error('Erro ao atualizar inquilino.');
        }
    }

    async function handleExcluir() {
        if (!deleteTarget) return;

        if (props.modo === 'criar') {
            const eraPrincipal = deleteTarget.principal;
            setInquilinos((prev) => {
                const restantes = prev.filter((i) => i.id !== deleteTarget.id);
                if (eraPrincipal && restantes.length > 0) {
                    const [primeiro, ...resto] = restantes;
                    return [{ ...primeiro, principal: true }, ...resto];
                }
                return restantes;
            });
            setDeleteTarget(null);
            return;
        }

        setDeleteLoading(true);
        try {
            const response = await fetch(`/contratos/${props.contratoId}/inquilinos/${deleteTarget.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
            });

            const data = await response.json();

            if (!response.ok) {
                toast.error(data.message ?? 'Erro ao remover inquilino.');
                return;
            }

            const novoPrincipalId: number | null = data.novo_principal_id ?? null;
            setInquilinos((prev) => {
                const restantes = prev.filter((i) => i.id !== deleteTarget.id);
                // Backend retorna o id do novo principal — usamos para garantir consistência
                // com a regra de promoção no servidor (orderBy id ascendente).
                if (novoPrincipalId !== null) {
                    return restantes.map((i) => ({ ...i, principal: i.id === novoPrincipalId }));
                }
                return restantes;
            });
            toast.success('Inquilino removido.');
        } catch {
            toast.error('Erro ao remover inquilino.');
        } finally {
            setDeleteLoading(false);
            setDeleteTarget(null);
        }
    }

    return (
        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-sm font-medium text-[#1E2D30]">Inquilinos</h2>
                <Button variant="outline" size="sm" onClick={() => setDialogOpen(true)} className="border-[#D8DCDA]">
                    <Plus className="mr-1 h-3.5 w-3.5" />
                    Adicionar inquilino
                </Button>
            </div>

            {inquilinos.length === 0 ? (
                <div className="flex flex-col items-center py-6 text-center">
                    <UserPlus className="mb-2 h-8 w-8 text-[#8A918E]" />
                    <p className="text-sm text-[#8A918E]">Nenhum inquilino adicionado</p>
                    <p className="mt-1 text-xs text-[#8C5A10]">É necessário ao menos 1 inquilino principal.</p>
                </div>
            ) : (
                <div className="space-y-2">
                    {inquilinos.map((i) => (
                        <div
                            key={i.id}
                            className="flex items-center justify-between rounded-lg border border-[#EEF0EF] px-4 py-3"
                        >
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <p className="text-sm font-medium text-[#1E2D30]">{i.nome}</p>
                                    {i.principal && (
                                        <Badge className="bg-[#E8F4F6] text-[#0A4F5C]">
                                            <Star className="mr-1 h-2.5 w-2.5 fill-current" />
                                            Principal
                                        </Badge>
                                    )}
                                    <Badge variant="outline" className="text-[10px]">
                                        {i.tipo_pessoa === 'pj' ? 'PJ' : 'PF'}
                                    </Badge>
                                </div>
                            </div>
                            <div className="flex items-center gap-1">
                                {!i.principal && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => handleTornarPrincipal(i)}
                                        className="h-7 text-xs text-[#0A4F5C]"
                                    >
                                        Tornar principal
                                    </Button>
                                )}
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-7 w-7"
                                    onClick={() => setDeleteTarget(i)}
                                    aria-label="Remover inquilino"
                                >
                                    <Trash2 className="h-3.5 w-3.5 text-[#A83232]" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <DialogAdicionarInquilino
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                excludeVinculoIds={excludeVinculoIds}
                jaTemPrincipal={jaTemPrincipal}
                onSalvar={handleAdicionar}
                saving={saving}
            />

            <ConfirmDialog
                open={!!deleteTarget}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                titulo="Remover inquilino"
                descricao={
                    deleteTarget
                        ? `Tem certeza que deseja remover ${deleteTarget.nome} do contrato?${
                              deleteTarget.principal && inquilinos.length > 1
                                  ? ' Outro inquilino será automaticamente promovido a principal.'
                                  : ''
                          }`
                        : ''
                }
                textoConfirmar="Remover"
                variante="destructive"
                loading={deleteLoading}
                onConfirm={handleExcluir}
            />
        </div>
    );
}
