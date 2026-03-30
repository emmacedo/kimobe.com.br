<?php

namespace App\Models;

use Database\Factories\FaturaKimobeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id', 'plano_id', 'referencia', 'valor', 'data_vencimento',
    'data_pagamento', 'metodo_pagamento', 'status', 'observacoes',
])]
class FaturaKimobe extends Model
{
    /** @use HasFactory<FaturaKimobeFactory> */
    use HasFactory;

    protected $table = 'faturas_kimobe';

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'data_vencimento' => 'date',
            'data_pagamento' => 'date',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }
}
