import { Loader2, Search } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type EnderecoViaCep = {
    logradouro: string;
    bairro: string;
    localidade: string;
    uf: string;
};

type Props = {
    value: string;
    onChange: (value: string) => void;
    onAddressFound: (endereco: EnderecoViaCep) => void;
    className?: string;
};

/**
 * Aplica a máscara de CEP: 00000-000
 */
function mascaraCep(valor: string): string {
    const digits = valor.replace(/\D/g, '').slice(0, 8);
    if (digits.length <= 5) return digits;
    return `${digits.slice(0, 5)}-${digits.slice(5)}`;
}

export function InputCep({ value, onChange, onAddressFound, className }: Props) {
    const [loading, setLoading] = useState(false);

    function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
        const masked = mascaraCep(e.target.value);
        onChange(masked);

        // Auto-busca ao completar 8 dígitos
        const digits = masked.replace(/\D/g, '');
        if (digits.length === 8) {
            buscarCep(digits);
        }
    }

    async function buscarCep(cep?: string) {
        const digits = (cep || value).replace(/\D/g, '');
        if (digits.length !== 8) {
            toast.error('Digite um CEP válido com 8 dígitos.');
            return;
        }

        setLoading(true);
        try {
            const response = await fetch(`https://viacep.com.br/ws/${digits}/json/`);
            const data = await response.json();

            if (data.erro) {
                toast.error('CEP não encontrado.');
                return;
            }

            onAddressFound({
                logradouro: data.logradouro || '',
                bairro: data.bairro || '',
                localidade: data.localidade || '',
                uf: data.uf || '',
            });
        } catch {
            toast.error('Erro ao consultar o CEP. Tente novamente.');
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className={`flex gap-2 ${className ?? ''}`}>
            <Input
                value={value}
                onChange={handleChange}
                placeholder="00000-000"
                maxLength={9}
                className="bg-white border-[#D8DCDA]"
            />
            <Button
                type="button"
                variant="outline"
                size="icon"
                onClick={() => buscarCep()}
                disabled={loading}
                className="shrink-0 border-[#D8DCDA]"
                aria-label="Buscar CEP"
            >
                {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Search className="h-4 w-4" />}
            </Button>
        </div>
    );
}
