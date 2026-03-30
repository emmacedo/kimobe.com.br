import { cn } from '@/lib/utils';

type Props = {
    className?: string;
    theme?: 'light' | 'dark';
};

export function CreditosKicol({ className, theme = 'light' }: Props) {
    const textColor = theme === 'dark' ? 'text-[#B3DDE5]' : 'text-[#555]';

    return (
        <div className={cn('text-center py-5', className)}>
            <p className={cn('text-sm', textColor)}>
                <a
                    href="https://www.kicol.com.br"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-[#21A896] no-underline hover:underline"
                >
                    Desenvolvido por
                </a>
            </p>
            <a href="https://www.kicol.com.br" target="_blank" rel="noopener noreferrer" className="mt-2 inline-block">
                <img
                    src="https://kicol.com.br/wp-content/uploads/2024/10/kicol_logo_pilula-1.png"
                    alt="Selo Kicol Full Service"
                    className="h-auto w-[100px]"
                />
            </a>
        </div>
    );
}
