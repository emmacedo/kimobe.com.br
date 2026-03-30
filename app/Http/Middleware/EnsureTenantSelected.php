<?php

namespace App\Http\Middleware;

use App\Services\TenantService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSelected
{
    public function __construct(
        protected TenantService $tenantService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Se não está autenticado, deixa passar — o middleware auth cuida disso
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $tenantId = $this->tenantService->getTenantId();

        // Se já tem tenant na sessão, valida se o vínculo ainda é ativo
        if ($tenantId) {
            $vinculoValido = $user->vinculos()
                ->where('tenant_id', $tenantId)
                ->where('status', 'ativo')
                ->exists();

            if ($vinculoValido) {
                return $next($request);
            }

            // Vínculo inválido — limpa e re-avalia
            $this->tenantService->clearTenant();
        }

        // Busca vínculos ativos do usuário
        $vinculos = $this->tenantService->getUserVinculos($user);

        // Sem vínculos ativos → tela de "sem acesso"
        if ($vinculos->isEmpty()) {
            return redirect()->route('tenant.sem-acesso');
        }

        // Agrupa por tenant_id para contar tenants distintos (não vínculos)
        $tenantIds = $vinculos->pluck('tenant_id')->unique();

        // Se só tem 1 tenant, auto-seleciona
        if ($tenantIds->count() === 1) {
            $this->tenantService->setTenant($vinculos->first()->tenant);
            return $next($request);
        }

        // Múltiplos tenants → tela de seleção
        return redirect()->route('tenant.selecionar');
    }
}
