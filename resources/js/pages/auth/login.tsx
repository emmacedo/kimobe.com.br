import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = { status?: string; canResetPassword: boolean; canRegister: boolean };

export default function Login({ status, canResetPassword, canRegister }: Props) {
    return (
        <AuthLayout titulo="Bem-vindo de volta!" subtitulo="Acesse sua conta para continuar">
            <Head title="Entrar" />
            <Form {...store.form()} resetOnSuccess={['password']} className="flex flex-col gap-5">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-1.5">
                            <Label htmlFor="email">Email</Label>
                            <Input id="email" type="email" name="email" required autoFocus autoComplete="email" placeholder="seu@email.com"
                                className="rounded-lg border-[#D8DCDA] bg-[#F0F2F1] focus:border-[#0A4F5C] focus:bg-white" />
                            <InputError message={errors.email} />
                        </div>
                        <div className="grid gap-1.5">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="password">Senha</Label>
                                {canResetPassword && <Link href={request()} className="text-xs text-[#C9A84C] hover:underline">Esqueceu a senha?</Link>}
                            </div>
                            <PasswordInput id="password" name="password" required autoComplete="current-password" placeholder="Sua senha" />
                            <InputError message={errors.password} />
                        </div>
                        <div className="flex items-center space-x-2">
                            <Checkbox id="remember" name="remember" />
                            <Label htmlFor="remember" className="text-sm text-[#6B7370]">Lembrar-me</Label>
                        </div>
                        <Button type="submit" disabled={processing} className="w-full rounded-lg bg-[#0A4F5C] py-3 text-white hover:bg-[#073B45]">
                            {processing && <Spinner />}Entrar
                        </Button>
                        {canRegister && (
                            <p className="text-center text-sm text-[#6B7370]">
                                Não tem uma conta?{' '}<Link href="/registro" className="font-medium text-[#C9A84C] hover:underline">Cadastre-se</Link>
                            </p>
                        )}
                    </>
                )}
            </Form>
            {status && <div className="mt-4 text-center text-sm font-medium text-green-600">{status}</div>}
        </AuthLayout>
    );
}
