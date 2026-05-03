import { usePage } from '@inertiajs/react';

type Can = {
    manage_imoveis: boolean;
    manage_contratos: boolean;
    manage_faturas: boolean;
    manage_repasses: boolean;
    upload_comprovantes: boolean;
    view_repasses: boolean;
    manage_dados_bancarios: boolean;
};

export function usePermissions() {
    const { current_papeis, can } = usePage().props as any;
    const papeis: string[] = current_papeis ?? [];

    return {
        papeis,
        can: (can ?? {}) as Can,
        isAdmin: papeis.includes('admin'),
        isProprietario: papeis.includes('proprietario'),
        isInquilino: papeis.includes('inquilino'),
        hasRole: (papel: string) => papeis.includes(papel),
        hasAnyRole: (...p: string[]) => p.some((r) => papeis.includes(r)),
    };
}
