<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'nome', 'tipo', 'documento', 'plano', 'status', 'plano_id',
    'cortesia', 'motivo_cortesia', 'bloqueado_em', 'motivo_bloqueio',
    'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
    'email_contato', 'telefone_comercial', 'whatsapp', 'site',
])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => 'string',
            'plano' => 'string',
            'status' => 'string',
            'cortesia' => 'boolean',
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
     * Plano de assinatura atual (tabela planos).
     */
    public function planoAssinatura(): BelongsTo
    {
        return $this->belongsTo(Plano::class, 'plano_id');
    }

    /**
     * Faturas do Kimobe para este assinante.
     */
    public function faturasKimobe(): HasMany
    {
        return $this->hasMany(FaturaKimobe::class);
    }

    public function estaBloqueado(): bool
    {
        return $this->status === 'bloqueado';
    }

    public function estaCortesia(): bool
    {
        return $this->cortesia === true;
    }

    public function estaInadimplente(): bool
    {
        return $this->faturasKimobe()->where('status', 'atrasado')->exists();
    }

    public function podeAdicionarImovel(): bool
    {
        $plano = $this->planoAssinatura;
        if (! $plano || $plano->limite_imoveis === 0) {
            return true;
        }

        return Imovel::withoutGlobalScopes()
            ->where('tenant_id', $this->id)
            ->count() < $plano->limite_imoveis;
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
}
