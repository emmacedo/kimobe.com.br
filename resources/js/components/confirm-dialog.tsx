import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    titulo: string;
    descricao: string | React.ReactNode;
    textoConfirmar?: string;
    textoCancelar?: string;
    variante?: 'default' | 'destructive';
    loading?: boolean;
    disabled?: boolean;
    onConfirm: () => void;
};

export function ConfirmDialog({
    open,
    onOpenChange,
    titulo,
    descricao,
    textoConfirmar = 'Confirmar',
    textoCancelar = 'Cancelar',
    variante = 'default',
    loading = false,
    disabled = false,
    onConfirm,
}: Props) {
    const isDestructive = variante === 'destructive';

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{titulo}</AlertDialogTitle>
                    <AlertDialogDescription asChild>
                        <div>{descricao}</div>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={loading}>
                        {textoCancelar}
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={(e) => {
                            e.preventDefault();
                            onConfirm();
                        }}
                        disabled={loading || disabled}
                        className={isDestructive ? 'bg-[#A83232] text-white hover:bg-[#8B2929]' : 'bg-[#0A4F5C] text-white hover:bg-[#073B45]'}
                    >
                        {loading && <Spinner />}
                        {textoConfirmar}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
