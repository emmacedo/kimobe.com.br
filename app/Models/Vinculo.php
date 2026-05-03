<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCreator;
use App\Services\TenantService;
use Database\Factories\VinculoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['user_id', 'tenant_id', 'papel', 'status', 'criado_por_user_id', 'atualizado_por_user_id'])]
class Vinculo extends Model
{
    /** @use HasFactory<VinculoFactory> */
    use BelongsToCreator, HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'papel'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

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
     * Contratos onde este vínculo é o inquilino PRINCIPAL (cache).
     * NÃO inclui contratos onde é apenas co-inquilino — para isso, use participacoesEmContratos().
     */
    public function contratosComoInquilino(): HasMany
    {
        return $this->hasMany(Contrato::class, 'inquilino_vinculo_id');
    }

    /**
     * Participações em contratos via pivot — inclui principal E co-inquilinos.
     * Usado para count exato de contratos do inquilino na listagem.
     */
    public function participacoesEmContratos(): HasMany
    {
        return $this->hasMany(ContratoInquilino::class);
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
