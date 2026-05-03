<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ItemCobrancaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'parent_item_id',
    'contrato_id',
    'descricao',
    'pagante',
    'recebedor',
    'entidade_externa_id',
    'tipo',
    'periodicidade',
    'num_parcela',
    'num_parcelas_total',
    'valor_unitario',
    'mes_referencia',
    'visivel_inquilino',
    'status',
    'fatura_id',
    'data_pagamento_externo',
    'pagamento_externo_por_user_id',
    'observacoes',
    'criado_por_user_id',
    'atualizado_por_user_id',
])]
class ItemCobranca extends Model
{
    /** @use HasFactory<ItemCobrancaFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'descricao', 'pagante', 'recebedor', 'entidade_externa_id',
                'valor_unitario', 'visivel_inquilino', 'status',
                'data_pagamento_externo', 'observacoes',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    protected $table = 'itens_cobranca';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pagante' => 'string',
            'recebedor' => 'string',
            'tipo' => 'string',
            'periodicidade' => 'string',
            'num_parcela' => 'integer',
            'num_parcelas_total' => 'integer',
            'valor_unitario' => 'decimal:2',
            'visivel_inquilino' => 'boolean',
            'status' => 'string',
            'data_pagamento_externo' => 'date',
        ];
    }

    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    public function entidadeExterna(): BelongsTo
    {
        return $this->belongsTo(EntidadeExterna::class);
    }

    /**
     * Item-pai da série (primeira ocorrência). Null para a própria primeira.
     */
    public function pai(): BelongsTo
    {
        return $this->belongsTo(ItemCobranca::class, 'parent_item_id');
    }

    /**
     * Demais ocorrências da série, agrupadas por parent_item_id apontando para este registro.
     */
    public function ocorrencias(): HasMany
    {
        return $this->hasMany(ItemCobranca::class, 'parent_item_id');
    }

    /**
     * Fatura que conciliou este item. Null enquanto pendente.
     */
    public function fatura(): BelongsTo
    {
        return $this->belongsTo(Fatura::class, 'fatura_id');
    }

    /**
     * Comprovantes anexados a este item de cobrança (ex: comprovante da admin
     * pagando o síndico em itens com `recebedor=administradora` intermediada).
     */
    public function comprovantes(): MorphMany
    {
        return $this->morphMany(Comprovante::class, 'owner');
    }
}
