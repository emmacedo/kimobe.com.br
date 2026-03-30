import { formataMoeda } from '@/lib/utils';

type Titular = {
    nome: string;
    percentual: number;
};

type Props = {
    valorAluguel: number;
    taxaAdministracaoPct: number;
    taxaSeguroInadimplenciaPct: number | null;
    modeloRepasse: string;
    titulares: Titular[];
};

export function SimulacaoRepasse({
    valorAluguel,
    taxaAdministracaoPct,
    taxaSeguroInadimplenciaPct,
    modeloRepasse,
    titulares,
}: Props) {
    if (!valorAluguel || valorAluguel <= 0) return null;

    const taxaAdmin = (valorAluguel * taxaAdministracaoPct) / 100;
    const taxaSeguro = modeloRepasse === 'garantido' && taxaSeguroInadimplenciaPct
        ? (valorAluguel * taxaSeguroInadimplenciaPct) / 100
        : 0;
    const liquidoTotal = valorAluguel - taxaAdmin - taxaSeguro;

    return (
        <div className="rounded-lg bg-[#F7F8F7] p-4">
            <p className="mb-3 text-xs font-medium uppercase tracking-wider text-[#8A918E]">
                Simulação de repasse mensal
            </p>

            <div className="space-y-1.5 text-sm">
                <div className="flex justify-between">
                    <span className="text-[#3A4240]">Aluguel</span>
                    <span className="font-mono font-medium text-[#1E2D30]">{formataMoeda(valorAluguel)}</span>
                </div>
                <div className="flex justify-between text-[#A83232]">
                    <span>Taxa administração ({taxaAdministracaoPct.toFixed(2)}%)</span>
                    <span className="font-mono">-{formataMoeda(taxaAdmin)}</span>
                </div>
                {modeloRepasse === 'garantido' && taxaSeguroInadimplenciaPct != null && taxaSeguroInadimplenciaPct > 0 && (
                    <div className="flex justify-between text-[#A83232]">
                        <span>Seguro inadimplência ({taxaSeguroInadimplenciaPct.toFixed(2)}%)</span>
                        <span className="font-mono">-{formataMoeda(taxaSeguro)}</span>
                    </div>
                )}
                <div className="border-t border-[#D8DCDA] pt-1.5">
                    <div className="flex justify-between">
                        <span className="font-medium text-[#1E2D30]">Líquido para proprietário(s)</span>
                        <span className="font-mono text-base font-semibold text-[#0A4F5C]">{formataMoeda(liquidoTotal)}</span>
                    </div>
                </div>
            </div>

            {/* Split por titular quando há múltiplos */}
            {titulares.length > 1 && (
                <div className="mt-3 border-t border-[#D8DCDA] pt-3">
                    <p className="mb-2 text-xs text-[#8A918E]">Divisão por titular:</p>
                    <div className="space-y-1">
                        {titulares.map((t) => {
                            const valorTitular = (liquidoTotal * t.percentual) / 100;
                            return (
                                <div key={t.nome} className="flex items-center justify-between text-xs">
                                    <span className="text-[#3A4240]">{t.nome} ({t.percentual}%)</span>
                                    <span className="font-mono font-medium text-[#1E2D30]">{formataMoeda(valorTitular)}</span>
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}
        </div>
    );
}
