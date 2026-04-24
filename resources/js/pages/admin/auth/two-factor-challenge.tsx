import { Head, router, usePage } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';

const OTP_LENGTH = 6;

export default function AdminTwoFactorChallenge() {
    const { errors } = usePage().props as any;
    const [showRecovery, setShowRecovery] = useState(false);
    const [code, setCode] = useState('');
    const [recoveryCode, setRecoveryCode] = useState('');
    const [processing, setProcessing] = useState(false);

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setProcessing(true);

        const data = showRecovery ? { recovery_code: recoveryCode } : { code };

        router.post('/admin/two-factor-challenge', data, {
            onFinish: () => setProcessing(false),
        });
    }

    function toggleMode() {
        setShowRecovery(!showRecovery);
        setCode('');
        setRecoveryCode('');
    }

    return (
        <AuthLayout
            titulo="Autenticação em dois fatores"
            subtitulo={showRecovery ? 'Informe um código de recuperação' : 'Informe o código do seu aplicativo autenticador'}
            variant="admin"
            showRegistro={false}
            showCreditos={false}
        >
            <Head title="Admin — Verificação 2FA" />

            <form onSubmit={handleSubmit} className="space-y-4">
                {showRecovery ? (
                    <div>
                        <Input
                            type="text"
                            value={recoveryCode}
                            onChange={(e) => setRecoveryCode(e.target.value)}
                            placeholder="Código de recuperação"
                            autoFocus
                            className="bg-white border-[#D8DCDA]"
                        />
                        <InputError message={errors?.recovery_code} />
                    </div>
                ) : (
                    <div className="flex flex-col items-center space-y-3">
                        <InputOTP
                            maxLength={OTP_LENGTH}
                            value={code}
                            onChange={setCode}
                            disabled={processing}
                            pattern={REGEXP_ONLY_DIGITS}
                        >
                            <InputOTPGroup>
                                {Array.from({ length: OTP_LENGTH }, (_, i) => (
                                    <InputOTPSlot key={i} index={i} />
                                ))}
                            </InputOTPGroup>
                        </InputOTP>
                        <InputError message={errors?.code} />
                    </div>
                )}

                <Button
                    type="submit"
                    disabled={processing || (!showRecovery && code.length < OTP_LENGTH) || (showRecovery && !recoveryCode)}
                    className="w-full bg-[#C9A84C] text-[#2E2410] hover:bg-[#B8993F]"
                >
                    {processing && <Spinner />}
                    Verificar
                </Button>

                <div className="text-center text-sm text-[#6B7370]">
                    <span>ou </span>
                    <button
                        type="button"
                        className="cursor-pointer text-[#1E2D30] underline decoration-[#D8DCDA] underline-offset-4 transition-colors hover:decoration-current"
                        onClick={toggleMode}
                    >
                        {showRecovery ? 'usar código do autenticador' : 'usar código de recuperação'}
                    </button>
                </div>
            </form>
        </AuthLayout>
    );
}
