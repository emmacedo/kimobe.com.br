import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Fiador } from '@/types/models';

type Props = {
    fiador: Fiador | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

function Info({ label, value }: { label: string; value: string | null | undefined }) {
    return (
        <div>
            <p className="text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">{label}</p>
            <p className="mt-0.5 text-sm text-[#1E2D30]">{value || '—'}</p>
        </div>
    );
}

export function FiadorDetalhesDialog({ fiador, open, onOpenChange }: Props) {
    if (!fiador) return null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Dados do fiador</DialogTitle>
                </DialogHeader>
                <div className="space-y-4">
                    <div>
                        <p className="mb-2 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Dados pessoais</p>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <Info label="Nome" value={fiador.nome} />
                            <Info label="CPF" value={fiador.cpf} />
                            <Info label="RG" value={fiador.rg} />
                            <Info label="Profissão" value={fiador.profissao} />
                            <Info label="Estado civil" value={fiador.estado_civil} />
                        </div>
                    </div>
                    <div>
                        <p className="mb-2 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Contato</p>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <Info label="Telefone" value={fiador.telefone} />
                            <Info label="Email" value={fiador.email} />
                        </div>
                    </div>
                    <div>
                        <p className="mb-2 text-[10px] font-medium uppercase tracking-wider text-[#8A918E]">Endereço</p>
                        <p className="text-sm text-[#3A4240]">
                            {fiador.logradouro}, {fiador.numero}
                            {fiador.complemento ? ` — ${fiador.complemento}` : ''}
                        </p>
                        <p className="text-sm text-[#6B7370]">
                            {fiador.bairro}, {fiador.cidade}/{fiador.uf} — CEP {fiador.cep}
                        </p>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
