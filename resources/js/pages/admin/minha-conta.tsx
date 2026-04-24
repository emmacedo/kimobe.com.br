import { Head, router, usePage } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { Check, Copy, RefreshCw, ShieldCheck, ShieldOff } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { InputOTP, InputOTPGroup, InputOTPSlot } from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useClipboard } from '@/hooks/use-clipboard';
import { useAppearance } from '@/hooks/use-appearance';

const OTP_LENGTH = 6;

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

function apiFetch(url: string, method = 'GET') {
    return fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Accept': 'application/json',
            'X-XSRF-TOKEN': getCsrfToken(),
        },
    });
}

type Props = {
    admin: { nome: string; email: string };
    twoFactorEnabled: boolean;
    aviso2fa?: string | null;
};

export default function MinhaConta({ admin, twoFactorEnabled, aviso2fa }: Props) {
    const { errors } = usePage().props as any;
    const [setupStep, setSetupStep] = useState<'idle' | 'qr' | 'confirm' | 'done'>('idle');
    const [qrSvg, setQrSvg] = useState<string | null>(null);
    const [secretKey, setSecretKey] = useState<string | null>(null);
    const [recoveryCodes, setRecoveryCodes] = useState<string[]>([]);
    const [code, setCode] = useState('');
    const [processing, setProcessing] = useState(false);
    const [copiedText, copy] = useClipboard();
    const [showDisable, setShowDisable] = useState(false);
    const [disablePassword, setDisablePassword] = useState('');
    const { resolvedAppearance } = useAppearance();

    const fetchSetupData = useCallback(async () => {
        try {
            const [qrRes, keyRes] = await Promise.all([
                apiFetch('/admin/minha-conta/two-factor/qr-code'),
                apiFetch('/admin/minha-conta/two-factor/secret-key'),
            ]);
            const qrData = await qrRes.json();
            const keyData = await keyRes.json();
            setQrSvg(qrData.svg);
            setSecretKey(keyData.secretKey);
        } catch {
            // silenciar erro — dados ficarão null e usuário pode retentar
        }
    }, []);

    const fetchRecoveryCodes = useCallback(async () => {
        try {
            const res = await apiFetch('/admin/minha-conta/two-factor/recovery-codes');
            const codes = await res.json();
            setRecoveryCodes(codes);
        } catch {
            // silenciar
        }
    }, []);

    function handleEnable() {
        setProcessing(true);
        router.post('/admin/minha-conta/two-factor', {}, {
            onSuccess: () => {
                setSetupStep('qr');
                fetchSetupData();
            },
            onFinish: () => setProcessing(false),
        });
    }

    function handleConfirm(e: React.FormEvent) {
        e.preventDefault();
        setProcessing(true);
        router.post('/admin/minha-conta/two-factor/confirm', { code }, {
            onSuccess: () => {
                setSetupStep('done');
                setCode('');
                fetchRecoveryCodes();
            },
            onError: () => setCode(''),
            onFinish: () => setProcessing(false),
        });
    }

    function handleDisable(e: React.FormEvent) {
        e.preventDefault();
        setProcessing(true);
        router.delete('/admin/minha-conta/two-factor', {
            data: { password: disablePassword },
            onSuccess: () => {
                setSetupStep('idle');
                setQrSvg(null);
                setSecretKey(null);
                setRecoveryCodes([]);
                setShowDisable(false);
                setDisablePassword('');
            },
            onFinish: () => setProcessing(false),
        });
    }

    function handleRegenerate() {
        setProcessing(true);
        apiFetch('/admin/minha-conta/two-factor/recovery-codes', 'POST')
            .then(res => res.json())
            .then(codes => setRecoveryCodes(codes))
            .finally(() => setProcessing(false));
    }

    // Ao carregar a página já com 2FA ativo, buscar recovery codes
    useEffect(() => {
        if (twoFactorEnabled && setupStep === 'idle') {
            fetchRecoveryCodes();
        }
    }, [twoFactorEnabled, setupStep, fetchRecoveryCodes]);

    const CopyIcon = copiedText === secretKey ? Check : Copy;

    return (
        <>
            <Head title="Admin — Minha Conta" />

            <div className="mb-6">
                <h1 className="text-xl font-semibold text-[#1E2D30]">Minha Conta</h1>
                <p className="mt-1 text-sm text-[#6B7370]">{admin.nome} — {admin.email}</p>
            </div>

            {/* Aviso obrigatório */}
            {aviso2fa && !twoFactorEnabled && setupStep === 'idle' && (
                <div className="mb-4 rounded-lg border border-[#C9A84C]/30 bg-[#C9A84C]/10 p-4 text-sm text-[#8B6914]">
                    <strong>Atenção:</strong> {aviso2fa}
                </div>
            )}

            {/* Card 2FA */}
            <div className="rounded-[10px] border border-[#D8DCDA] bg-white p-5">
                <h2 className="mb-4 text-sm font-medium text-[#1E2D30]">Autenticação em dois fatores (2FA)</h2>

                {/* Estado: 2FA ativo */}
                {twoFactorEnabled && setupStep !== 'done' && (
                    <div className="space-y-4">
                        <div className="flex items-center gap-2">
                            <ShieldCheck className="h-5 w-5 text-[#1B6B3A]" />
                            <span className="text-sm font-medium text-[#1B6B3A]">2FA ativado</span>
                        </div>
                        <p className="text-sm text-[#6B7370]">
                            Ao fazer login, será solicitado um código seguro do seu aplicativo autenticador.
                        </p>

                        {!showDisable ? (
                            <Button
                                variant="outline"
                                onClick={() => setShowDisable(true)}
                                className="border-[#A83232] text-[#A83232] hover:bg-[#FDECEC]"
                            >
                                <ShieldOff className="mr-1 h-4 w-4" />
                                Desativar 2FA
                            </Button>
                        ) : (
                            <form onSubmit={handleDisable} className="space-y-3 rounded-lg border border-[#A83232]/20 bg-[#FDECEC]/30 p-4">
                                <p className="text-sm text-[#6B7370]">Confirme sua senha para desativar o 2FA. Você será obrigado a configurá-lo novamente.</p>
                                <div>
                                    <Label htmlFor="disable-password">Senha</Label>
                                    <PasswordInput
                                        id="disable-password"
                                        value={disablePassword}
                                        onChange={(e) => setDisablePassword(e.target.value)}
                                        placeholder="Sua senha"
                                        className="bg-white border-[#D8DCDA]"
                                        autoFocus
                                    />
                                    <InputError message={errors?.password} />
                                </div>
                                <div className="flex gap-2">
                                    <Button type="button" variant="outline" onClick={() => { setShowDisable(false); setDisablePassword(''); }} size="sm">
                                        Cancelar
                                    </Button>
                                    <Button type="submit" disabled={processing || !disablePassword} variant="outline" className="border-[#A83232] text-[#A83232] hover:bg-[#FDECEC]" size="sm">
                                        {processing && <Spinner />}
                                        Confirmar desativação
                                    </Button>
                                </div>
                            </form>
                        )}

                        {/* Códigos de recuperação */}
                        <RecoveryCodesSection
                            codes={recoveryCodes}
                            onRegenerate={handleRegenerate}
                            processing={processing}
                        />
                    </div>
                )}

                {/* Estado: Setup — passo 1 (idle) */}
                {!twoFactorEnabled && setupStep === 'idle' && (
                    <div className="space-y-4">
                        <p className="text-sm text-[#6B7370]">
                            Adicione uma camada extra de segurança à sua conta. O código será gerado por um aplicativo autenticador (Google Authenticator, Authy, etc.).
                        </p>
                        <Button
                            onClick={handleEnable}
                            disabled={processing}
                            className="bg-[#0A4F5C] text-white hover:bg-[#073B45]"
                        >
                            {processing && <Spinner />}
                            <ShieldCheck className="mr-1 h-4 w-4" />
                            Configurar 2FA
                        </Button>
                    </div>
                )}

                {/* Estado: Setup — passo 2 (QR code) */}
                {setupStep === 'qr' && (
                    <div className="space-y-5">
                        <p className="text-sm text-[#6B7370]">
                            Escaneie o QR code com o seu aplicativo autenticador ou insira a chave manualmente.
                        </p>

                        <div className="flex justify-center">
                            <div className="overflow-hidden rounded-lg border border-[#D8DCDA] bg-white p-3">
                                {qrSvg ? (
                                    <div
                                        className="aspect-square w-48 [&_svg]:size-full"
                                        dangerouslySetInnerHTML={{ __html: qrSvg }}
                                        style={{ filter: resolvedAppearance === 'dark' ? 'invert(1) brightness(1.5)' : undefined }}
                                    />
                                ) : (
                                    <div className="flex aspect-square w-48 items-center justify-center">
                                        <Spinner />
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Chave manual */}
                        {secretKey && (
                            <>
                                <div className="relative flex items-center justify-center">
                                    <div className="absolute inset-0 top-1/2 h-px w-full bg-[#D8DCDA]" />
                                    <span className="relative bg-white px-2 text-xs text-[#8A918E]">ou insira a chave manualmente</span>
                                </div>
                                <div className="flex overflow-hidden rounded-lg border border-[#D8DCDA]">
                                    <input
                                        type="text"
                                        readOnly
                                        value={secretKey}
                                        className="w-full bg-[#F7F8F7] px-3 py-2 font-mono text-sm text-[#1E2D30] outline-none"
                                    />
                                    <button
                                        onClick={() => copy(secretKey)}
                                        className="border-l border-[#D8DCDA] px-3 text-[#6B7370] hover:bg-[#EEF0EF]"
                                    >
                                        <CopyIcon className="h-4 w-4" />
                                    </button>
                                </div>
                            </>
                        )}

                        <Button
                            onClick={() => setSetupStep('confirm')}
                            className="w-full bg-[#0A4F5C] text-white hover:bg-[#073B45]"
                        >
                            Continuar
                        </Button>
                    </div>
                )}

                {/* Estado: Setup — passo 3 (confirmar código) */}
                {setupStep === 'confirm' && (
                    <div className="space-y-5">
                        <p className="text-sm text-[#6B7370]">
                            Insira o código de 6 dígitos exibido no seu aplicativo autenticador para confirmar a ativação.
                        </p>

                        <form onSubmit={handleConfirm} className="space-y-4">
                            <div className="flex flex-col items-center space-y-3">
                                <InputOTP
                                    maxLength={OTP_LENGTH}
                                    value={code}
                                    onChange={setCode}
                                    disabled={processing}
                                    pattern={REGEXP_ONLY_DIGITS}
                                    autoFocus
                                >
                                    <InputOTPGroup>
                                        {Array.from({ length: OTP_LENGTH }, (_, i) => (
                                            <InputOTPSlot key={i} index={i} />
                                        ))}
                                    </InputOTPGroup>
                                </InputOTP>
                                <InputError message={errors?.code} />
                            </div>

                            <div className="flex gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setSetupStep('qr')}
                                    disabled={processing}
                                    className="flex-1"
                                >
                                    Voltar
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing || code.length < OTP_LENGTH}
                                    className="flex-1 bg-[#0A4F5C] text-white hover:bg-[#073B45]"
                                >
                                    {processing && <Spinner />}
                                    Confirmar
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Estado: Setup concluído — exibir recovery codes */}
                {setupStep === 'done' && (
                    <div className="space-y-4">
                        <div className="flex items-center gap-2">
                            <ShieldCheck className="h-5 w-5 text-[#1B6B3A]" />
                            <span className="text-sm font-medium text-[#1B6B3A]">2FA ativado com sucesso!</span>
                        </div>

                        <div className="rounded-lg border border-[#C9A84C]/30 bg-[#C9A84C]/10 p-4 text-sm text-[#8B6914]">
                            <strong>Importante:</strong> Guarde os códigos de recuperação abaixo em um local seguro. Cada código pode ser usado uma única vez caso você perca o acesso ao seu autenticador.
                        </div>

                        <RecoveryCodesSection codes={recoveryCodes} onRegenerate={handleRegenerate} processing={processing} alwaysVisible />

                        <Button
                            onClick={() => router.visit('/admin/dashboard')}
                            className="w-full bg-[#0A4F5C] text-white hover:bg-[#073B45]"
                        >
                            Acessar o painel
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

function RecoveryCodesSection({
    codes,
    onRegenerate,
    processing,
    alwaysVisible = false,
}: {
    codes: string[];
    onRegenerate: () => void;
    processing: boolean;
    alwaysVisible?: boolean;
}) {
    const [visible, setVisible] = useState(alwaysVisible);

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-medium text-[#1E2D30]">Códigos de recuperação</h3>
                {!alwaysVisible && (
                    <button
                        type="button"
                        onClick={() => setVisible(!visible)}
                        className="text-xs text-[#0A4F5C] underline underline-offset-2 hover:no-underline"
                    >
                        {visible ? 'Ocultar' : 'Exibir'}
                    </button>
                )}
            </div>

            {visible && (
                <>
                    {codes.length > 0 ? (
                        <div className="grid grid-cols-2 gap-1 rounded-lg bg-[#F7F8F7] p-4 font-mono text-sm text-[#1E2D30]">
                            {codes.map((c, i) => (
                                <div key={i} className="select-text">{c}</div>
                            ))}
                        </div>
                    ) : (
                        <div className="space-y-2 rounded-lg bg-[#F7F8F7] p-4">
                            {Array.from({ length: 8 }, (_, i) => (
                                <div key={i} className="h-4 animate-pulse rounded bg-[#D8DCDA]" />
                            ))}
                        </div>
                    )}

                    <p className="text-xs text-[#8A918E]">
                        Cada código pode ser usado uma única vez. Clique em "Regenerar" para obter novos códigos (os anteriores serão invalidados).
                    </p>

                    <Button
                        variant="outline"
                        onClick={onRegenerate}
                        disabled={processing}
                        size="sm"
                    >
                        <RefreshCw className="mr-1 h-3 w-3" />
                        Regenerar códigos
                    </Button>
                </>
            )}
        </div>
    );
}
