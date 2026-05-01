<?php

namespace App\Models;

use App\Services\TenantService;
use Database\Factories\VinculoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'tenant_id', 'papel', 'status'])]
class Vinculo extends Model
{
    /** @use HasFactory<VinculoFactory> */
    use HasFactory;

    /**
     * Nome da tabela associada ao model.
     */
    protected $table = 'vinculos';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'papel' => 'string',
            'status' => 'string',
        ];
    }

    /**
     * Usuário deste vínculo.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Tenant deste vínculo.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Contas bancárias cadastradas para esta pessoa neste tenant.
     */
    public function dadosBancarios(): HasMany
    {
        return $this->hasMany(DadosBancarios::class);
    }

    /**
     * Titularidades de imóveis desta pessoa neste tenant.
     */
    public function titularidades(): HasMany
    {
        return $this->hasMany(Titularidade::class);
    }

    /**
     * Contratos nos quais esta pessoa é inquilino.
     */
    public function contratosComoInquilino(): HasMany
    {
        return $this->hasMany(Contrato::class, 'inquilino_vinculo_id');
    }

    /**
     * Resolve route binding com filtro por tenant. Vinculo NÃO usa BelongsToTenant
     * (é cross-tenant por natureza), então o filtro precisa ser aplicado no binding
     * para evitar vazamento de dados entre tenants nas rotas /proprietarios/{vinculo}.
     */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $tenantId = app(TenantService::class)->getTenantId();

        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->where('tenant_id', $tenantId)
            ->first();
    }
}
