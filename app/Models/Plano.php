<?php

namespace App\Models;

use App\Observers\PlanoObserver;
use Database\Factories\PlanoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nome', 'descricao', 'limite_imoveis', 'valor_mensal', 'status', 'ordem'])]
#[ObservedBy(PlanoObserver::class)]
class Plano extends Model
{
    /** @use HasFactory<PlanoFactory> */
    use HasFactory;

    protected $table = 'planos';

    protected function casts(): array
    {
        return [
            'valor_mensal' => 'decimal:2',
            'limite_imoveis' => 'integer',
            'ordem' => 'integer',
        ];
    }

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function faturas(): HasMany
    {
        return $this->hasMany(FaturaKimobe::class);
    }

    public function scopeAtivo(Builder $query): Builder
    {
        return $query->where('status', 'ativo');
    }

    public function scopeOrdenado(Builder $query): Builder
    {
        return $query->orderBy('ordem')->orderBy('valor_mensal');
    }

    protected function ilimitado(): Attribute
    {
        return Attribute::get(fn () => $this->limite_imoveis === 0);
    }
}
