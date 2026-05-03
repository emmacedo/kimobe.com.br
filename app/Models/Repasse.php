<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\RepasseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'fatura_id', 'titularidade_id', 'valor_aluguel_bruto', 'taxa_administracao_valor',
    'taxa_seguro_inadimplencia_valor', 'valor_liquido', 'data_prevista', 'data_realizada',
    'status', 'observacoes',
    'taxa_administracao_pct_aplicada', 'taxa_seguro_inadimplencia_pct_aplicada',
    'percentual_titularidade_aplicado', 'gerado_por_user_id', 'realizado_por_user_id',
])]
class Repasse extends Model
{
    /** @use HasFactory<RepasseFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'repasses';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_aluguel_bruto' => 'decimal:2',
            'taxa_administracao_valor' => 'decimal:2',
            'taxa_seguro_inadimplencia_valor' => 'decimal:2',
            'valor_liquido' => 'decimal:2',
            'data_prevista' => 'date',
            'data_realizada' => 'date',
            'status' => 'string',
            'taxa_administracao_pct_aplicada' => 'decimal:2',
            'taxa_seguro_inadimplencia_pct_aplicada' => 'decimal:2',
            'percentual_titularidade_aplicado' => 'decimal:2',
        ];
    }

    /**
     * Fatura que originou este repasse.
     */
    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class);
    }

    /**
     * Titular que recebe este repasse.
     */
    public function titularidade(): BelongsTo
    {
        return $this->belongsTo(Titularidade::class);
    }

    /**
     * Comprovantes de transferência deste repasse (polimórficos).
     */
    public function comprovantes(): MorphMany
    {
        return $this->morphMany(Comprovante::class, 'owner');
    }
}
