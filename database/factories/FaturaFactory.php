<?php

namespace Database\Factories;

use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Fatura> */
class FaturaFactory extends Factory
{
    protected $model = Fatura::class;

    public function definition(): array
    {
        $mes = $this->faker->numberBetween(1, 12);

        return [
            'tenant_id' => Tenant::factory(),
            'contrato_id' => Contrato::factory(),
            'referencia' => str_pad((string) $mes, 2, '0', STR_PAD_LEFT).'/2026',
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
