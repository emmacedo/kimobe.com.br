import { Plus } from 'lucide-react';
import { useState } from 'react';
import { DialogCriarEntidadeExterna } from '@/components/dialog-criar-entidade-externa';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { EntidadeExterna, TipoEntidadeExterna } from '@/types/models';

type Props = {
    entidades: EntidadeExterna[];
    value: number | null;
    onChange: (entidadeId: number | null) => void;
    onEntidadeCriada: (entidade: EntidadeExterna) => void;
    /**
     * Tipo padrão para criação inline. Default `administradora_condominio`.
     */
    tipoCriacao?: TipoEntidadeExterna;
    placeholder?: string;
    tituloDialog?: string;
};

const VALOR_NENHUMA = '__nenhuma__';

export function SeletorEntidadeExterna({
    entidades,
    value,
    onChange,
    onEntidadeCriada,
    tipoCriacao = 'administradora_condominio',
    placeholder = 'Selecione a administradora',
    tituloDialog = 'Cadastrar nova administradora',
}: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);

    function handleValueChange(v: string) {
        if (v === VALOR_NENHUMA) {
            onChange(null);
            return;
        }
        onChange(parseInt(v, 10));
    }

    function handleEntidadeCriada(entidade: EntidadeExterna) {
        onEntidadeCriada(entidade);
        onChange(entidade.id);
    }

    return (
        <>
            <div className="flex items-stretch gap-2">
                <Select value={value === null ? VALOR_NENHUMA : String(value)} onValueChange={handleValueChange}>
                    <SelectTrigger className="bg-white border-[#D8DCDA]">
                        <SelectValue placeholder={placeholder} />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={VALOR_NENHUMA}>Nenhuma</SelectItem>
                        {entidades.map((ent) => (
                            <SelectItem key={ent.id} value={String(ent.id)}>
                                {ent.nome}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => setDialogOpen(true)}
                    className="shrink-0 border-[#D8DCDA]"
                >
                    <Plus className="mr-1 h-4 w-4" />
                    Nova
                </Button>
            </div>

            <DialogCriarEntidadeExterna
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                onEntidadeCriada={handleEntidadeCriada}
                tipo={tipoCriacao}
                titulo={tituloDialog}
            />
        </>
    );
}
