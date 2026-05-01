import { Link, router, usePage } from '@inertiajs/react';
import { Banknote, Building, Building2, CreditCard, FileText, Home, Mail, Menu, Receipt, UserCircle, Users, Wallet, X } from 'lucide-react';
import { useMemo, useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { usePermissions } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';

type NavLink = {
    title: string;
    href: string;
    icon: React.ElementType;
};

/**
 * Monta os links de navegação baseado nos papéis do usuário.
 */
function getNavLinks(papeis: string[]): NavLink[] {
    const isAdmin = papeis.includes('admin');
    const isProprietario = papeis.includes('proprietario');
    const isInquilino = papeis.includes('inquilino');

    const links: NavLink[] = [
        { title: 'Dashboard', href: '/dashboard', icon: Home },
    ];

    if (isAdmin) {
        links.push(
            { title: 'Imóveis', href: '/imoveis', icon: Building2 },
            { title: 'Proprietários', href: '/proprietarios', icon: UserCircle },
            { title: 'Administradoras', href: '/administradoras', icon: Building },
            { title: 'Contratos', href: '/contratos', icon: FileText },
            { title: 'Cobranças', href: '/financeiro/cobrancas', icon: Receipt },
            { title: 'Repasses', href: '/financeiro/repasses', icon: Wallet },
            { title: 'Emails', href: '/emails', icon: Mail },
        );
        // Admin que também é proprietário vê link de dados bancários
        if (isProprietario) {
            links.push({ title: 'Dados Bancários', href: '/dados-bancarios', icon: Banknote });
        }
    } else {
        if (isProprietario) {
            links.push(
                { title: 'Meus Imóveis', href: '/imoveis', icon: Building2 },
                { title: 'Meus Repasses', href: '/financeiro/repasses', icon: Wallet },
                { title: 'Cobranças', href: '/financeiro/cobrancas', icon: Receipt },
                { title: 'Dados Bancários', href: '/dados-bancarios', icon: Banknote },
            );
        }
        if (isInquilino) {
            links.push(
                { title: 'Minhas Cobranças', href: '/financeiro/cobrancas', icon: Receipt },
                { title: 'Meus Contratos', href: '/contratos', icon: FileText },
            );
        }
    }

    return links;
}

export function KimobeNavbar() {
    const { auth, current_tenant, has_multiple_tenants } = usePage().props;
    const { papeis } = usePermissions();
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const getInitials = useInitials();
    const [mobileOpen, setMobileOpen] = useState(false);
    const navLinks = useMemo(() => getNavLinks(papeis), [papeis]);

    function handleLogout() {
        router.post('/logout');
    }

    function handleTrocarContexto() {
        router.post('/trocar-contexto');
    }

    return (
        <nav className="sticky top-0 z-50 bg-[#0A4F5C]">
            <div className="mx-auto flex h-14 items-center justify-between px-4 md:px-6">
                {/* Logo */}
                <Link href="/dashboard">
                    <img src="/logo-kimobe.webp" alt="Kimobe" className="h-8" />
                </Link>

                {/* Links desktop */}
                <div className="hidden items-center gap-1 md:flex">
                    {navLinks.map((link) => {
                        const isActive = isCurrentOrParentUrl(link.href);
                        return (
                            <Link
                                key={link.href}
                                href={link.href}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm transition-colors',
                                    isActive
                                        ? 'font-medium text-white'
                                        : 'text-[#8DCAD6] hover:text-white',
                                )}
                            >
                                {link.title}
                            </Link>
                        );
                    })}
                </div>

                {/* Avatar + dropdown desktop */}
                <div className="flex items-center gap-3">
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <button
                                className="flex h-8 w-8 items-center justify-center rounded-full bg-white/15 text-xs font-medium text-white transition-colors hover:bg-white/25 focus:outline-none"
                                aria-label="Menu do usuário"
                            >
                                {getInitials(auth.user?.name ?? '')}
                            </button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" className="w-56">
                            <DropdownMenuLabel className="font-normal">
                                <div className="flex flex-col space-y-1">
                                    <p className="text-sm font-medium">{auth.user?.name}</p>
                                    {current_tenant && (
                                        <p className="text-xs text-muted-foreground">{current_tenant.nome}</p>
                                    )}
                                </div>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <Link href="/settings/perfil">Perfil</Link>
                            </DropdownMenuItem>
                            {has_multiple_tenants && (
                                <DropdownMenuItem onClick={handleTrocarContexto}>
                                    Trocar ambiente
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuSeparator />
                            <DropdownMenuItem onClick={handleLogout}>
                                Sair
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    {/* Hamburger mobile */}
                    <button
                        className="flex h-8 w-8 items-center justify-center rounded-md text-white md:hidden"
                        onClick={() => setMobileOpen(!mobileOpen)}
                        aria-label="Abrir menu"
                    >
                        {mobileOpen ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
                    </button>
                </div>
            </div>

            {/* Mobile nav */}
            {mobileOpen && (
                <div className="border-t border-white/10 bg-[#0A4F5C] px-4 pb-4 pt-2 md:hidden">
                    {navLinks.map((link) => {
                        const isActive = isCurrentOrParentUrl(link.href);
                        return (
                            <Link
                                key={link.href}
                                href={link.href}
                                onClick={() => setMobileOpen(false)}
                                className={cn(
                                    'flex items-center gap-3 rounded-md px-3 py-2.5 text-sm',
                                    isActive
                                        ? 'font-medium text-white'
                                        : 'text-[#8DCAD6]',
                                )}
                            >
                                <link.icon className="h-4 w-4" />
                                {link.title}
                            </Link>
                        );
                    })}
                </div>
            )}
        </nav>
    );
}
