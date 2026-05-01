<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Kicol\FullFlow\Models\FullFlowPlan;

#[Fillable([
    'nome', 'legal_name', 'tipo', 'tipo_documento', 'documento', 'state_registration', 'municipal_registration',
    'status',
    'is_exempt_from_subscription', 'motivo_isencao', 'auto_upgrade_enabled',
    'bloqueado_em', 'motivo_bloqueio',
    'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
    'email_contato', 'telefone_comercial', 'whatsapp', 'site',
])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'tipo' => 'string',
            'tipo_documento' => 'string',
            'status' => 'string',
            'is_exempt_from_subscription' => 'boolean',
            'auto_upgrade_enabled' => 'boolean',
            'bloqueado_em' => 'datetime',
        ];
    }

    /**
     * Vínculos deste tenant com usuários (com papel e status).
     */
    public function vinculos(): HasMany
    {
        return $this->hasMany(Vinculo::class);
    }

    /**
     * Usuários vinculados a este tenant (via tabela vinculos).
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'vinculos')
            ->withPivot('papel', 'status')
            ->withTimestamps();
    }

    /**
     * Assinatura no catálogo central FullFlow (1:1 com Tenant).
     */
    public function fullflowSubscription(): HasOne
    {
        return $this->hasOne(FullFlowSubscription::class);
    }

    public function estaBloqueado(): bool
    {
        return $this->status === 'bloqueado';
    }

    public function estaIsento(): bool
    {
        return $this->is_exempt_from_subscription === true;
    }

    public function getAdminPrincipal(): ?User
    {
        return $this->vinculos()
            ->where('papel', 'admin')
            ->where('status', 'ativo')
            ->with('user')
            ->first()
            ?->user;
    }

    public function getAdminEmails(): array
    {
        return $this->vinculos()
            ->where('papel', 'admin')
            ->where('status', 'ativo')
            ->with('user')
            ->get()
            ->pluck('user.email')
            ->toArray();
    }

    // --- Helpers FullFlow (catálogo central) ---

    public function currentFullFlowSubscription(): ?FullFlowSubscription
    {
        return FullFlowSubscription::where('tenant_id', $this->id)->first();
    }

    public function currentFullFlowPlan(): ?FullFlowPlan
    {
        $sub = $this->currentFullFlowSubscription();
        if (! $sub || ! $sub->plan_code) {
            return null;
        }

        return FullFlowPlan::where('code', $sub->plan_code)->first();
    }

    public function canAccessModule(string $slug): bool
    {
        if ($this->is_exempt_from_subscription) {
            return true;
        }

        return (bool) $this->currentFullFlowSubscription()?->canAccess($slug);
    }

    public function getModuleQuota(string $slug): ?int
    {
        if ($this->is_exempt_from_subscription) {
            return PHP_INT_MAX;
        }

        return $this->currentFullFlowSubscription()?->getQuota($slug);
    }

    /**
     * Quota de imóveis disponível considerando o plano FullFlow vigente.
     * Retorna true para tenants isentos ou enquanto há vagas;
     * AutoUpgradeService cuida do upgrade automático na criação real.
     */
    public function podeAdicionarImovel(): bool
    {
        if ($this->is_exempt_from_subscription) {
            return true;
        }

        $quota = $this->getModuleQuota('imoveis');
        if ($quota === null) {
            return false;
        }

        $atuais = Imovel::withoutGlobalScopes()
            ->where('tenant_id', $this->id)
            ->count();

        return $atuais < $quota;
    }

    /**
     * Total de imóveis ativos do tenant (uso atual da quota `imoveis`).
     */
    public function totalImoveis(): int
    {
        return Imovel::withoutGlobalScopes()
            ->where('tenant_id', $this->id)
            ->count();
    }
}
