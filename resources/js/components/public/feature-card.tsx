import type { LucideIcon } from 'lucide-react';

type Props = {
    icone: LucideIcon;
    titulo: string;
    descricao: string;
};

export function FeatureCard({ icone: Icone, titulo, descricao }: Props) {
    return (
        <div className="rounded-xl bg-white p-6 shadow-sm border border-[#EEF0EF] transition-shadow hover:shadow-md">
            <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-lg bg-[#E8F4F6]">
                <Icone className="h-6 w-6 text-[#0A4F5C]" />
            </div>
            <h3 className="mb-2 text-lg font-medium text-[#1E2D30]">{titulo}</h3>
            <p className="text-sm leading-relaxed text-[#6B7370]">{descricao}</p>
        </div>
    );
}
