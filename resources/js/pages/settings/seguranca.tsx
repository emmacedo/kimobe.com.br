import { Form, Head, router, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TwoFactorRecoveryCodes from '@/components/two-factor-recovery-codes';
import TwoFactorSetupModal from '@/components/two-factor-setup-modal';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTwoFactorAuth } from '@/hooks/use-two-factor-auth';
import { disable, enable } from '@/routes/two-factor';

type Props = {
    canManageTwoFactor?: boolean;
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

export default function SegurancaPage({
    canManageTwoFactor = false,
    requiresConfirmation = false,
    twoFactorEnabled = false,
}: Props) {
    const { flash } = usePage().props as any;
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    // Estado do formulário de senha
    const [currentPassword, setCurrentPassword] = useState('');
    const [password, setPassword] = useState('');
    const [passwordConfirmation, setPasswordConfirmation] = useState('');
    const [savingSenha, setSavingSenha] = useState(false);
    const [senhaErrors, setSenhaErrors] = useState<Record<string, string>>({});

    // 2FA
    const {
        qrCodeSvg, hasSetupData, manualSetupKey, clearSetupData,
        clearTwoFactorAuthData, fetchSetupData, recoveryCodesList,
        fetchRecoveryCodes, errors: twoFactorErrors,
    } = useTwoFactorAuth();
    const [showSetupModal, setShowSetupModal] = useState(false);
    const prevTwoFactorEnabled = useRef(twoFactorEnabled);

    useEffect(() => { if (flash?.success) toast.success(flash.success); }, [flash?.success]);

    useEffect(() => {
        if (prevTwoFactorEnabled.current && !twoFactorEnabled) {
            clearTwoFactorAuthData();
        }
        prevTwoFactorEnabled.current = twoFactorEnabled;
    }, [twoFactorEnabled, clearTwoFactorAuthData]);

    // Força da senha
    const forcaSenha = password.length === 0 ? 0 : password.length < 8 ? 1 : /(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(password) ? 3 : 2;
    const forcaLabels = ['', 'Fraca', 'Média', 'Forte'];
    const forcaCores = ['', '#A83232', '#C9A84C', '#1B6B3A'];

    function handleAlterarSenha(e: React.FormEvent) {
        e.preventDefault();
        setSavingSenha(true);
        setSenhaErrors({});
        router.put('/settings/seguranca/senha', {
            current_password: currentPassword,
            password,
            password_confirmation: passwordConfirmation,
        }, {
            onSuccess: () => {
                setCurrentPassword('');
                setPassword('');
                setPasswordConfirmation('');
            },
            onError: (errs) => {
                setSenhaErrors(errs);
                if (errs.current_password) currentPasswordInput.current?.focus();
                else if (errs.password) passwordInput.current?.focus();
            },
            onFinish: () => setSavingSenha(false),
        });
    }

    return (
        <>
            <Head title="Segurança" />

            {/* Card alterar senha */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Alterar senha</h2>
                <form onSubmit={handleAlterarSenha} className="space-y-4">
                    <div>
                        <Label htmlFor="current_password">Senha atual</Label>
                        <PasswordInput
                            id="current_password"
                            ref={currentPasswordInput}
                            name="current_password"
                            value={currentPassword}
                            onChange={(e) => setCurrentPassword(e.target.value)}
                            autoComplete="current-password"
                            placeholder="Sua senha atual"
                            className="bg-white border-[#D8DCDA]"
                        />
                        <InputError message={senhaErrors.current_password} />
                    </div>
                    <div>
                        <Label htmlFor="password">Nova senha</Label>
                        <PasswordInput
                            id="password"
                            ref={passwordInput}
                            name="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            autoComplete="new-password"
                            placeholder="Mínimo 8 caracteres"
                            className="bg-white border-[#D8DCDA]"
                        />
                        {password.length > 0 && (
                            <div className="mt-1.5 flex items-center gap-2">
                                <div className="h-1.5 flex-1 rounded-full bg-[#EEF0EF]">
                                    <div className="h-full rounded-full transition-all" style={{ width: `${(forcaSenha / 3) * 100}%`, backgroundColor: forcaCores[forcaSenha] }} />
                                </div>
                                <span className="text-[10px]" style={{ color: forcaCores[forcaSenha] }}>{forcaLabels[forcaSenha]}</span>
                            </div>
                        )}
                        <InputError message={senhaErrors.password} />
                    </div>
                    <div>
                        <Label htmlFor="password_confirmation">Confirmar nova senha</Label>
                        <PasswordInput
                            id="password_confirmation"
                            name="password_confirmation"
                            value={passwordConfirmation}
                            onChange={(e) => setPasswordConfirmation(e.target.value)}
                            autoComplete="new-password"
                            placeholder="Repita a nova senha"
                            className="bg-white border-[#D8DCDA]"
                        />
                        <InputError message={senhaErrors.password_confirmation} />
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={savingSenha || !currentPassword || !password} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                            {savingSenha && <Spinner />}Alterar senha
                        </Button>
                    </div>
                </form>
            </div>

            {/* Card 2FA */}
            {canManageTwoFactor && (
                <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                    <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Autenticação em dois fatores (2FA)</h2>

                    {twoFactorEnabled ? (
                        <div className="space-y-4">
                            <div className="flex items-center gap-2">
                                <ShieldCheck className="h-5 w-5 text-[#1B6B3A]" />
                                <span className="text-sm font-medium text-[#1B6B3A]">2FA ativado</span>
                            </div>
                            <p className="text-sm text-[#6B7370]">
                                Ao fazer login, será solicitado um código seguro do seu aplicativo autenticador.
                            </p>

                            <Form {...disable.form()}>
                                {({ processing }) => (
                                    <Button variant="outline" type="submit" disabled={processing} className="border-[#A83232] text-[#A83232] hover:bg-[#FDECEC]">
                                        {processing && <Spinner />}Desativar 2FA
                                    </Button>
                                )}
                            </Form>

                            <TwoFactorRecoveryCodes
                                recoveryCodesList={recoveryCodesList}
                                fetchRecoveryCodes={fetchRecoveryCodes}
                                errors={twoFactorErrors}
                            />
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <p className="text-sm text-[#6B7370]">
                                Adicione uma camada extra de segurança à sua conta. O código será gerado por um aplicativo autenticador (Google Authenticator, Authy, etc.).
                            </p>

                            {hasSetupData ? (
                                <Button onClick={() => setShowSetupModal(true)} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                                    <ShieldCheck className="mr-1 h-4 w-4" />Continuar configuração
                                </Button>
                            ) : (
                                <Form {...enable.form()} onSuccess={() => setShowSetupModal(true)}>
                                    {({ processing }) => (
                                        <Button type="submit" disabled={processing} className="bg-[#0A4F5C] text-white hover:bg-[#073B45]">
                                            {processing && <Spinner />}Ativar 2FA
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </div>
                    )}

                    <TwoFactorSetupModal
                        isOpen={showSetupModal}
                        onClose={() => setShowSetupModal(false)}
                        requiresConfirmation={requiresConfirmation}
                        twoFactorEnabled={twoFactorEnabled}
                        qrCodeSvg={qrCodeSvg}
                        manualSetupKey={manualSetupKey}
                        clearSetupData={clearSetupData}
                        fetchSetupData={fetchSetupData}
                        errors={twoFactorErrors}
                    />
                </div>
            )}
        </>
    );
}
