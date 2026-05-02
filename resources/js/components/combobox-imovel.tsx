import { Combobox, ComboboxInput, ComboboxOption, ComboboxOptions } from '@headlessui/react';
import { Building2, Check, Search } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { formataMoeda } from '@/lib/utils';
import type { ImovelDisponivel } from '@/types/models';

type Props = {
    value: ImovelDisponivel | null;
    onChange: (imovel: ImovelDisponivel | null) => void;
    placeholder?: string;
};

const tipoLabels: Record<string, string> = {
    apartamento: 'Apt',
    casa: 'Casa',
    sala: 'Sala',
    loja: 'Loja',
    galpao: 'Galpão',
};

/**
 * Autocomplete de imóveis SEM contrato vigente. Busca AND por palavra em endereço
 * (logradouro/complemento/bairro/cidade) e nome dos titulares.
 */
export function ComboboxImovel({
    value,
    onChange,
    placeholder = 'Buscar por endereço, bairro, cidade ou proprietário...',
}: Props) {
    const [query, setQuery] = useState('');
    const [resultados, setResultados] = useState<ImovelDisponivel[]>([]);
    const [carregando, setCarregando] = useState(false);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);
    const abortRef = useRef<AbortController | null>(null);

    const buscar = useCallback(async (termo: string) => {
        if (termo.trim().length < 2) {
            setResultados([]);
            return;
        }

        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;

        setCarregando(true);
        try {
            const response = await fetch(`/contratos/imoveis-disponiveis?q=${encodeURIComponent(termo)}`, {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                setResultados([]);
                return;
            }
            setResultados(await response.json());
        } catch (e) {
            if ((e as Error).name !== 'AbortError') setResultados([]);
        } finally {
            if (abortRef.current === controller) setCarregando(false);
        }
    }, []);

    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => buscar(query), 300);
        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, [query, buscar]);

    return (
        <Combobox<ImovelDisponivel | null>
            value={value}
            onChange={onChange}
            onClose={() => setQuery('')}
        >
            <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A918E]" />
                <ComboboxInput
                    className="w-full rounded-md border border-[#D8DCDA] bg-white py-2 pl-9 pr-3 text-sm placeholder:text-muted-foreground focus:border-[#0A4F5C] focus:outline-none focus:ring-1 focus:ring-[#0A4F5C]"
                    placeholder={placeholder}
                    displayValue={(im: ImovelDisponivel | null) => im
                        ? (im.complemento || `${im.logradouro}, ${im.numero}`)
                        : ''}
                    onChange={(e) => setQuery(e.target.value)}
                />
            </div>

            <ComboboxOptions
                anchor={{ to: 'bottom start', gap: 4 }}
                className="z-[60] w-[var(--input-width)] origin-top overflow-hidden rounded-md border border-[#D8DCDA] bg-white shadow-lg empty:invisible"
            >
                {carregando && (
                    <div className="px-3 py-2 text-xs text-[#8A918E]">Buscando...</div>
                )}
                {!carregando && query.trim().length >= 2 && resultados.length === 0 && (
                    <div className="px-3 py-3">
                        <p className="text-xs text-[#8A918E]">
                            Nenhum imóvel disponível encontrado para "{query}". Imóveis com contrato ativo são filtrados.
                        </p>
                    </div>
                )}
                {!carregando && query.trim().length < 2 && (
                    <div className="px-3 py-2 text-xs text-[#8A918E]">
                        Digite pelo menos 2 letras para buscar.
                    </div>
                )}
                {resultados.map((im) => {
                    const titulo = im.complemento || `${im.logradouro}, ${im.numero}`;
                    const proprietarios = im.titularidades.map((t) => t.vinculo.user.name).join(', ');

                    return (
                        <ComboboxOption
                            key={im.id}
                            value={im}
                            className="group flex cursor-pointer items-start gap-3 px-3 py-2 text-sm data-[focus]:bg-[#F7F8F7]"
                        >
                            <div className="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-[#EEF0EF]">
                                <Building2 className="h-3.5 w-3.5 text-[#8A918E]" />
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-medium text-[#1E2D30]">
                                    {titulo}
                                    <span className="ml-1.5 inline-flex items-center rounded-full bg-[#F7F8F7] px-1.5 py-0.5 text-[9px] font-medium text-[#3A4240]">
                                        {tipoLabels[im.tipo] ?? im.tipo}
                                    </span>
                                </p>
                                <p className="truncate text-xs text-[#8A918E]">
                                    {im.bairro}, {im.cidade}/{im.uf}
                                    {im.valor_aluguel_sugerido && (
                                        <span className="ml-1.5 font-mono text-[11px] text-[#0A4F5C]">
                                            · {formataMoeda(im.valor_aluguel_sugerido)}
                                        </span>
                                    )}
                                </p>
                                {proprietarios && (
                                    <p className="truncate text-xs text-[#6B7370]">
                                        Proprietário(s): {proprietarios}
                                    </p>
                                )}
                            </div>
                            <Check className="mt-1 hidden h-4 w-4 text-[#0A4F5C] group-data-[selected]:block" />
                        </ComboboxOption>
                    );
                })}
            </ComboboxOptions>
        </Combobox>
    );
}
