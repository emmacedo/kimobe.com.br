<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'contrato_id',
    'alterado_por_user_id',
    'alterado_em',
    'campo',
    'valor_anterior',
    'valor_novo',
    'data_efetiva',
    'motivo',
])]
class ContratoAlteracao extends Model
{
    use BelongsToTenant;

    protected $table = 'contrato_alteracoes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alterado_em' => 'datetime',
            'data_efetiva' => 'date',
            'valor_anterior' => 'array',
            'valor_novo' => 'array',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function alteradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'alterado_por_user_id');
    }
}
