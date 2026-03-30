import { cn } from '@/lib/utils';

type StatusType = 'imovel' | 'contrato' | 'cobranca' | 'repasse' | 'garantia';

type StatusConfig = {
    label: string;
    className: string;
};

const statusMap: Record<StatusType, Record<string, StatusConfig>> = {
    imovel: {
        disponivel: { label: 'Disponível', className: 'bg-[#E7F7ED] text-[#1B6B3A]' },
        alugado: { label: 'Alugado', className: 'bg-[#E8F4F6] text-[#0A4F5C]' },
        manutencao: { label: 'Manutenção', className: 'bg-[#FFF4E5] text-[#8C5A10]' },
        inativo: { label: 'Inativo', className: 'bg-[#FDECEC] text-[#A83232]' },
    },
    contrato: {
        ativo: { label: 'Ativo', className: 'bg-[#E7F7ED] text-[#1B6B3A]' },
        encerrado: { label: 'Encerrado', className: 'bg-[#F7F8F7] text-[#6B7370]' },
        renovacao: { label: 'Renovação', className: 'bg-[#FBF6E8] text-[#6B5420]' },
        cancelado: { label: 'Cancelado', className: 'bg-[#FDECEC] text-[#A83232]' },
    },
    cobranca: {
        pago: { label: 'Pago', className: 'bg-[#E7F7ED] text-[#1B6B3A]' },
        pendente: { label: 'Pendente', className: 'bg-[#FFF4E5] text-[#8C5A10]' },
        atrasado: { label: 'Atrasado', className: 'bg-[#FDECEC] text-[#A83232]' },
        cancelado: { label: 'Cancelado', className: 'bg-[#F7F8F7] text-[#6B7370]' },
    },
    repasse: {
        pendente: { label: 'Pendente', className: 'bg-[#FFF4E5] text-[#8C5A10]' },
        realizado: { label: 'Realizado', className: 'bg-[#E7F7ED] text-[#1B6B3A]' },
        cancelado: { label: 'Cancelado', className: 'bg-[#FDECEC] text-[#A83232]' },
    },
    garantia: {
        ativo: { label: 'Ativa', className: 'bg-[#E7F7ED] text-[#1B6B3A]' },
        vencido: { label: 'Vencida', className: 'bg-[#FFF4E5] text-[#8C5A10]' },
        cancelado: { label: 'Cancelada', className: 'bg-[#FDECEC] text-[#A83232]' },
        resgatado: { label: 'Resgatada', className: 'bg-[#F7F8F7] text-[#6B7370]' },
    },
};

type Props = {
    status: string;
    tipo: StatusType;
    className?: string;
};

export function StatusBadge({ status, tipo, className }: Props) {
    const config = statusMap[tipo]?.[status];

    if (!config) {
        return (
            <span className={cn('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-[#F7F8F7] text-[#6B7370]', className)}>
                {status}
            </span>
        );
    }

    return (
        <span className={cn('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', config.className, className)}>
            {config.label}
        </span>
    );
}
