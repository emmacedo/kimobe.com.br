<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCreator;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\GarantiaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'contrato_id', 'tipo', 'valor', 'seguradora', 'numero_apolice', 'numero_titulo',
    'data_inicio', 'data_fim', 'status', 'observacoes',
    'criado_por_user_id', 'atualizado_por_user_id',
])]
class Garantia extends Model
{
    /** @use HasFactory<GarantiaFactory> */
    use BelongsToCreator, BelongsToTenant, HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'tipo', 'valor', 'seguradora', 'numero_apolice', 'numero_titulo',
                'data_inicio', 'data_fim', 'status', 'observacoes',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected $table = 'garantias';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => 'string',
            'valor' => 'decimal:2',
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'status' => 'string',
        ];
    }

    /**
     * Contrato ao qual esta garantia está vinculada.
     */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }
}
