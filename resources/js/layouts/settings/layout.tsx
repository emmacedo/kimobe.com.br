import { Link } from '@inertiajs/react';
import { Building2, CreditCard, Lock, User } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermissions } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';

type NavItem = { title: string; href: string; icon: React.ElementType; adminOnly?: boolean };

const allNavItems: NavItem[] = [
    { title: 'Meu perfil', href: '/settings/perfil', icon: User },
    { title: 'Minha empresa', href: '/settings/empresa', icon: Building2, adminOnly: true },
    { title: 'Meu plano', href: '/settings/plano', icon: CreditCard, adminOnly: true },
    { title: 'Segurança', href: '/settings/seguranca', icon: Lock },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { isAdmin } = usePermissions();

    // Filtra itens conforme papel do usuário
    const navItems = allNavItems.filter((item) => !item.adminOnly || isAdmin);

    return (
        <div className="px-4 py-6">
            <h1 className="text-xl font-semibold text-[#1E2D30]">Configurações</h1>
            <p className="mt-1 text-sm text-[#6B7370]">Gerencie seu perfil e configurações da conta</p>

            <div className="mt-6 flex flex-col gap-6 lg:flex-row">
                {/* Navegação lateral (desktop) / topo (mobile) */}
                <aside className="w-full lg:w-48 shrink-0">
                    <nav className="flex gap-1 overflow-x-auto lg:flex-col" aria-label="Configurações">
                        {navItems.map((item) => {
                            const active = isCurrentOrParentUrl(item.href);
                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={cn(
                                        'flex items-center gap-2 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                                        active
                                            ? 'bg-[#E8F4F6] text-[#0A4F5C]'
                                            : 'text-[#6B7370] hover:bg-[#F7F8F7] hover:text-[#1E2D30]',
                                    )}
                                >
                                    <item.icon className="h-4 w-4 shrink-0" />
                                    {item.title}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>

                {/* Conteúdo */}
                <div className="flex-1 min-w-0">
                    <div className="max-w-2xl space-y-6">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
}
