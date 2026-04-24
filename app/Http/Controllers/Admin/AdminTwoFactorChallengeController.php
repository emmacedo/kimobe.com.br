<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class AdminTwoFactorChallengeController extends Controller
{
    public function __construct(
        private TwoFactorAuthenticationProvider $provider,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has('admin.2fa_pending_id')) {
            return redirect()->route('admin.login');
        }

        return Inertia::render('admin/auth/two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $pendingId = $request->session()->get('admin.2fa_pending_id');

        if (! $pendingId) {
            return redirect()->route('admin.login');
        }

        $admin = AdminUser::find($pendingId);

        if (! $admin) {
            $request->session()->forget(['admin.2fa_pending_id', 'admin.2fa_remember']);

            return redirect()->route('admin.login');
        }

        // Validar código TOTP ou código de recuperação
        if ($code = $request->input('code')) {
            $valid = $this->provider->verify(
                decrypt($admin->two_factor_secret),
                $code,
            );

            if (! $valid) {
                return back()->withErrors(['code' => 'Código inválido.']);
            }
        } elseif ($recoveryCode = $request->input('recovery_code')) {
            $valid = collect($admin->recoveryCodes())
                ->first(fn (string $code) => hash_equals($code, $recoveryCode));

            if (! $valid) {
                return back()->withErrors(['recovery_code' => 'Código de recuperação inválido.']);
            }

            $admin->replaceRecoveryCode($recoveryCode);
        } else {
            return back()->withErrors(['code' => 'Informe o código de autenticação.']);
        }

        $remember = $request->session()->get('admin.2fa_remember', false);

        $request->session()->forget(['admin.2fa_pending_id', 'admin.2fa_remember']);

        Auth::guard('admin')->login($admin, $remember);

        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }
}
