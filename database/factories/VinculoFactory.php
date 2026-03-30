<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Vinculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vinculo>
 */
class VinculoFactory extends Factory
{
    protected $model = Vinculo::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => Tenant::factory(),
            'papel' => $this->faker->randomElement(['admin', 'proprietario', 'inquilino']),
            'status' => 'ativo',
        ];
    }
}
