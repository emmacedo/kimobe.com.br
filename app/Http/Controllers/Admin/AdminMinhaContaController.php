<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\RecoveryCode;

class AdminMinhaContaController extends Controller
{
    public function __construct(
        private TwoFactorAuthenticationProvider $provider,
    ) {}

    private function admin(): AdminUser
    {
        return Auth::guard('admin')->user();
    }

    public function show(): Response
    {
        $admin = $this->admin();

        return Inertia::render('admin/minha-conta', [
            'admin' => $admin,
            'twoFactorEnabled' => (bool) $admin->hasEnabledTwoFactorAuthentication(),
            'aviso2fa' => session('aviso_2fa'),
        ]);
    }

    public function enableTwoFactor(Request $request): RedirectResponse
    {
        $admin = $this->admin();

        // Gerar novo secret se não existir ou se o setup anterior não foi confirmado
        if (! $admin->two_factor_secret || ! $admin->two_factor_confirmed_at) {
            $admin->forceFill([
                'two_factor_secret' => encrypt($this->provider->generateSecretKey()),
                'two_factor_recovery_codes' => encrypt(json_encode(
                    Collection::times(8, fn () => RecoveryCode::generate())->all(),
                )),
                'two_factor_confirmed_at' => null,
            ])->save();
        }

        return back();
    }

    public function confirmTwoFactor(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $admin = $this->admin();

        $valid = $this->provider->verify(
            decrypt($admin->two_factor_secret),
            $request->input('code'),
        );

        if (! $valid) {
            return back()->withErrors(['code' => 'Código inválido. Tente novamente.']);
        }

        $admin->forceFill([
            'two_factor_confirmed_at' => now(),
        ])->save();

        return back()->with('success', 'Autenticação em dois fatores ativada com sucesso.');
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ], [
            'password.required' => 'Informe sua senha para desativar o 2FA.',
        ]);

        $admin = $this->admin();

        if (! Hash::check($request->input('password'), $admin->getAuthPassword())) {
            return back()->withErrors(['password' => 'Senha incorreta.']);
        }

        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with('success', '2FA desativado.');
    }

    public function qrCode(): JsonResponse
    {
        $admin = $this->admin();

        if (! $admin->two_factor_secret || $admin->two_factor_confirmed_at) {
            abort(404);
        }

        return response()->json([
            'svg' => $admin->twoFactorQrCodeSvg(),
            'url' => $admin->twoFactorQrCodeUrl(),
        ]);
    }

    public function secretKey(): JsonResponse
    {
        $admin = $this->admin();

        if (! $admin->two_factor_secret || $admin->two_factor_confirmed_at) {
            abort(404);
        }

        return response()->json([
            'secretKey' => decrypt($admin->two_factor_secret),
        ]);
    }

    public function recoveryCodes(): JsonResponse
    {
        $admin = $this->admin();

        if (! $admin->two_factor_recovery_codes) {
            abort(404);
        }

        return response()->json($admin->recoveryCodes());
    }

    public function regenerateRecoveryCodes(): JsonResponse
    {
        $admin = $this->admin();

        $admin->forceFill([
            'two_factor_recovery_codes' => encrypt(json_encode(
                Collection::times(8, fn () => RecoveryCode::generate())->all(),
            )),
        ])->save();

        return response()->json($admin->recoveryCodes());
    }
}
