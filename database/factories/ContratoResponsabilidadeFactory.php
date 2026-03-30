<?php

namespace Database\Factories;

use App\Models\Contrato;
use App\Models\ContratoResponsabilidade;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContratoResponsabilidade>
 */
class ContratoResponsabilidadeFactory extends Factory
{
    protected $model = ContratoResponsabilidade::class;

    /**
     * Itens pré-definidos com periodicidade padrão.
     */
    public const PREDEFINIDOS = [
        ['descricao' => 'IPTU', 'periodicidade' => 'anual'],
        ['descricao' => 'Condomínio', 'periodicidade' => 'mensal'],
        ['descricao' => 'Taxa extra de condomínio', 'periodicidade' => 'avulso'],
        ['descricao' => 'Seguro incêndio', 'periodicidade' => 'anual'],
        ['descricao' => 'Taxa dos Bombeiros', 'periodicidade' => 'anual'],
    ];

    public function definition(): array
    {
        $item = $this->faker->randomElement(self::PREDEFINIDOS);

        return [
            'tenant_id' => Tenant::factory(),
            'contrato_id' => Contrato::factory(),
            'descricao' => $item['descricao'],
            'responsavel' => $this->faker->randomElement(['proprietario', 'inquilino']),
            'valor' => $this->faker->randomFloat(2, 50, 1500),
            'periodicidade' => $item['periodicidade'],
            'predefinido' => true,
            'observacoes' => null,
        ];
    }
}
