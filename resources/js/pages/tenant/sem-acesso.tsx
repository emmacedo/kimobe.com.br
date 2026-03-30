import { Head, router } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';

export default function SemAcesso() {
    function logout() {
        router.post('/logout');
    }

    return (
        <>
            <Head title="Sem acesso" />

            <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
                <div className="w-full max-w-sm">
                    <div className="flex flex-col items-center gap-6 text-center">
                        {/* Logo */}
                        <div className="flex h-9 w-9 items-center justify-center rounded-md">
                            <AppLogoIcon className="size-9 fill-current text-[#0A4F5C] dark:text-white" />
                        </div>

                        {/* Mensagem */}
                        <div className="space-y-2">
                            <h1 className="text-xl font-medium">Sem acesso</h1>
                            <p className="text-sm text-muted-foreground">
                                Você ainda não possui acesso a nenhuma empresa.
                                Entre em contato com a imobiliária ou administrador
                                que gerencia seus imóveis.
                            </p>
                        </div>

                        {/* Botão de logout */}
                        <Button
                            variant="outline"
                            className="w-full"
                            onClick={logout}
                        >
                            Sair
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
