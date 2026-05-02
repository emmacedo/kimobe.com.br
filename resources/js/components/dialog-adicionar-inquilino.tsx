import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { ComboboxInquilino } from '@/components/combobox-inquilino';
import { DialogCriarInquilino } from '@/components/dialog-criar-inquilino';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { Inquilino } from '@/types/models';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    excludeVinculoIds: number[];
    /** Se já existe um principal, novo inquilino entra como co-inquilino por default. */
    jaTemPrincipal: boolean;
    onSalvar: (inquilino: Inquilino, principal: boolean) => Promise<void>;
    saving: boolean;
};

export function DialogAdicionarInquilino({
    open,
    onOpenChange,
    excludeVinculoIds,
    jaTemPrincipal,
    onSalvar,
    saving,
}: Props) {
    const [selecionado, setSelecionado] = useState<Inquilino | null>(null);
    const [marcarPrincipal, setMarcarPrincipal] = useState(false);
    const [dialogCriar, setDialogCriar] = useState(false);

    useEffect(() => {
        if (open) {
            setSelecionado(null);
            // Se ainda não há principal, o novo entra como principal por padrão.
            setMarcarPrincipal(!jaTemPrincipal);
        }
    }, [open, jaTemPrincipal]);

    function handleInquilinoCriado(inq: Inquilino) {
        setSelecionado(inq);
    }

    async function handleSalvar() {
        if (!selecionado) {
            toast.error('Selecione um inquilino.');
            return;
        }
        await onSalvar(selecionado, marcarPrincipal);
    }

    return (
        <>
            <Dialog open={open} onOpenChange={onOpenChange}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Adicionar inquilino</DialogTitle>
                        <DialogDescription>
                            Busque um inquilino existente ou cadastre um novo. Marque como principal o responsável pelo pagamento.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div>
                            <Label>Inquilino</Label>
                            <ComboboxInquilino
                                value={selecionado}
                                onChange={setSelecionado}
                                onCriarNovo={() => setDialogCriar(true)}
                                excludeVinculoIds={excludeVinculoIds}
                            />
                        </div>

                        <div className="rounded-md border border-[#D8DCDA] bg-[#FAFBFA] p-3">
                            <label className="flex cursor-pointer items-start gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={marcarPrincipal}
                                    onChange={(e) => setMarcarPrincipal(e.target.checked)}
                                    className="mt-0.5 h-4 w-4 rounded border-[#D8DCDA] text-[#0A4F5C]"
                                />
                                <div>
                                    <p className="font-medium text-[#1E2D30]">Inquilino principal</p>
                                    <p className="text-xs text-[#8A918E]">
                                        O principal é o responsável pelo pagamento e destinatário das notificações de cobrança.
                                        {jaTemPrincipal && marcarPrincipal && (
                                            <span className="mt-1 block text-[#8C5A10]">
                                                Marcar este como principal vai mover o atual para co-inquilino.
                                            </span>
                                        )}
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => onOpenChange(false)} className="border-[#D8DCDA]">
                            Cancelar
                        </Button>
                        <Button
                            onClick={handleSalvar}
                            disabled={saving || !selecionado}
                            className="bg-[#0A4F5C] text-white hover:bg-[#073B45]"
                        >
                            {saving && <Spinner />}
                            Adicionar
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <DialogCriarInquilino
                open={dialogCriar}
                onOpenChange={setDialogCriar}
                onInquilinoCriado={handleInquilinoCriado}
            />
        </>
    );
}
