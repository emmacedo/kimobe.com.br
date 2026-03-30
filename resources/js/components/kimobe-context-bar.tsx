/**
 * Barra de contexto com alertas pendentes.
 * Por enquanto, componente preparado sem lógica real — será alimentado nas próximas etapas.
 */

type Props = {
    mensagem?: string;
    textoBotao?: string;
    onAction?: () => void;
};

export function KimobeContextBar({ mensagem, textoBotao, onAction }: Props) {
    if (!mensagem) return null;

    return (
        <div className="flex items-center justify-between bg-[#073B45] px-4 py-3 text-sm md:px-6">
            <span className="text-[#B3DDE5]" dangerouslySetInnerHTML={{ __html: mensagem }} />
            {textoBotao && (
                <button
                    onClick={onAction}
                    className="shrink-0 rounded-md bg-[#C9A84C] px-4 py-1.5 text-xs font-medium text-white transition-colors hover:bg-[#B8993F]"
                >
                    {textoBotao}
                </button>
            )}
        </div>
    );
}
