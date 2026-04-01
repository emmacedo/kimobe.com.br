import { useEffect, useState } from 'react';
import { Input } from '@/components/ui/input';

type Props = {
    value: number | string | null | undefined;
    onChange: (value: number | null) => void;
    className?: string;
    placeholder?: string;
};

/**
 * Converte centavos (inteiro) para string formatada pt-BR.
 * Ex: 3990 → "R$ 39,90" | 150000 → "R$ 1.500,00" | 0 → "R$ 0,00"
 */
function centavosParaDisplay(centavos: number): string {
    if (centavos === 0) return '';
    const reais = centavos / 100;
    return 'R$ ' + reais.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Converte valor numérico (reais com decimais) para centavos inteiros.
 * Ex: 39.9 → 3990 | 1500 → 150000
 */
function reaisParaCentavos(valor: number): number {
    return Math.round(valor * 100);
}

/**
 * Input monetário pt-BR com máscara estilo caixa registradora.
 * O usuário digita apenas números e os centavos são posicionados automaticamente.
 * Ex: digitar "3990" exibe "R$ 39,90", digitar "15000" exibe "R$ 150,00".
 */
export function InputMoeda({ value, onChange, className, placeholder = 'R$ 0,00' }: Props) {
    // Converte o valor externo (em reais) para centavos inteiros para controle interno
    const numValue = typeof value === 'string' ? parseFloat(value) : (value ?? null);
    const [centavos, setCentavos] = useState<number>(() =>
        numValue !== null && !isNaN(numValue) ? reaisParaCentavos(numValue) : 0
    );

    // Sincroniza quando o valor externo muda (ex: ao abrir edição de um plano)
    useEffect(() => {
        const novoCentavos = numValue !== null && !isNaN(numValue) ? reaisParaCentavos(numValue) : 0;
        setCentavos(novoCentavos);
    }, [numValue]);

    function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
        // Extrai apenas dígitos do input
        const apenasDigitos = e.target.value.replace(/\D/g, '');

        // Converte para inteiro (centavos)
        const novosCentavos = parseInt(apenasDigitos, 10) || 0;
        setCentavos(novosCentavos);

        // Propaga o valor em reais para o componente pai
        if (novosCentavos === 0) {
            onChange(null);
        } else {
            onChange(novosCentavos / 100);
        }
    }

    return (
        <Input
            value={centavosParaDisplay(centavos)}
            onChange={handleChange}
            placeholder={placeholder}
            className={`bg-white border-[#D8DCDA] ${className ?? ''}`}
            inputMode="numeric"
        />
    );
}
