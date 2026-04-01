import { Input } from '@/components/ui/input';

type Props = {
    value: string;
    onChange: (value: string) => void;
    className?: string;
    placeholder?: string;
};

/**
 * Aplica máscara de telefone brasileiro.
 * Celular (11 dígitos): (00) 00000-0000
 * Fixo (10 dígitos):    (00) 0000-0000
 */
function mascaraTelefone(valor: string): string {
    const d = valor.replace(/\D/g, '').slice(0, 11);
    if (d.length <= 2) return d.length ? `(${d}` : '';
    if (d.length <= 6) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
    if (d.length <= 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
    // 11 dígitos — celular: 5 dígitos antes do traço
    return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
}

export function InputTelefone({ value, onChange, className, placeholder = '(00) 00000-0000' }: Props) {
    function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
        onChange(mascaraTelefone(e.target.value));
    }

    return (
        <Input
            value={value}
            onChange={handleChange}
            placeholder={placeholder}
            maxLength={15}
            className={`bg-white border-[#D8DCDA] ${className ?? ''}`}
            inputMode="tel"
        />
    );
}
