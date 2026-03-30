<?php

namespace Database\Factories;

use App\Models\FaturaKimobe;
use App\Models\Plano;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FaturaKimobe> */
class FaturaKimobeFactory extends Factory
{
    protected $model = FaturaKimobe::class;

    public function definition(): array
    {
        $mes = $this->faker->numberBetween(1, 12);

        return [
            'tenant_id' => Tenant::factory(),
            'plano_id' => Plano::factory(),
            'referencia' => str_pad($mes, 2, '0', STR_PAD_LEFT) . '/2026',
            'valor' => $this->faker->randomFloat(2, 49.90, 899.90),
            'data_vencimento' => now()->startOfMonth()->addDays(9),
            'data_pagamento' => null,
            'metodo_pagamento' => null,
            'status' => $this->faker->randomElement(['pendente', 'pago', 'atrasado']),
        ];
    }
}
