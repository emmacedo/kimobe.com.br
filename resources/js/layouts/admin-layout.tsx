import { Head, Link, router, usePage } from '@inertiajs/react';
import { Building2, CreditCard, FileEdit, FileText, LayoutDashboard, LogOut, Menu, MessageSquare, Receipt, Send, Settings, UserCog, X } from 'lucide-react';
import { useState } from 'react';
import { Toaster } from 'sonner';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';

type NavLink = { title: string; href: string; icon: React.ElementType };

const navLinks: NavLink[] = [
    { title: 'Dashboard', href: '/admin/dashboard', icon: LayoutDashboard },
    { title: 'Assinantes', href: '/admin/assinantes', icon: Building2 },
    { title: 'Templates', href: '/admin/templates', icon: FileEdit },
    { title: 'Páginas', href: '/admin/paginas', icon: FileText },
    { title: 'Emails', href: '/admin/emails', icon: Send },
    { title: 'Mensagens', href: '/admin/mensagens', icon: MessageSquare },
    { title: 'Minha Conta', href: '/admin/minha-conta', icon: UserCog },
    { title: 'Configurações', href: '/admin/configuracoes', icon: Settings },
];

export default function AdminLayout({ children }: { children: React.ReactNode }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const page = usePage().props as any;
    const admin = page.admin ?? page.auth?.user;
    const [mobileOpen, setMobileOpen] = useState(false);

    function handleLogout() {
        router.post('/admin/logout');
    }

    const adminNome = admin?.nome ?? admin?.name ?? 'Admin';
    const initials = adminNome.split(' ').map((n: string) => n[0]).join('').slice(0, 2).toUpperCase();

    return (
        <div className="flex min-h-screen">
            <Head>
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            {/* Sidebar desktop */}
            <aside className="hidden w-60 shrink-0 flex-col bg-[#073B45] lg:flex">
                <SidebarContent
                    navLinks={navLinks}
                    isCurrentOrParentUrl={isCurrentOrParentUrl}
                    adminNome={adminNome}
                    initials={initials}
                    onLogout={handleLogout}
                />
            </aside>

            {/* Mobile overlay */}
            {mobileOpen && (
                <div className="fixed inset-0 z-50 flex lg:hidden">
                    <div className="absolute inset-0 bg-black/50" onClick={() => setMobileOpen(false)} />
                    <aside className="relative z-10 flex w-60 flex-col bg-[#073B45]">
                        <button onClick={() => setMobileOpen(false)} className="absolute right-3 top-3 text-white/50 hover:text-white">
                            <X className="h-5 w-5" />
                        </button>
                        <SidebarContent
                            navLinks={navLinks}
                            isCurrentOrParentUrl={isCurrentOrParentUrl}
                            adminNome={adminNome}
                            initials={initials}
                            onLogout={handleLogout}
                            onNavClick={() => setMobileOpen(false)}
                        />
                    </aside>
                </div>
            )}

            {/* Main content */}
            <div className="flex flex-1 flex-col bg-[#EEF0EF]">
                {/* Mobile top bar */}
                <div className="flex h-14 items-center border-b border-[#D8DCDA] bg-white px-4 lg:hidden">
                    <button onClick={() => setMobileOpen(true)} className="text-[#1E2D30]">
                        <Menu className="h-5 w-5" />
                    </button>
                    <img src="/logo-kimobe.webp" alt="Kimobe" className="ml-3 h-5" />
                    <span className="ml-2 rounded-full bg-[#C9A84C]/15 px-2 py-0.5 text-[10px] font-medium text-[#C9A84C]">Admin</span>
                </div>

                <main className="mx-auto w-full max-w-7xl flex-1 p-5 md:p-6">
                    {children}
                </main>
            </div>

            <Toaster position="top-right" richColors toastOptions={{ style: { fontFamily: 'inherit' } }} />
        </div>
    );
}

function SidebarContent({
    navLinks,
    isCurrentOrParentUrl,
    adminNome,
    initials,
    onLogout,
    onNavClick,
}: {
    navLinks: NavLink[];
    isCurrentOrParentUrl: (url: string) => boolean;
    adminNome: string;
    initials: string;
    onLogout: () => void;
    onNavClick?: () => void;
}) {
    return (
        <div className="flex h-full flex-col">
            {/* Logo */}
            <div className="px-5 pt-6 pb-2">
                <img src="/logo-kimobe.webp" alt="Kimobe" className="h-8" />
                <div className="mt-1.5">
                    <span className="rounded-full bg-[#C9A84C]/15 px-2.5 py-0.5 text-[10px] font-medium text-[#E4CC82]">
                        Administração
                    </span>
                </div>
            </div>

            {/* Nav */}
            <nav className="mt-4 flex-1 space-y-0.5 px-3">
                {navLinks.map((link) => {
                    const isActive = isCurrentOrParentUrl(link.href);
                    return (
                        <Link
                            key={link.href}
                            href={link.href}
                            onClick={onNavClick}
                            className={cn(
                                'flex items-center gap-3 rounded-md px-3 py-2 text-sm transition-colors',
                                isActive
                                    ? 'border-l-3 border-[#C9A84C] bg-[#0A4F5C] font-medium text-white'
                                    : 'text-[#8DCAD6] hover:bg-[#0A4F5C]/50 hover:text-white',
                            )}
                        >
                            <link.icon className="h-4 w-4" />
                            {link.title}
                        </Link>
                    );
                })}
            </nav>

            {/* Footer */}
            <div className="border-t border-white/10 p-4">
                <div className="flex items-center gap-3">
                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-white/15 text-xs font-medium text-white">
                        {initials}
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="truncate text-sm text-white">{adminNome}</p>
                    </div>
                    <button onClick={onLogout} className="text-white/50 hover:text-white" aria-label="Sair">
                        <LogOut className="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>
    );
}
