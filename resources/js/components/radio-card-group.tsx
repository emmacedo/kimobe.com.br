import { cn } from '@/lib/utils';

type Option = {
    value: string;
    titulo: string;
    descricao: string;
    icone?: React.ReactNode;
};

type Props = {
    options: Option[];
    value: string;
    onChange: (value: string) => void;
    activeColor?: string;
};

export function RadioCardGroup({ options, value, onChange, activeColor = 'border-[#0A4F5C]' }: Props) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {options.map((opt) => {
                const isActive = value === opt.value;
                return (
                    <button
                        key={opt.value}
                        type="button"
                        onClick={() => onChange(opt.value)}
                        className={cn(
                            'flex items-start gap-3 rounded-[10px] border-2 bg-white p-4 text-left transition-all',
                            isActive ? activeColor : 'border-[#D8DCDA] hover:border-[#8A918E]',
                        )}
                    >
                        {opt.icone && (
                            <div className={cn(
                                'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-md',
                                isActive ? 'bg-[#0A4F5C]/10' : 'bg-[#EEF0EF]',
                            )}>
                                {opt.icone}
                            </div>
                        )}
                        <div>
                            <p className={cn('text-sm font-medium', isActive ? 'text-[#1E2D30]' : 'text-[#3A4240]')}>
                                {opt.titulo}
                            </p>
                            <p className="mt-0.5 text-xs text-[#6B7370]">{opt.descricao}</p>
                        </div>
                    </button>
                );
            })}
        </div>
    );
}
