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
    'data_aplicacao',
    'valor_anterior',
    'valor_novo',
    'percentual',
    'indice_usado',
    'origem',
    'observacao',
])]
class ContratoReajuste extends Model
{
    use BelongsToTenant;

    protected $table = 'contrato_reajustes';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'alterado_em' => 'datetime',
            'data_aplicacao' => 'date',
            'valor_anterior' => 'decimal:2',
            'valor_novo' => 'decimal:2',
            'percentual' => 'decimal:4',
            'indice_usado' => 'string',
            'origem' => 'string',
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
