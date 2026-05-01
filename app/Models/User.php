<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'telefone', 'tipo_pessoa', 'documento'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Domínio dos emails placeholder gerados quando um proprietário é cadastrado
     * sem email — permite manter a coluna unique sem alterar o schema.
     */
    public const EMAIL_PLACEHOLDER_DOMAIN = 'nao-cadastrado.kimobe.local';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'tipo_pessoa' => 'string',
        ];
    }

    /**
     * Indica se o email é um placeholder gerado (pessoa cadastrada sem email real).
     */
    public function hasPlaceholderEmail(): bool
    {
        return str_ends_with($this->email ?? '', '@'.self::EMAIL_PLACEHOLDER_DOMAIN);
    }

    /**
     * Vínculos do usuário com tenants (com papel e status).
     */
    public function vinculos(): HasMany
    {
        return $this->hasMany(Vinculo::class);
    }

    /**
     * Tenants aos quais o usuário está vinculado (via tabela vinculos).
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'vinculos')
            ->withPivot('papel', 'status')
            ->withTimestamps();
    }
}
