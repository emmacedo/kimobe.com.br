import { useState } from 'react';
import { Input } from '@/components/ui/input';

type Props = {
    value: string;
    onChange: (value: string) => void;
    className?: string;
    placeholder?: string;
    disabled?: boolean;
};

/**
 * Aplica máscara de CPF: 000.000.000-00
 */
function mascaraCpf(valor: string): string {
    const d = valor.replace(/\D/g, '').slice(0, 11);
    if (d.length <= 3) return d;
    if (d.length <= 6) return `${d.slice(0, 3)}.${d.slice(3)}`;
    if (d.length <= 9) return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6)}`;
    return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9)}`;
}

/**
 * Valida CPF pelo algoritmo dos dígitos verificadores.
 * Retorna true se o CPF é válido, false se inválido.
 */
function validarCpf(valor: string): boolean {
    const digits = valor.replace(/\D/g, '');
    if (digits.length !== 11) return false;
    // Rejeita sequências iguais (111.111.111-11, etc.)
    if (/^(\d)\1{10}$/.test(digits)) return false;

    // Cálculo dos dígitos verificadores
    let soma = 0;
    for (let i = 0; i < 9; i++) soma += parseInt(digits[i]) * (10 - i);
    let resto = (soma * 10) % 11;
    if (resto === 10) resto = 0;
    if (resto !== parseInt(digits[9])) return false;

    soma = 0;
    for (let i = 0; i < 10; i++) soma += parseInt(digits[i]) * (11 - i);
    resto = (soma * 10) % 11;
    if (resto === 10) resto = 0;
    return resto === parseInt(digits[10]);
}

export function InputCpf({ value, onChange, className, placeholder = '000.000.000-00', disabled = false }: Props) {
    const [tocado, setTocado] = useState(false);

    const digits = value.replace(/\D/g, '');
    // Só valida se o campo foi tocado (blur) e está completo (11 dígitos)
    const invalido = tocado && digits.length === 11 && !validarCpf(value);

    function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
        onChange(mascaraCpf(e.target.value));
    }

    function handleBlur() {
        setTocado(true);
    }

    return (
        <div>
            <Input
                value={value}
                onChange={handleChange}
                onBlur={handleBlur}
                placeholder={placeholder}
                maxLength={14}
                className={`bg-white border-[#D8DCDA] ${invalido ? 'border-[#A83232] focus:border-[#A83232] focus:ring-[#A83232]' : ''} ${className ?? ''}`}
                inputMode="numeric"
                disabled={disabled}
            />
            {invalido && (
                <p className="mt-1 text-xs text-[#A83232]">CPF inválido</p>
            )}
        </div>
    );
}
