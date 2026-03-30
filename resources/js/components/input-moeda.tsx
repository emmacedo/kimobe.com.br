import { Input } from '@/components/ui/input';

type Props = {
    value: number | string | null | undefined;
    onChange: (value: number | null) => void;
    className?: string;
    placeholder?: string;
};

/**
 * Formata número para exibição monetária pt-BR (sem o R$, só o valor).
 */
function formatarParaExibicao(valor: number | null): string {
    if (valor === null || valor === undefined || isNaN(valor)) return '';
    return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Extrai o valor numérico de uma string formatada pt-BR.
 */
function extrairValor(texto: string): number | null {
    // Remove tudo exceto dígitos e vírgula
    const limpo = texto.replace(/[^\d,]/g, '');
    if (!limpo) return null;
    // Troca vírgula por ponto para converter
    const numero = parseFloat(limpo.replace(',', '.'));
    return isNaN(numero) ? null : numero;
}

export function InputMoeda({ value, onChange, className, placeholder = 'R$ 0,00' }: Props) {
    const numValue = typeof value === 'string' ? parseFloat(value) : (value ?? null);
    const displayValue = numValue !== null ? `R$ ${formatarParaExibicao(numValue)}` : '';

    function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
        const raw = e.target.value;
        // Se o usuário apagou tudo
        if (!raw || raw === 'R$ ' || raw === 'R$') {
            onChange(null);
            return;
        }
        const numero = extrairValor(raw);
        onChange(numero);
    }

    function handleBlur(e: React.FocusEvent<HTMLInputElement>) {
        // Re-formata ao sair do campo
        const numero = extrairValor(e.target.value);
        onChange(numero);
    }

    return (
        <Input
            value={displayValue}
            onChange={handleChange}
            onBlur={handleBlur}
            placeholder={placeholder}
            className={`bg-white border-[#D8DCDA] ${className ?? ''}`}
            inputMode="decimal"
        />
    );
}
