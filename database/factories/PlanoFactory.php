<?php

namespace Database\Factories;

use App\Models\Plano;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plano> */
class PlanoFactory extends Factory
{
    protected $model = Plano::class;

    public function definition(): array
    {
        return [
            'nome' => $this->faker->randomElement(['Starter', 'Pro', 'Business', 'Enterprise']),
            'descricao' => $this->faker->sentence(),
            'limite_imoveis' => $this->faker->randomElement([15, 50, 200, 0]),
            'valor_mensal' => $this->faker->randomFloat(2, 29.90, 999.90),
            'status' => 'ativo',
            'ordem' => $this->faker->numberBetween(1, 10),
        ];
    }
}
