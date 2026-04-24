<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminRequire2FA
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if ($admin && ! $admin->hasEnabledTwoFactorAuthentication() && ! $request->routeIs('admin.minha-conta*', 'admin.logout')) {
            return redirect()->route('admin.minha-conta')
                ->with('aviso_2fa', 'Configure a autenticação em dois fatores para acessar o painel.');
        }

        return $next($request);
    }
}
