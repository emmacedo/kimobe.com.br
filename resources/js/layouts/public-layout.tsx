import { Link } from '@inertiajs/react';
import { Menu, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { cn } from '@/lib/utils';

type Props = { children: React.ReactNode };

const navLinks = [
    { title: 'Funcionalidades', href: '/#funcionalidades' },
    { title: 'Planos', href: '/planos' },
    { title: 'FAQ', href: '/faq' },
    { title: 'Contato', href: '/contato' },
];

export default function PublicLayout({ children }: Props) {
    const [scrolled, setScrolled] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => {
        const handleScroll = () => setScrolled(window.scrollY > 50);
        window.addEventListener('scroll', handleScroll, { passive: true });
        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    return (
        <div className="min-h-screen">
            {/* Navbar */}
            <nav className={cn(
                'fixed top-0 left-0 right-0 z-50 transition-all duration-300',
                scrolled ? 'bg-white shadow-sm' : 'bg-transparent',
            )}>
                <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 md:px-6">
                    <Link href="/" className={cn('text-lg font-medium tracking-tight transition-colors', scrolled ? 'text-[#0A4F5C]' : 'text-[#E4CC82]')}>
                        kimobe
                    </Link>

                    {/* Desktop nav */}
                    <div className="hidden items-center gap-6 md:flex">
                        {navLinks.map((link) => (
                            <Link key={link.href} href={link.href}
                                className={cn('text-sm transition-colors', scrolled ? 'text-[#3A4240] hover:text-[#0A4F5C]' : 'text-white/80 hover:text-white')}>
                                {link.title}
                            </Link>
                        ))}
                    </div>

                    <div className="hidden items-center gap-3 md:flex">
                        <Link href="/login" className={cn('rounded-lg border px-4 py-1.5 text-sm font-medium transition-colors',
                            scrolled ? 'border-[#0A4F5C] text-[#0A4F5C] hover:bg-[#0A4F5C] hover:text-white' : 'border-white/50 text-white hover:bg-white/10')}>
                            Entrar
                        </Link>
                        <Link href="/registro" className="rounded-lg bg-[#C9A84C] px-4 py-1.5 text-sm font-medium text-[#2E2410] transition-colors hover:bg-[#B8993F]">
                            Criar conta
                        </Link>
                    </div>

                    {/* Mobile hamburger */}
                    <button onClick={() => setMobileOpen(!mobileOpen)} className={cn('md:hidden', scrolled ? 'text-[#1E2D30]' : 'text-white')}>
                        {mobileOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
                    </button>
                </div>

                {/* Mobile menu */}
                {mobileOpen && (
                    <div className="fixed inset-0 top-16 z-40 bg-[#0A4F5C] p-6 md:hidden">
                        <div className="flex flex-col gap-4">
                            {navLinks.map((link) => (
                                <Link key={link.href} href={link.href} onClick={() => setMobileOpen(false)}
                                    className="text-lg text-white">
                                    {link.title}
                                </Link>
                            ))}
                            <div className="mt-4 flex flex-col gap-3">
                                <Link href="/login" className="rounded-lg border border-white/50 py-2.5 text-center text-sm font-medium text-white">Entrar</Link>
                                <Link href="/registro" className="rounded-lg bg-[#C9A84C] py-2.5 text-center text-sm font-medium text-[#2E2410]">Criar conta</Link>
                            </div>
                        </div>
                    </div>
                )}
            </nav>

            {/* Content */}
            {children}

            {/* Footer */}
            <footer className="bg-[#073B45] text-white">
                <div className="mx-auto max-w-7xl px-4 py-12 md:px-6">
                    <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <span className="text-lg font-medium text-[#E4CC82]">kimobe</span>
                            <p className="mt-3 text-sm text-[#8DCAD6]">
                                Gestão completa de aluguéis para imobiliárias e proprietários. Simples, seguro e acessível.
                            </p>
                        </div>
                        <div>
                            <h4 className="mb-3 text-sm font-medium text-white">Produto</h4>
                            <div className="space-y-2">
                                <Link href="/#funcionalidades" className="block text-sm text-[#8DCAD6] hover:text-white">Funcionalidades</Link>
                                <Link href="/planos" className="block text-sm text-[#8DCAD6] hover:text-white">Planos</Link>
                            </div>
                        </div>
                        <div>
                            <h4 className="mb-3 text-sm font-medium text-white">Empresa</h4>
                            <div className="space-y-2">
                                <a href="#" className="block text-sm text-[#8DCAD6] hover:text-white">Termos de uso</a>
                                <a href="#" className="block text-sm text-[#8DCAD6] hover:text-white">Privacidade</a>
                            </div>
                        </div>
                        <div>
                            <h4 className="mb-3 text-sm font-medium text-white">Contato</h4>
                            <div className="space-y-2 text-sm text-[#8DCAD6]">
                                <p>contato@kimobe.com.br</p>
                                <p>(21) 99999-0000</p>
                            </div>
                        </div>
                    </div>
                    <div className="mt-10 border-t border-white/10 pt-6 text-center text-xs text-[#8DCAD6]">
                        © {new Date().getFullYear()} Kimobe. Todos os direitos reservados.
                    </div>
                </div>
            </footer>
        </div>
    );
}
