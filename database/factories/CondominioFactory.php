<?php

namespace Database\Factories;

use App\Models\Condominio;
use App\Models\Imovel;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Condominio>
 */
class CondominioFactory extends Factory
{
    protected $model = Condominio::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'imovel_id' => Imovel::factory(),
            'administradora_id' => null,
            'dia_vencimento' => $this->faker->numberBetween(1, 28),
            'valor' => $this->faker->randomFloat(2, 100, 2500),
            'acesso_login' => $this->faker->optional(0.5)->userName(),
            'acesso_senha' => $this->faker->optional(0.5)->password(8, 12),
            'acesso_descricao' => $this->faker->optional(0.3)->sentence(),
        ];
    }
}
