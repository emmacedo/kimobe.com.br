<?php

namespace App\Models\Scopes;

use App\Services\TenantService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Scope global que filtra automaticamente queries por tenant_id.
 *
 * Só aplica o filtro quando há um tenant ativo na sessão.
 * Em contextos sem sessão (artisan, seeders, jobs), o scope é ignorado
 * para não interferir em operações administrativas.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantService = app(TenantService::class);
        $tenantId = $tenantService->getTenantId();

        if ($tenantId) {
            $builder->where($model->getTable().'.tenant_id', $tenantId);
        }
    }
}
