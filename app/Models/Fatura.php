<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\FaturaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'contrato_id', 'referencia',
    'valor_total', 'valor_desconto', 'valor_juros', 'valor_multa', 'valor_pago',
    'data_vencimento', 'data_pagamento', 'metodo_pagamento', 'tipo_geracao',
    'status', 'url_boleto', 'observacoes',
    'multa_atraso_pct_aplicada', 'juros_atraso_pct_dia_aplicada',
    'desconto_pontualidade_pct_aplicada', 'dias_carencia_aplicada',
    'gerada_por_user_id', 'baixada_por_user_id',
])]
class Fatura extends Model
{
    /** @use HasFactory<FaturaFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'faturas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_total' => 'decimal:2',
            'valor_desconto' => 'decimal:2',
            'valor_juros' => 'decimal:2',
            'valor_multa' => 'decimal:2',
            'valor_pago' => 'decimal:2',
            'data_vencimento' => 'date',
            'data_pagamento' => 'date',
            'metodo_pagamento' => 'string',
            'tipo_geracao' => 'string',
            'status' => 'string',
            'multa_atraso_pct_aplicada' => 'decimal:2',
            'juros_atraso_pct_dia_aplicada' => 'decimal:4',
            'desconto_pontualidade_pct_aplicada' => 'decimal:2',
            'dias_carencia_aplicada' => 'integer',
        ];
    }

    /**
     * Contrato que originou esta fatura.
     */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    /**
     * Itens de cobrança conciliados nesta fatura.
     */
    public function itens(): HasMany
    {
        return $this->hasMany(ItemCobranca::class, 'fatura_id');
    }

    /**
     * Comprovantes de pagamento desta fatura (polimórficos).
     */
    public function comprovantes(): MorphMany
    {
        return $this->morphMany(Comprovante::class, 'owner');
    }

    /**
     * Repasses aos proprietários gerados a partir desta fatura.
     */
    public function repasses(): HasMany
    {
        return $this->hasMany(Repasse::class, 'fatura_id');
    }

    /**
     * Verifica se a fatura está atrasada (pendente e vencida).
     */
    protected function estaAtrasado(): Attribute
    {
        return Attribute::get(
            fn () => $this->status === 'pendente' && $this->data_vencimento?->isPast()
        );
    }

    /**
     * Calcula o valor final com acréscimos: total + juros + multa - desconto.
     */
    protected function valorComAcrescimos(): Attribute
    {
        return Attribute::get(
            fn () => (float) $this->valor_total
                + (float) ($this->valor_juros ?? 0)
                + (float) ($this->valor_multa ?? 0)
                - (float) ($this->valor_desconto ?? 0)
        );
    }
}
