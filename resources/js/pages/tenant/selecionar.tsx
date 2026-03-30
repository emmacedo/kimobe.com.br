import { Head, router } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type TenantOption = {
    id: number;
    nome: string;
    tipo: 'imobiliaria' | 'proprietario_direto';
    papeis: string[];
};

type Props = {
    tenants: TenantOption[];
};

/** Labels legíveis para os tipos de tenant */
const tipoLabels: Record<string, string> = {
    imobiliaria: 'Imobiliária',
    proprietario_direto: 'Proprietário Direto',
};

/** Labels legíveis para os papéis */
const papelLabels: Record<string, string> = {
    admin: 'Admin',
    proprietario: 'Proprietário',
    inquilino: 'Inquilino',
};

export default function SelecionarTenant({ tenants }: Props) {
    function selecionar(tenantId: number) {
        router.post('/selecionar-contexto', { tenant_id: tenantId });
    }

    return (
        <>
            <Head title="Selecione o ambiente" />

            <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10">
                <div className="w-full max-w-lg">
                    <div className="flex flex-col gap-8">
                        {/* Logo e título */}
                        <div className="flex flex-col items-center gap-4">
                            <div className="flex h-9 w-9 items-center justify-center rounded-md">
                                <AppLogoIcon className="size-9 fill-current text-[#0A4F5C] dark:text-white" />
                            </div>
                            <div className="space-y-2 text-center">
                                <h1 className="text-xl font-medium">Selecione o ambiente</h1>
                                <p className="text-sm text-muted-foreground">
                                    Escolha a empresa ou contexto em que deseja trabalhar
                                </p>
                            </div>
                        </div>

                        {/* Lista de tenants */}
                        <div className="flex flex-col gap-3">
                            {tenants.map((tenant) => (
                                <Card
                                    key={tenant.id}
                                    className="cursor-pointer transition-all hover:border-[#0A4F5C] hover:shadow-md dark:hover:border-[#14788A]"
                                    onClick={() => selecionar(tenant.id)}
                                >
                                    <CardHeader className="pb-0">
                                        <div className="flex items-center justify-between gap-2">
                                            <CardTitle className="text-base">
                                                {tenant.nome}
                                            </CardTitle>
                                            <Badge
                                                variant="outline"
                                                className="shrink-0 border-[#C9A84C] text-[#C9A84C]"
                                            >
                                                {tipoLabels[tenant.tipo] ?? tenant.tipo}
                                            </Badge>
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="flex gap-2">
                                            {tenant.papeis.map((papel) => (
                                                <Badge
                                                    key={papel}
                                                    variant="secondary"
                                                    className="bg-[#0A4F5C]/10 text-[#0A4F5C] dark:bg-[#14788A]/20 dark:text-[#14788A]"
                                                >
                                                    {papelLabels[papel] ?? papel}
                                                </Badge>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
