import type { LucideIcon } from 'lucide-react';

type Props = {
    icone: LucideIcon;
    titulo: string;
    descricao: string;
    acao?: React.ReactNode;
};

export function EmptyState({ icone: Icone, titulo, descricao, acao }: Props) {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-[#D8DCDA] bg-white px-6 py-16 text-center">
            <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-[#EEF0EF]">
                <Icone className="h-7 w-7 text-[#8A918E]" />
            </div>
            <h3 className="mb-1 text-base font-medium text-[#1E2D30]">{titulo}</h3>
            <p className="mb-6 max-w-sm text-sm text-[#6B7370]">{descricao}</p>
            {acao}
        </div>
    );
}
