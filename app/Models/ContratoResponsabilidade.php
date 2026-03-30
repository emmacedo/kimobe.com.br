<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ContratoResponsabilidadeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'contrato_id', 'descricao', 'responsavel', 'valor', 'periodicidade', 'predefinido', 'observacoes',
])]
class ContratoResponsabilidade extends Model
{
    /** @use HasFactory<ContratoResponsabilidadeFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'contrato_responsabilidades';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'responsavel' => 'string',
            'valor' => 'decimal:2',
            'periodicidade' => 'string',
            'predefinido' => 'boolean',
        ];
    }

    /**
     * Contrato ao qual esta responsabilidade pertence.
     */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
