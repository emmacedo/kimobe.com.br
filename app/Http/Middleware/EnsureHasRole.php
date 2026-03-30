<?php

namespace App\Http\Middleware;

use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware parametrizado que verifica se o usuário tem pelo menos um dos papéis exigidos.
 * Uso: ->middleware('role:admin,proprietario')
 */
class EnsureHasRole
{
    public function __construct(
        protected TenantService $tenantService,
    ) {}

    public function handle(Request $request, Closure $next, string ...$papeisAceitos): Response
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $tenant = $this->tenantService->getTenant();
        if (! $tenant) {
            return redirect()->route('tenant.selecionar');
        }

        // Buscar papéis ativos do usuário no tenant
        $papeis = $this->tenantService->getUserPapeis($user, $tenant);

        // Verificar se tem ao menos um papel aceito
        $temPermissao = ! empty(array_intersect($papeis, $papeisAceitos));

        if (! $temPermissao) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Acesso negado.'], 403);
            }

            return redirect()->route('dashboard')
                ->with('error', 'Você não tem permissão para acessar esta página.');
        }

        return $next($request);
    }
}
