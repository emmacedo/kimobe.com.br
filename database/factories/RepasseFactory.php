<?php

namespace Database\Factories;

use App\Models\Fatura;
use App\Models\Repasse;
use App\Models\Tenant;
use App\Models\Titularidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Repasse> */
class RepasseFactory extends Factory
{
    protected $model = Repasse::class;

    public function definition(): array
    {
        $bruto = $this->faker->randomFloat(2, 500, 5000);
        $taxaAdmin = round($bruto * 0.10, 2);

        return [
            'tenant_id' => Tenant::factory(),
            'fatura_id' => Fatura::factory(),
            'titularidade_id' => Titularidade::factory(),
            'valor_aluguel_bruto' => $bruto,
            'taxa_administracao_valor' => $taxaAdmin,
            'taxa_seguro_inadimplencia_valor' => null,
            'valor_liquido' => $bruto - $taxaAdmin,
            'data_prevista' => now(),
            'data_realizada' => null,
            'status' => 'pendente',
            'observacoes' => null,
        ];
    }
}
