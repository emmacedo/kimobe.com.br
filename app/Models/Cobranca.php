<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CobrancaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'contrato_id', 'referencia', 'valor_aluguel', 'valor_condominio', 'valor_iptu',
    'valor_seguro_incendio', 'valor_taxa_bombeiros', 'valor_taxa_extra_condominio',
    'valor_total', 'valor_desconto', 'valor_juros', 'valor_multa', 'valor_pago',
    'data_vencimento', 'data_pagamento', 'metodo_pagamento', 'tipo_geracao',
    'status', 'url_boleto', 'observacoes',
])]
class Cobranca extends Model
{
    /** @use HasFactory<CobrancaFactory> */
    use BelongsToTenant, HasFactory;

    protected $table = 'cobrancas';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor_aluguel' => 'decimal:2',
            'valor_condominio' => 'decimal:2',
            'valor_iptu' => 'decimal:2',
            'valor_seguro_incendio' => 'decimal:2',
            'valor_taxa_bombeiros' => 'decimal:2',
            'valor_taxa_extra_condominio' => 'decimal:2',
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
        ];
    }

    /**
     * Contrato que originou esta cobrança.
     */
    public function contrato(): BelongsTo
    {
        return $this->belongsTo(Contrato::class);
    }

    /**
     * Itens extras adicionados a esta cobrança.
     */
    public function itensExtras(): HasMany
    {
        return $this->hasMany(CobrancaItemExtra::class);
    }

    /**
     * Comprovantes de pagamento desta cobrança.
     */
    public function comprovantes(): HasMany
    {
        return $this->hasMany(CobrancaComprovante::class);
    }

    /**
     * Repasses aos proprietários gerados a partir desta cobrança.
     */
    public function repasses(): HasMany
    {
        return $this->hasMany(Repasse::class);
    }

    /**
     * Verifica se a cobrança está atrasada (pendente e vencida).
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
