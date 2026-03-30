import { Head, router, usePage } from '@inertiajs/react';
import { Lock, Mail } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';

export default function AdminLogin() {
    const { errors } = usePage().props as any;
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [remember, setRemember] = useState(false);
    const [processing, setProcessing] = useState(false);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setProcessing(true);
        router.post('/admin/login', { email, password, remember }, {
            onFinish: () => setProcessing(false),
        });
    }

    return (
        <AuthLayout
            titulo="Painel administrativo"
            subtitulo="Acesse o painel de gestão do Kimobe"
            variant="admin"
            showRegistro={false}
            showCreditos={false}
        >
            <Head title="Admin — Login" />

            <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                    <Label htmlFor="email">Email</Label>
                    <div className="relative">
                        <Mail className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A918E]" />
                        <Input
                            id="email"
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            placeholder="admin@kimobe.com.br"
                            className="pl-9 bg-white border-[#D8DCDA]"
                            autoFocus
                        />
                    </div>
                    <InputError message={errors?.email} />
                </div>

                <div>
                    <Label htmlFor="password">Senha</Label>
                    <div className="relative">
                        <Lock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#8A918E]" />
                        <Input
                            id="password"
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            placeholder="Sua senha"
                            className="pl-9 bg-white border-[#D8DCDA]"
                        />
                    </div>
                    <InputError message={errors?.password} />
                </div>

                <div className="flex items-center space-x-2">
                    <Checkbox
                        id="remember"
                        checked={remember}
                        onCheckedChange={(c) => setRemember(!!c)}
                    />
                    <Label htmlFor="remember" className="text-sm text-[#6B7370]">Lembrar-me</Label>
                </div>

                <Button
                    type="submit"
                    disabled={processing}
                    className="w-full bg-[#C9A84C] text-[#2E2410] hover:bg-[#B8993F]"
                >
                    {processing && <Spinner />}
                    Entrar
                </Button>
            </form>
        </AuthLayout>
    );
}
