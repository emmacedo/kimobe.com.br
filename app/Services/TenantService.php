<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Vinculo;
use Illuminate\Database\Eloquent\Collection;

class TenantService
{
    /**
     * Cache em memória do tenant ativo na request atual.
     * Evita queries repetidas ao banco na mesma request.
     */
    private ?Tenant $tenantCache = null;

    /**
     * Flag para saber se já tentamos carregar o tenant do cache.
     * Necessário para distinguir "não buscou ainda" de "buscou e não encontrou".
     */
    private bool $tenantLoaded = false;

    /**
     * Salva o tenant ativo na sessão.
     */
    public function setTenant(Tenant $tenant): void
    {
        session(['tenant_id' => $tenant->id]);
        $this->tenantCache = $tenant;
        $this->tenantLoaded = true;
    }

    /**
     * Retorna o tenant ativo da sessão, com cache em memória.
     */
    public function getTenant(): ?Tenant
    {
        if ($this->tenantLoaded) {
            return $this->tenantCache;
        }

        $tenantId = $this->getTenantId();
        $this->tenantLoaded = true;

        if (! $tenantId) {
            $this->tenantCache = null;
            return null;
        }

        $this->tenantCache = Tenant::find($tenantId);

        return $this->tenantCache;
    }

    /**
     * Retorna o ID do tenant ativo da sessão.
     */
    public function getTenantId(): ?int
    {
        return session('tenant_id');
    }

    /**
     * Limpa o tenant da sessão e do cache em memória.
     */
    public function clearTenant(): void
    {
        session()->forget('tenant_id');
        $this->tenantCache = null;
        $this->tenantLoaded = false;
    }

    /**
     * Retorna os vínculos ativos do usuário com os tenants carregados (eager load),
     * ordenados pelo nome do tenant.
     */
    public function getUserVinculos(User $user): Collection
    {
        return $user->vinculos()
            ->where('status', 'ativo')
            ->with('tenant')
            ->get()
            ->sortBy('tenant.nome')
            ->values();
    }

    /**
     * Retorna os papéis do usuário no tenant informado.
     * Ex: ['admin', 'proprietario']
     */
    public function getUserPapeis(User $user, Tenant $tenant): array
    {
        return Vinculo::where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->where('status', 'ativo')
            ->pluck('papel')
            ->toArray();
    }
}
