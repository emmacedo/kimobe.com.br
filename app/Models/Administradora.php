<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AdministradoraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'nome', 'cpf_cnpj', 'telefone', 'email', 'site',
    'contato_interno_nome',
    'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
    'observacoes',
])]
class Administradora extends Model
{
    /** @use HasFactory<AdministradoraFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'administradoras';

    /**
     * Condomínios administrados por esta empresa/profissional.
     */
    public function condominios(): HasMany
    {
        return $this->hasMany(Condominio::class);
    }
}
