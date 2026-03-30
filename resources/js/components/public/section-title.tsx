type Props = {
    titulo: string;
    subtitulo?: string;
    claro?: boolean;
};

export function SectionTitle({ titulo, subtitulo, claro = false }: Props) {
    return (
        <div className="mx-auto max-w-2xl text-center">
            <h2 className={`text-3xl font-semibold tracking-tight sm:text-4xl ${claro ? 'text-white' : 'text-[#1E2D30]'}`}>
                {titulo}
            </h2>
            {subtitulo && (
                <p className={`mt-4 text-lg ${claro ? 'text-[#B3DDE5]' : 'text-[#6B7370]'}`}>
                    {subtitulo}
                </p>
            )}
        </div>
    );
}
