import { useEffect, useRef, useState } from 'react';
import { Input } from '@/components/ui/input';

type Props = {
    value: number | string | null | undefined;
    onChange: (value: number | null) => void;
    className?: string;
    placeholder?: string;
};

/**
 * Formata número para exibição monetária pt-BR com prefixo R$.
 * Ex: 1500.5 → "R$ 1.500,50"
 */
function formatarParaExibicao(valor: number | null): string {
    if (valor === null || valor === undefined || isNaN(valor)) return '';
    return 'R$ ' + valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Extrai valor numérico de string pt-BR (aceita ponto como milhar e vírgula como decimal).
 * Ex: "1.500,50" → 1500.5 | "999" → 999 | "12,5" → 12.5
 */
function extrairValor(texto: string): number | null {
    // Remove tudo exceto dígitos, vírgula e ponto
    const limpo = texto.replace(/[^\d.,]/g, '');
    if (!limpo) return null;
    // Remove pontos de milhar e troca vírgula decimal por ponto
    const normalizado = limpo.replace(/\./g, '').replace(',', '.');
    const numero = parseFloat(normalizado);
    return isNaN(numero) ? null : numero;
}

/**
 * Input monetário pt-BR.
 * Permite digitação livre (sem reformatar a cada tecla) e formata apenas no blur,
 * evitando o ciclo de feedback que corrompia valores acima de 3 dígitos.
 */
export function InputMoeda({ value, onChange, className, placeholder = 'R$ 0,00' }: Props) {
    const numValue = typeof value === 'string' ? parseFloat(value) : (value ?? null);

    // Estado interno controla o texto exibido — só formata no blur ou quando value muda externamente
    const [textoInterno, setTextoInterno] = useState(() => formatarParaExibicao(numValue));
    const focadoRef = useRef(false);

    // Sincroniza o texto exibido quando o valor externo muda (ex: ao abrir edição)
    // mas NÃO reformata enquanto o campo estiver focado (usuário digitando)
    useEffect(() => {
        if (!focadoRef.current) {
            setTextoInterno(formatarParaExibicao(numValue));
        }
    }, [numValue]);

    function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
        const raw = e.target.value;
        // Atualiza o texto exibido sem reformatar — permite digitação livre
        setTextoInterno(raw);

        // Propaga o valor numérico para o estado pai
        if (!raw || raw.trim() === 'R$' || raw.trim() === 'R$ ') {
            onChange(null);
            return;
        }
        const numero = extrairValor(raw);
        onChange(numero);
    }

    function handleFocus() {
        focadoRef.current = true;
    }

    function handleBlur() {
        focadoRef.current = false;
        // Ao sair do campo, reformata para exibição limpa (R$ 1.500,00)
        const numero = extrairValor(textoInterno);
        onChange(numero);
        setTextoInterno(formatarParaExibicao(numero));
    }

    return (
        <Input
            value={textoInterno}
            onChange={handleChange}
            onFocus={handleFocus}
            onBlur={handleBlur}
            placeholder={placeholder}
            className={`bg-white border-[#D8DCDA] ${className ?? ''}`}
            inputMode="decimal"
        />
    );
}
