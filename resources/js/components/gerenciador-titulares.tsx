import { Landmark, Pencil, Plus, Trash2, UserPlus } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { DialogTitularForm, type TitularFormData } from '@/components/dialog-titular-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { getCsrfToken } from '@/lib/csrf';
import type { DadosBancarios, Titularidade } from '@/types/models';

/**
 * Item interno do gerenciador. Cobre tanto titularidades persistidas (modo editar)
 * quanto titulares pendentes (modo criar). IDs negativos identificam itens locais.
 */
export type TitularItem = {
    id: number;
    vinculo_id: number;
    tipo_titular: 'pessoa_fisica' | 'empresa' | 'inventario';
    papel: 'responsavel' | 'observador';
    percentual: string;
    dados_bancarios_id: number | null;
    proprietario_nome: string;
    proprietario_tipo_pessoa: 'pf' | 'pj';
    proprietario_documento: string | null;
    dados_bancarios_resumo: string | null;
};

type ModoCriarProps = {
    modo: 'criar';
    titulares: TitularItem[];
    onChange: (t: TitularItem[]) => void;
};

type ModoEditarProps = {
    modo: 'editar';
    imovelId: number;
    titularidades: Titularidade[];
};

type Props = ModoCriarProps | ModoEditarProps;

const papelLabels: Record<string, string> = { responsavel: 'Responsável', observador: 'Observador' };
const tipoLabels: Record<string, string> = {
    pessoa_fisica: 'Pessoa física',
    empresa: 'Empresa',
    inventario: 'Inventário',
};

function resumoConta(c: DadosBancarios | null | undefined): string | null {
    if (!c) return null;

    return `${c.banco_nome} — Ag ${c.agencia} CC ${c.conta}`;
}

function titularidadeParaItem(t: Titularidade): TitularItem {
    return {
        id: t.id,
        vinculo_id: t.vinculo_id,
        tipo_titular: t.tipo_titular,
        papel: t.papel,
        percentual: t.percentual,
        dados_bancarios_id: t.dados_bancarios_id,
        proprietario_nome: t.vinculo.user.name,
        proprietario_tipo_pessoa: (t.vinculo.user.tipo_pessoa as 'pf' | 'pj') ?? 'pf',
        proprietario_documento: t.vinculo.user.documento ?? null,
        dados_bancarios_resumo: resumoConta(t.dados_bancarios),
    };
}

