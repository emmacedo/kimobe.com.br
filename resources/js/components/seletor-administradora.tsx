import { Plus } from 'lucide-react';
import { useState } from 'react';
import { DialogCriarAdministradora } from '@/components/dialog-criar-administradora';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Administradora } from '@/types/models';

type Props = {
    administradoras: Administradora[];
    value: number | null;
    onChange: (administradoraId: number | null) => void;
    onAdministradoraCriada: (administradora: Administradora) => void;
};

const VALOR_NENHUMA = '__nenhuma__';

export function SeletorAdministradora({ administradoras, value, onChange, onAdministradoraCriada }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);

    function handleValueChange(v: string) {
        if (v === VALOR_NENHUMA) {
            onChange(null);
            return;
        }
        onChange(parseInt(v, 10));
    }

    function handleAdministradoraCriada(administradora: Administradora) {
        onAdministradoraCriada(administradora);
        onChange(administradora.id);
    }

    return (
        <>
            <div className="flex items-stretch gap-2">
                <Select value={value === null ? VALOR_NENHUMA : String(value)} onValueChange={handleValueChange}>
                    <SelectTrigger className="bg-white border-[#D8DCDA]">
                        <SelectValue placeholder="Selecione a administradora" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={VALOR_NENHUMA}>Nenhuma</SelectItem>
                        {administradoras.map((adm) => (
                            <SelectItem key={adm.id} value={String(adm.id)}>
                                {adm.nome}
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

            <DialogCriarAdministradora
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                onAdministradoraCriada={handleAdministradoraCriada}
            />
        </>
    );
}
