<?php

namespace Database\Factories;

use App\Models\Imovel;
use App\Models\Tenant;
use App\Models\Titularidade;
use App\Models\Vinculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Titularidade>
 */
class TitularidadeFactory extends Factory
{
    protected $model = Titularidade::class;

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
            'vinculo_id' => Vinculo::factory()->proprietario(),
            'dados_bancarios_id' => null,
            'tipo_titular' => 'pessoa_fisica',
            'papel' => 'responsavel',
            'percentual' => 100.00,
        ];
    }
}
