<?php

namespace Database\Factories;

use App\Models\Contrato;
use App\Models\ContratoInquilino;
use App\Models\Tenant;
use App\Models\Vinculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContratoInquilino>
 */
class ContratoInquilinoFactory extends Factory
{
    protected $model = ContratoInquilino::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'contrato_id' => Contrato::factory(),
            'vinculo_id' => Vinculo::factory()->inquilino(),
            'principal' => true,
        ];
    }

    public function principal(): static
    {
        return $this->state(fn () => ['principal' => true]);
    }

    public function coInquilino(): static
    {
        return $this->state(fn () => ['principal' => false]);
    }
}
