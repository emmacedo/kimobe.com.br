import { usePage } from '@inertiajs/react';
import { AlertCircle, AlertTriangle, X } from 'lucide-react';
import { useState } from 'react';
import { formataMoeda } from '@/lib/utils';

type AlertaFatura = {
    nivel: 1 | 2 | 3;
    referencia: string;
    valor: number;
    dias_atraso: number;
    dias_para_bloqueio: number;
    mensagem: string;
};

const nivelConfig = {
    1: { bg: 'bg-[#FFF4E5]', text: 'text-[#8C5A10]', icon: AlertTriangle, dismissible: true },
    2: { bg: 'bg-[#FEE2D5]', text: 'text-[#9A3412]', icon: AlertTriangle, dismissible: false },
    3: { bg: 'bg-[#FDECEC]', text: 'text-[#A83232]', icon: AlertCircle, dismissible: false },
};

export function BannerCobrancaKimobe() {
    const { alerta_fatura } = usePage().props as any;
    const [dismissed, setDismissed] = useState(false);

    if (!alerta_fatura || dismissed) return null;

    const alerta = alerta_fatura as AlertaFatura;
    const cfg = nivelConfig[alerta.nivel];
    const Icon = cfg.icon;

    return (
        <div className={`${cfg.bg} ${cfg.text} flex items-center justify-between px-4 py-3 text-sm ${alerta.nivel === 3 ? 'animate-pulse' : ''}`}>
            <div className="flex items-center gap-2">
                <Icon className="h-4 w-4 shrink-0" />
                <span>{alerta.mensagem}</span>
            </div>
            {cfg.dismissible && (
                <button onClick={() => setDismissed(true)} className="shrink-0 opacity-60 hover:opacity-100" aria-label="Fechar">
                    <X className="h-4 w-4" />
                </button>
            )}
        </div>
    );
}
