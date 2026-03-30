type Props = {
    titulo: string;
    subtitulo?: string;
    children?: React.ReactNode;
};

export function PageHeader({ titulo, subtitulo, children }: Props) {
    return (
        <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 className="text-xl font-semibold text-[#1E2D30]">{titulo}</h1>
                {subtitulo && (
                    <p className="text-sm text-[#6B7370]">{subtitulo}</p>
                )}
            </div>
            {children && <div className="flex items-center gap-2">{children}</div>}
        </div>
    );
}
