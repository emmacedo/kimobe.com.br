<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait para models de negócio que pertencem a um tenant.
 *
 * Aplica automaticamente o TenantScope (filtro por tenant_id) e
 * preenche o tenant_id ao criar novos registros.
 *
 * Uso: adicionar `use BelongsToTenant;` nos models que possuem coluna tenant_id.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Aplica o scope global para filtrar por tenant_id
        static::addGlobalScope(new TenantScope);

        // Auto-preenche tenant_id ao criar um novo registro
        static::creating(function ($model) {
            if (! $model->tenant_id) {
                $tenantService = app(TenantService::class);
                $model->tenant_id = $tenantService->getTenantId();
            }
        });
    }

    /**
     * Tenant ao qual este registro pertence.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
