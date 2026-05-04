import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { mesAnterior, mesParaLabel, mesProximo } from '@/lib/mes-utils';

type Props = {
    mes: string; // 'YYYY-MM'
    onChange: (mes: string) => void;
};

/**
 * Navegação ←/→ por mês, usada nas listagens de Faturas e Repasses.
 */
export function MonthNavigator({ mes, onChange }: Props) {
    return (
        <div className="inline-flex items-center gap-1 rounded-md border border-[#D8DCDA] bg-white">
            <Button
                variant="ghost"
                size="icon"
                className="h-9 w-9 rounded-none rounded-l-md text-[#3A4240] hover:bg-[#F7F8F7]"
                onClick={() => onChange(mesAnterior(mes))}
                aria-label="Mês anterior"
            >
                <ChevronLeft className="h-4 w-4" />
            </Button>
            <span className="min-w-[140px] px-3 text-center text-sm font-medium text-[#1E2D30]">
                {mesParaLabel(mes)}
            </span>
            <Button
                variant="ghost"
                size="icon"
                className="h-9 w-9 rounded-none rounded-r-md text-[#3A4240] hover:bg-[#F7F8F7]"
                onClick={() => onChange(mesProximo(mes))}
                aria-label="Próximo mês"
            >
                <ChevronRight className="h-4 w-4" />
            </Button>
        </div>
    );
}
