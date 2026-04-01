import { useState } from 'react';
import { Input } from '@/components/ui/input';

type Props = {
    value: string;
    onChange: (value: string) => void;
    className?: string;
    placeholder?: string;
};

/**
 * Aplica máscara de CNPJ: 00.000.000/0000-00
 */
function mascaraCnpj(valor: string): string {
    const d = valor.replace(/\D/g, '').slice(0, 14);
    if (d.length <= 2) return d;
    if (d.length <= 5) return `${d.slice(0, 2)}.${d.slice(2)}`;
    if (d.length <= 8) return `${d.slice(0, 2)}.${d.slice(2, 5)}.${d.slice(5)}`;
    if (d.length <= 12) return `${d.slice(0, 2)}.${d.slice(2, 5)}.${d.slice(5, 8)}/${d.slice(8)}`;
    return `${d.slice(0, 2)}.${d.slice(2, 5)}.${d.slice(5, 8)}/${d.slice(8, 12)}-${d.slice(12)}`;
}

/**
 * Valida CNPJ pelo algoritmo dos dígitos verificadores.
 */
function validarCnpj(valor: string): boolean {
    const digits = valor.replace(/\D/g, '');
    if (digits.length !== 14) return false;
    // Rejeita sequências iguais
    if (/^(\d)\1{13}$/.test(digits)) return false;

    // Primeiro dígito verificador
    const pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    let soma = 0;
    for (let i = 0; i < 12; i++) soma += parseInt(digits[i]) * pesos1[i];
    let resto = soma % 11;
    const dv1 = resto < 2 ? 0 : 11 - resto;
    if (parseInt(digits[12]) !== dv1) return false;

    // Segundo dígito verificador
    const pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
    soma = 0;
    for (let i = 0; i < 13; i++) soma += parseInt(digits[i]) * pesos2[i];
    resto = soma % 11;
    const dv2 = resto < 2 ? 0 : 11 - resto;
    return parseInt(digits[13]) === dv2;
}

export function InputCnpj({ value, onChange, className, placeholder = '00.000.000/0000-00' }: Props) {
    const [tocado, setTocado] = useState(false);

    const digits = value.replace(/\D/g, '');
    const invalido = tocado && digits.length === 14 && !validarCnpj(value);

    function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
        onChange(mascaraCnpj(e.target.value));
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
                maxLength={18}
                className={`bg-white border-[#D8DCDA] ${invalido ? 'border-[#A83232] focus:border-[#A83232] focus:ring-[#A83232]' : ''} ${className ?? ''}`}
                inputMode="numeric"
            />
            {invalido && (
                <p className="mt-1 text-xs text-[#A83232]">CNPJ inválido</p>
            )}
        </div>
    );
}
