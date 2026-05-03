<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CondominioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'imovel_id', 'entidade_externa_id',
    'acesso_login', 'acesso_senha', 'acesso_descricao',
])]
class Condominio extends Model
{
    /** @use HasFactory<CondominioFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'condominios';

    public function imovel(): BelongsTo
    {
        return $this->belongsTo(Imovel::class);
    }

    public function entidadeExterna(): BelongsTo
    {
        return $this->belongsTo(EntidadeExterna::class);
    }
}
