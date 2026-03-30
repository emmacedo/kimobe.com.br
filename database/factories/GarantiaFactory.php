<?php

namespace Database\Factories;

use App\Models\Contrato;
use App\Models\Garantia;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Garantia>
 */
class GarantiaFactory extends Factory
{
    protected $model = Garantia::class;

    public function definition(): array
    {
        $tipo = $this->faker->randomElement(['caucao', 'fiador', 'seguro_fianca', 'titulo_capitalizacao']);
        $dataInicio = $this->faker->dateTimeBetween('-12 months', 'now');

        return [
            'tenant_id' => Tenant::factory(),
            'contrato_id' => Contrato::factory(),
            'tipo' => $tipo,
            'valor' => in_array($tipo, ['caucao', 'seguro_fianca', 'titulo_capitalizacao'])
                ? $this->faker->randomFloat(2, 2000, 20000)
                : null,
            'seguradora' => $tipo === 'seguro_fianca'
                ? $this->faker->randomElement(['Porto Seguro', 'SulAmérica', 'Tokio Marine', 'Allianz'])
                : null,
            'numero_apolice' => $tipo === 'seguro_fianca'
                ? 'PSF-' . $this->faker->numerify('####-#####')
                : null,
            'numero_titulo' => $tipo === 'titulo_capitalizacao'
                ? 'TC-' . $this->faker->numerify('####-#####')
                : null,
            'data_inicio' => $dataInicio,
            'data_fim' => (clone $dataInicio)->modify('+12 months'),
            'status' => 'ativo',
            'observacoes' => null,
        ];
    }
}
