<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\FiadorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'contrato_id', 'nome', 'cpf', 'rg', 'telefone', 'email', 'profissao', 'estado_civil',
    'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf',
])]
class Fiador extends Model
{
    /** @use HasFactory<FiadorFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'fiadores';

    /**
     * Contrato no qual esta pessoa é fiadora.
     */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
