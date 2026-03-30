<?php

namespace Database\Factories;

use App\Models\Contrato;
use App\Models\Imovel;
use App\Models\Tenant;
use App\Models\Vinculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contrato>
 */
class ContratoFactory extends Factory
{
    protected $model = Contrato::class;

    public function definition(): array
    {
        $dataInicio = $this->faker->dateTimeBetween('-12 months', 'now');
        $modeloRepasse = $this->faker->randomElement(['por_recebimento', 'garantido']);

        return [
            'tenant_id' => Tenant::factory(),
            'imovel_id' => Imovel::factory(),
            'inquilino_vinculo_id' => Vinculo::factory(),
            'data_inicio' => $dataInicio,
            'data_fim' => (clone $dataInicio)->modify('+30 months'),
            'valor_aluguel' => $this->faker->randomFloat(2, 800, 8000),
            'dia_vencimento' => $this->faker->numberBetween(1, 28),
            'modelo_repasse' => $modeloRepasse,
            'taxa_administracao_pct' => $this->faker->randomFloat(2, 6, 12),
            'taxa_seguro_inadimplencia_pct' => $modeloRepasse === 'garantido'
                ? $this->faker->randomFloat(2, 2, 5)
                : null,
            'indice_reajuste' => $this->faker->randomElement(['igpm', 'ipca', 'fixo']),
            'mes_reajuste' => $this->faker->numberBetween(1, 12),
            'multa_atraso_pct' => 2.00,
            'juros_atraso_pct_dia' => 0.0333,
            'dias_carencia' => 0,
            'multa_rescisoria_pct' => $this->faker->randomFloat(2, 10, 30),
            'desconto_pontualidade_pct' => $this->faker->optional(0.3)->randomFloat(2, 3, 10),
            'tipo_garantia' => $this->faker->randomElement(['caucao', 'fiador', 'seguro_fianca', 'titulo_capitalizacao', 'sem_garantia']),
            'status' => 'ativo',
            'observacoes' => null,
        ];
    }
}
