import { usePage } from '@inertiajs/react';

type AlertaFatura = {
    nivel: 1 | 2 | 3;
    mensagem: string;
};

/**
 * Banner de status da assinatura FullFlow. Lê `alerta_fatura` das
 * sharedProps preenchidas em HandleInertiaRequests.
 *
 * Níveis:
 *   1 → info  (cancelamento agendado, alertas leves)
 *   2 → atenção (past_due — pagamento em atraso)
 *   3 → urgência (suspensa — prestes a bloquear)
 */
export function FullFlowStatusBanner() {
    const alerta = usePage().props.alerta_fatura as AlertaFatura | null | undefined;
    if (!alerta) {
        return null;
    }

    const styles = {
        1: 'border-[#C9A84C] bg-[#FBF6E5] text-[#5D4A0E]',
        2: 'border-[#E89B3D] bg-[#FFF4E5] text-[#7A4A0A]',
        3: 'border-[#A83232] bg-[#FCE8E8] text-[#5A1010]',
    } as const;

    return (
        <div className={`border-l-4 px-4 py-3 text-sm ${styles[alerta.nivel] ?? styles[1]}`}>
            <p className="mx-auto max-w-7xl">{alerta.mensagem}</p>
        </div>
    );
}
