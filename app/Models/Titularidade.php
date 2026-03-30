<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TitularidadeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'imovel_id', 'vinculo_id', 'dados_bancarios_id', 'tipo_titular', 'papel', 'percentual',
])]
class Titularidade extends Model
{
    /** @use HasFactory<TitularidadeFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'titularidades';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo_titular' => 'string',
            'papel' => 'string',
            'percentual' => 'decimal:2',
        ];
    }

    /**
     * Imóvel ao qual esta titularidade se refere.
     */
    public function imovel(): BelongsTo
    {
        return $this->belongsTo(Imovel::class);
    }

    /**
     * Vínculo (pessoa titular) deste imóvel.
     */
    public function vinculo(): BelongsTo
    {
        return $this->belongsTo(Vinculo::class);
    }

    /**
     * Conta bancária para repasse deste titular neste imóvel.
     */
    public function dadosBancarios(): BelongsTo
    {
        return $this->belongsTo(DadosBancarios::class);
    }

    /**
     * Repasses financeiros recebidos por este titular.
     */
    public function repasses(): HasMany
    {
        return $this->hasMany(Repasse::class);
    }
}
