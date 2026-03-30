<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CobrancaItemExtraFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cobranca_id', 'descricao', 'valor', 'observacoes'])]
class CobrancaItemExtra extends Model
{
    /** @use HasFactory<CobrancaItemExtraFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'cobranca_itens_extras';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
        ];
    }

    /**
     * Cobrança à qual este item extra pertence.
     */
    public function cobranca(): BelongsTo
    {
        return $this->belongsTo(Cobranca::class);
    }
}
