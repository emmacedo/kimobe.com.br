import { Combobox, ComboboxInput, ComboboxOption, ComboboxOptions } from '@headlessui/react';
import { Check, Plus, Search } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { formataCpfCnpjMascarado } from '@/lib/utils';
import type { Inquilino } from '@/types/models';

type Props = {
    value: Inquilino | null;
    onChange: (inquilino: Inquilino | null) => void;
    onCriarNovo: () => void;
    placeholder?: string;
    excludeVinculoIds?: number[];
};

export function ComboboxInquilino({
    value,
    onChange,
    onCriarNovo,
    placeholder = 'Buscar por nome, CPF/CNPJ ou email...',
    excludeVinculoIds = [],
}: Props) {
    const [query, setQuery] = useState('');
    const [resultados, setResultados] = useState<Inquilino[]>([]);
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
            const response = await fetch(`/inquilinos/buscar?q=${encodeURIComponent(termo)}`, {
                signal: controller.signal,
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) {
                setResultados([]);
                return;
            }
            const data: Inquilino[] = await response.json();
            setResultados(data.filter((p) => !excludeVinculoIds.includes(p.vinculo_id)));
        } catch (e) {
            if ((e as Error).name !== 'AbortError') setResultados([]);
        } finally {
            if (abortRef.current === controller) setCarregando(false);
        }
    }, [excludeVinculoIds]);

    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        debounceRef.current = setTimeout(() => buscar(query), 300);
        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, [query, buscar]);

    return (
        <div className="space-y-2">
            <Combobox<Inquilino | null>
                value={value}
                onChange={onChange}
                onClose={() => setQuery('')}
            >
                <div className="relative">
                    <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A918E]" />
                    <ComboboxInput
                        className="w-full rounded-md border border-[#D8DCDA] bg-white py-2 pl-9 pr-3 text-sm placeholder:text-muted-foreground focus:border-[#0A4F5C] focus:outline-none focus:ring-1 focus:ring-[#0A4F5C]"
                        placeholder={placeholder}
                        displayValue={(p: Inquilino | null) => p?.name ?? ''}
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
                        <div className="space-y-2 p-3">
                            <p className="text-xs text-[#8A918E]">Nenhum inquilino encontrado para "{query}".</p>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={onCriarNovo}
                                className="w-full border-[#D8DCDA]"
                            >
                                <Plus className="mr-1 h-3.5 w-3.5" />
                                Cadastrar "{query.trim()}" como novo inquilino
                            </Button>
                        </div>
                    )}
                    {!carregando && query.trim().length < 2 && (
                        <div className="px-3 py-2 text-xs text-[#8A918E]">Digite pelo menos 2 letras para buscar.</div>
                    )}
                    {resultados.map((p) => (
                        <ComboboxOption
                            key={p.vinculo_id}
                            value={p}
                            className="group flex cursor-pointer items-center justify-between px-3 py-2 text-sm data-[focus]:bg-[#F7F8F7]"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-medium text-[#1E2D30]">{p.name}</p>
                                <p className="truncate text-xs text-[#8A918E]">
                                    {p.tipo_pessoa === 'pj' ? 'PJ' : 'PF'}
                                    {p.documento ? ` · ${formataCpfCnpjMascarado(p.documento)}` : ''}
                                    {p.email ? ` · ${p.email}` : ''}
                                </p>
                            </div>
                            <Check className="hidden h-4 w-4 text-[#0A4F5C] group-data-[selected]:block" />
                        </ComboboxOption>
                    ))}
                </ComboboxOptions>
            </Combobox>

            <Button
                type="button"
                variant="link"
                size="sm"
                onClick={onCriarNovo}
                className="h-auto p-0 text-xs text-[#0A4F5C]"
            >
                <Plus className="mr-1 h-3 w-3" />
                Cadastrar novo inquilino
            </Button>
        </div>
    );
}

