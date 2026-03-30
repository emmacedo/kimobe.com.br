<?php

namespace Database\Factories;

use App\Models\Cobranca;
use App\Models\Contrato;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cobranca> */
class CobrancaFactory extends Factory
{
    protected $model = Cobranca::class;

    public function definition(): array
    {
        $mes = $this->faker->numberBetween(1, 12);

        return [
            'tenant_id' => Tenant::factory(),
            'contrato_id' => Contrato::factory(),
            'referencia' => str_pad($mes, 2, '0', STR_PAD_LEFT) . '/2026',
            'valor_aluguel' => $this->faker->randomFloat(2, 800, 8000),
            'valor_condominio' => $this->faker->optional(0.7)->randomFloat(2, 300, 2000),
            'valor_iptu' => $this->faker->optional(0.6)->randomFloat(2, 50, 500),
            'valor_seguro_incendio' => $this->faker->optional(0.4)->randomFloat(2, 30, 200),
            'valor_taxa_bombeiros' => null,
            'valor_taxa_extra_condominio' => null,
            'valor_total' => $this->faker->randomFloat(2, 1000, 10000),
            'valor_desconto' => null,
            'valor_juros' => null,
            'valor_multa' => null,
            'valor_pago' => null,
            'data_vencimento' => now()->startOfMonth()->addDays($this->faker->numberBetween(0, 27)),
            'data_pagamento' => null,
            'metodo_pagamento' => null,
            'tipo_geracao' => 'automatica',
            'status' => $this->faker->randomElement(
                array_merge(
                    array_fill(0, 6, 'pago'),
                    array_fill(0, 2, 'pendente'),
                    ['atrasado', 'cancelado']
                )
            ),
        ];
    }
}
