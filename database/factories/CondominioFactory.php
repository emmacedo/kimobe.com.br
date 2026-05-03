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
            'entidade_externa_id' => null,
            'acesso_login' => $this->faker->optional(0.5)->userName(),
            'acesso_senha' => $this->faker->optional(0.5)->password(8, 12),
            'acesso_descricao' => $this->faker->optional(0.3)->sentence(),
        ];
    }
}
