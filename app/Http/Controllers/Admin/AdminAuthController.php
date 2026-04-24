<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuthController extends Controller
{
    public function showLogin(): Response|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return Inertia::render('admin/auth/login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ], [
            'email.required' => 'Informe o email.',
            'password.required' => 'Informe a senha.',
        ]);

        if (Auth::guard('admin')->attempt(
            ['email' => $request->email, 'password' => $request->password],
            $request->boolean('remember'),
        )) {
            $admin = Auth::guard('admin')->user();

            // Se 2FA está ativo, redirecionar para o challenge sem manter a sessão autenticada
            if ($admin->hasEnabledTwoFactorAuthentication()) {
                $pendingId = $admin->getKey();
                $remember = $request->boolean('remember');

                Auth::guard('admin')->logout();
                $request->session()->regenerate();

                $request->session()->put('admin.2fa_pending_id', $pendingId);
                $request->session()->put('admin.2fa_remember', $remember);

                return redirect()->route('admin.two-factor.challenge');
            }

            $request->session()->regenerate();

            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors([
            'email' => 'Credenciais inválidas.',
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->forget(['admin.2fa_pending_id', 'admin.2fa_remember']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
