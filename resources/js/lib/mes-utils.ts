/**
 * Helpers para manipular o formato de mês 'YYYY-MM' usado nos filtros
 * temporais de Faturas e Repasses.
 */

export function mesAnterior(mes: string): string {
    const [ano, mm] = mes.split('-').map(Number);
    const d = new Date(ano, mm - 2, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export function mesProximo(mes: string): string {
    const [ano, mm] = mes.split('-').map(Number);
    const d = new Date(ano, mm, 1);
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

/**
 * 'YYYY-MM' → 'Maio 2026'.
 */
export function mesParaLabel(mes: string): string {
    const [ano, mm] = mes.split('-').map(Number);
    const d = new Date(ano, mm - 1, 1);
    const label = d.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
    return label.charAt(0).toUpperCase() + label.slice(1);
}
