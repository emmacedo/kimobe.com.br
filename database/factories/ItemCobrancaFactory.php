<?php

namespace Database\Factories;

use App\Models\Contrato;
use App\Models\ItemCobranca;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ItemCobranca>
 */
class ItemCobrancaFactory extends Factory
{
    protected $model = ItemCobranca::class;

    /**
     * Estado padrão: aluguel mensal recorrente, pagante=inquilino, recebedor=proprietario.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mes = $this->faker->numberBetween(1, 12);
        $ano = 2026;

        return [
            'tenant_id' => Tenant::factory(),
            'contrato_id' => Contrato::factory(),
            'parent_item_id' => null,
            'descricao' => $this->faker->randomElement(['Aluguel', 'Condomínio', 'IPTU', 'Seguro incêndio']),
            'pagante' => 'inquilino',
            'recebedor' => 'proprietario',
            'entidade_externa_id' => null,
            'tipo' => 'recorrente',
            'periodicidade' => 'mensal',
            'num_parcela' => null,
            'num_parcelas_total' => null,
            'valor_unitario' => $this->faker->randomFloat(2, 100, 5000),
            'mes_referencia' => str_pad((string) $mes, 2, '0', STR_PAD_LEFT).'/'.$ano,
            'visivel_inquilino' => true,
            'status' => 'pendente',
            'fatura_id' => null,
            'data_pagamento_externo' => null,
            'pagamento_externo_por_user_id' => null,
            'observacoes' => null,
            'criado_por_user_id' => null,
            'atualizado_por_user_id' => null,
        ];
    }

    /**
     * Estado: item recorrente com periodicidade configurável.
     */
    public function recorrente(string $periodicidade = 'mensal'): static
    {
        return $this->state(fn () => [
            'tipo' => 'recorrente',
            'periodicidade' => $periodicidade,
            'num_parcela' => null,
            'num_parcelas_total' => null,
        ]);
    }

    /**
     * Estado: item parcelado (ex: IPTU em 12x).
     */
    public function parcelado(int $parcela, int $total): static
    {
        return $this->state(fn () => [
            'tipo' => 'parcelado',
            'periodicidade' => null,
            'num_parcela' => $parcela,
            'num_parcelas_total' => $total,
        ]);
    }

    /**
     * Estado: item avulso (ocorrência única — chuveiro, frete, multa).
     */
    public function avulso(): static
    {
        return $this->state(fn () => [
            'tipo' => 'avulso',
            'periodicidade' => null,
            'num_parcela' => null,
            'num_parcelas_total' => null,
        ]);
    }

    /**
     * Estado: item já conciliado em uma fatura específica.
     */
    public function conciliado(int $faturaId): static
    {
        return $this->state(fn () => [
            'status' => 'conciliado',
            'fatura_id' => $faturaId,
        ]);
    }

    /**
     * Estado: intermediação — recebedor=administradora repassa a uma entidade externa.
     */
    public function intermediado(int $entidadeExternaId): static
    {
        return $this->state(fn () => [
            'recebedor' => 'administradora',
            'entidade_externa_id' => $entidadeExternaId,
        ]);
    }
}
