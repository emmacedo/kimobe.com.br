import { Input } from '@/components/ui/input';

type Props = {
    value: string;
    onChange: (value: string) => void;
    className?: string;
    placeholder?: string;
};

/**
 * Aplica máscara de CPF (000.000.000-00) ou CNPJ (00.000.000/0000-00)
 * automaticamente conforme a quantidade de dígitos.
 * <= 11 dígitos: CPF | > 11 dígitos: CNPJ.
 */
function mascaraCpfCnpj(valor: string): string {
    const d = valor.replace(/\D/g, '').slice(0, 14);

    if (d.length <= 11) {
        // CPF: 000.000.000-00
        if (d.length <= 3) return d;
        if (d.length <= 6) return `${d.slice(0, 3)}.${d.slice(3)}`;
        if (d.length <= 9) return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6)}`;
        return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9)}`;
    }

    // CNPJ: 00.000.000/0000-00
    return `${d.slice(0, 2)}.${d.slice(2, 5)}.${d.slice(5, 8)}/${d.slice(8, 12)}-${d.slice(12)}`;
}

export function InputCpfCnpj({ value, onChange, className, placeholder = '000.000.000-00' }: Props) {
    function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
        onChange(mascaraCpfCnpj(e.target.value));
    }

    return (
        <Input
            value={mascaraCpfCnpj(value)}
            onChange={handleChange}
            placeholder={placeholder}
            maxLength={18}
            className={`bg-white border-[#D8DCDA] ${className ?? ''}`}
            inputMode="numeric"
        />
    );
}
