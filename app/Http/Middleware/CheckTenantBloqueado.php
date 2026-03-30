<?php

namespace App\Http\Middleware;

use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica se o tenant ativo está bloqueado, suspenso ou cancelado.
 * Se sim, redireciona para a tela de bloqueio.
 */
class CheckTenantBloqueado
{
    public function __construct(
        protected TenantService $tenantService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->tenantService->getTenant();

        if ($tenant && in_array($tenant->status, ['bloqueado', 'suspenso', 'cancelado'])) {
            return redirect()->route('tenant.bloqueado');
        }

        return $next($request);
    }
}