export function GerenciadorTitulares(props: Props) {
    // Estado local inicializado lazy a partir das props. Após o mount, mudanças
    // vêm exclusivamente dos handlers internos (fetch no modo editar; setTitulares
    // direto no modo criar). Sem sincronização com props pra evitar lint suppression
    // e dependências instáveis. Se o pai precisar resetar a lista, força remount via key.
    const [titulares, setTitulares] = useState<TitularItem[]>(() =>
        props.modo === 'criar' ? props.titulares : props.titularidades.map(titularidadeParaItem),
    );

    // Modo criar: propaga estado para o pai a cada mudança (via ref pra manter callback estável).
    const onChangeRef = useRef(props.modo === 'criar' ? props.onChange : null);
    if (props.modo === 'criar') {
        onChangeRef.current = props.onChange;
    }
    useEffect(() => {
        onChangeRef.current?.(titulares);
    }, [titulares]);

    const [dialogOpen, setDialogOpen] = useState(false);
    const [editando, setEditando] = useState<TitularItem | null>(null);
    const [saving, setSaving] = useState(false);
    const [deleteTarget, setDeleteTarget] = useState<TitularItem | null>(null);
    const [deleteLoading, setDeleteLoading] = useState(false);

    // Soma e progresso
    const somaPercentuais = titulares.reduce((acc, t) => acc + parseFloat(t.percentual || '0'), 0);
    const disponivel = editando
        ? 100 - somaPercentuais + parseFloat(editando.percentual || '0')
        : 100 - somaPercentuais;
    const barraCorEstilo = somaPercentuais === 100
        ? 'bg-[#1B6B3A]'
        : somaPercentuais > 100
            ? 'bg-[#A83232]'
            : 'bg-[#C9A84C]';

    const excludeVinculoIds = useMemo(
        () => titulares.filter((t) => !editando || t.id !== editando.id).map((t) => t.vinculo_id),
        [titulares, editando],
    );

    const outroResponsavelExiste = titulares.some(
        (t) => t.papel === 'responsavel' && (!editando || t.id !== editando.id),
    );

    function abrirAdicionar() {
        setEditando(null);
        setDialogOpen(true);
    }

    function abrirEditar(t: TitularItem) {
        setEditando(t);
        setDialogOpen(true);
    }

    async function handleSalvar(form: TitularFormData) {
        const valorPerc = parseFloat(form.percentual);

        if (props.modo === 'criar') {
            persistirLocal(form, valorPerc);
            setDialogOpen(false);

            return;
        }

        // Modo editar: persiste via fetch.
        setSaving(true);
        try {
            const url = editando
                ? `/imoveis/${props.imovelId}/titularidades/${editando.id}`
                : `/imoveis/${props.imovelId}/titularidades`;
            const method = editando ? 'PUT' : 'POST';

            const body: Record<string, unknown> = {
                tipo_titular: form.tipo_titular,
                papel: form.papel,
                percentual: valorPerc,
                dados_bancarios_id: form.dados_bancarios_id,
            };
            if (!editando) body.vinculo_id = form.proprietario!.vinculo_id;

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify(body),
            });

            if (!response.ok) {
                const data = await response.json();
                toast.error(data.message ?? 'Erro ao salvar titular.');

                return;
            }

            const titularidade: Titularidade = await response.json();
            const novoItem = titularidadeParaItem(titularidade);

            setTitulares((prev) => {
                // Backend já demote os outros responsáveis: refletimos localmente.
                const lista = novoItem.papel === 'responsavel'
                    ? prev.map((t) => (t.id !== novoItem.id ? { ...t, papel: 'observador' as const } : t))
                    : prev;

                if (editando) return lista.map((t) => (t.id === novoItem.id ? novoItem : t));

                return [...lista, novoItem];
            });

            toast.success(editando ? 'Titular atualizado.' : 'Titular adicionado.');
            setDialogOpen(false);
        } catch {
            toast.error('Erro ao salvar titular.');
        } finally {
            setSaving(false);
        }
    }

    /**
     * Modo criar: aplica radio behavior local e persiste no estado interno.
     * Não chama endpoints — tudo será enviado no submit do imóvel.
     */
    function persistirLocal(form: TitularFormData, valorPerc: number) {
        setTitulares((prev) => {
            // Snapshot da conta selecionada para exibir o resumo na lista.
            // Como as contas não são propagadas para o pai, capturamos o resumo via id.
            const novoResumo: string | null = null; // o dialog gerencia internamente; sem fetch aqui.

            let novos = prev;
            if (form.papel === 'responsavel') {
                novos = novos.map((t) => ({ ...t, papel: 'observador' as const }));
            }

            if (editando) {
                return novos.map((t) =>
                    t.id === editando.id
                        ? {
                              ...t,
                              tipo_titular: form.tipo_titular,
                              papel: form.papel,
                              percentual: valorPerc.toString(),
                              dados_bancarios_id: form.dados_bancarios_id,
                              dados_bancarios_resumo: novoResumo,
                          }
                        : t,
                );
            }

            if (!form.proprietario) return novos;

            return [
                ...novos,
                {
                    id: -Date.now(),
                    vinculo_id: form.proprietario.vinculo_id,
                    tipo_titular: form.tipo_titular,
                    papel: form.papel,
                    percentual: valorPerc.toString(),
                    dados_bancarios_id: form.dados_bancarios_id,
                    proprietario_nome: form.proprietario.name,
                    proprietario_tipo_pessoa: form.proprietario.tipo_pessoa,
                    proprietario_documento: form.proprietario.documento,
                    dados_bancarios_resumo: novoResumo,
                },
            ];
        });
    }

    async function handleExcluir() {
        if (!deleteTarget) return;

        if (props.modo === 'criar') {
            const eraResponsavel = deleteTarget.papel === 'responsavel';
            setTitulares((prev) => {
                const restantes = prev.filter((t) => t.id !== deleteTarget.id);
                if (eraResponsavel && restantes.length > 0) {
                    const [primeiro, ...resto] = restantes;

                    return [{ ...primeiro, papel: 'responsavel' }, ...resto];
                }

                return restantes;
            });
            setDeleteTarget(null);

            return;
        }

        setDeleteLoading(true);
        try {
            const response = await fetch(`/imoveis/${props.imovelId}/titularidades/${deleteTarget.id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': getCsrfToken(), Accept: 'application/json' },
            });

            if (!response.ok) {
                const data = await response.json();
                toast.error(data.message ?? 'Erro ao remover titular.');

                return;
            }

            const eraResponsavel = deleteTarget.papel === 'responsavel';
            setTitulares((prev) => {
                const restantes = prev.filter((t) => t.id !== deleteTarget.id);
                if (eraResponsavel && restantes.length > 0) {
                    const [primeiro, ...resto] = restantes;

                    return [{ ...primeiro, papel: 'responsavel' }, ...resto];
                }

                return restantes;
            });
            toast.success('Titular removido.');
        } catch {
            toast.error('Erro ao remover titular.');
        } finally {
            setDeleteLoading(false);
            setDeleteTarget(null);
        }
    }

    return (
        <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-sm font-medium text-[#1E2D30]">Titulares</h2>
                <Button variant="outline" size="sm" onClick={abrirAdicionar} className="border-[#D8DCDA]">
                    <Plus className="mr-1 h-3.5 w-3.5" />
                    Adicionar titular
                </Button>
            </div>

            {titulares.length > 0 && (
                <div className="mb-4">
                    <div className="mb-1 flex items-center justify-between text-xs">
                        <span className="text-[#6B7370]">Soma dos percentuais</span>
                        <span
                            className={
                                somaPercentuais === 100
                                    ? 'text-[#1B6B3A]'
                                    : somaPercentuais > 100
                                        ? 'text-[#A83232]'
                                        : 'text-[#8C5A10]'
                            }
                        >
                            {somaPercentuais.toFixed(0)}%
                            {somaPercentuais < 100 && ` — Faltam ${(100 - somaPercentuais).toFixed(0)}%`}
                            {somaPercentuais > 100 && ` — Excede ${(somaPercentuais - 100).toFixed(0)}%`}
                        </span>
                    </div>
                    <div className="h-2 overflow-hidden rounded-full bg-[#EEF0EF]">
                        <div
                            className={`h-full rounded-full transition-all ${barraCorEstilo}`}
                            style={{ width: `${Math.min(somaPercentuais, 100)}%` }}
                        />
                    </div>
                </div>
            )}

            {titulares.length === 0 ? (
                <div className="flex flex-col items-center py-6 text-center">
                    <UserPlus className="mb-2 h-8 w-8 text-[#8A918E]" />
                    <p className="text-sm text-[#8A918E]">Nenhum titular cadastrado</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {titulares.map((t) => (
                        <div
                            key={t.id}
                            className="flex items-center justify-between rounded-lg border border-[#EEF0EF] px-4 py-3"
                        >
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center gap-2">
                                    <p className="text-sm font-medium text-[#1E2D30]">{t.proprietario_nome}</p>
                                    <Badge
                                        variant="secondary"
                                        className={
                                            t.papel === 'responsavel'
                                                ? 'bg-[#E8F4F6] text-[#0A4F5C]'
                                                : 'bg-[#F7F8F7] text-[#6B7370]'
                                        }
                                    >
                                        {papelLabels[t.papel]}
                                    </Badge>
                                    <Badge variant="outline" className="text-[10px]">
                                        {tipoLabels[t.tipo_titular]}
                                    </Badge>
                                </div>
                                <p className="mt-0.5 text-xs text-[#8A918E]">
                                    {t.dados_bancarios_resumo ? (
                                        <span className="flex items-center gap-1">
                                            <Landmark className="h-3 w-3" />
                                            {t.dados_bancarios_resumo}
                                        </span>
                                    ) : (
                                        <span className="text-[#8C5A10]">Sem conta bancária</span>
                                    )}
                                </p>
                            </div>
                            <div className="flex items-center gap-3">
                                <span className="text-lg font-medium text-[#0A4F5C]">
                                    {parseFloat(t.percentual).toFixed(0)}%
                                </span>
                                <div className="flex items-center gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="h-7 w-7"
                                        onClick={() => abrirEditar(t)}
                                        aria-label="Editar titular"
                                    >
                                        <Pencil className="h-3.5 w-3.5 text-[#6B7370]" />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="h-7 w-7"
                                        onClick={() => setDeleteTarget(t)}
                                        aria-label="Remover titular"
                                    >
                                        <Trash2 className="h-3.5 w-3.5 text-[#A83232]" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            <DialogTitularForm
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                editando={editando}
                disponivel={disponivel}
                outroResponsavelExiste={outroResponsavelExiste}
                excludeVinculoIds={excludeVinculoIds}
                onSalvar={handleSalvar}
                saving={saving}
            />

            <ConfirmDialog
                open={!!deleteTarget}
                onOpenChange={(open) => !open && setDeleteTarget(null)}
                titulo="Remover titular"
                descricao={
                    deleteTarget
                        ? `Tem certeza que deseja remover ${deleteTarget.proprietario_nome} como titular deste imóvel?`
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
