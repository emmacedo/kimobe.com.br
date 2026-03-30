<?php

namespace Database\Factories;

use App\Models\Cobranca;
use App\Models\CobrancaItemExtra;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CobrancaItemExtra> */
class CobrancaItemExtraFactory extends Factory
{
    protected $model = CobrancaItemExtra::class;

    private const DESCRICOES = [
        'Reparo no interfone — rateio', 'Taxa de mudança', 'Pintura do hall — rateio',
        'Limpeza de caixa d\'água — rateio', 'Reparo emergencial hidráulico',
        'Chaveiro — troca de fechadura', 'Dedetização — rateio',
    ];

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'cobranca_id' => Cobranca::factory(),
            'descricao' => $this->faker->randomElement(self::DESCRICOES),
            'valor' => $this->faker->randomFloat(2, 20, 300),
            'observacoes' => null,
        ];
    }
}
