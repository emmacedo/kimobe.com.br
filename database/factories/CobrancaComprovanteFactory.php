<?php

namespace Database\Factories;

use App\Models\Cobranca;
use App\Models\CobrancaComprovante;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CobrancaComprovante> */
class CobrancaComprovanteFactory extends Factory
{
    protected $model = CobrancaComprovante::class;

    public function definition(): array
    {
        $ext = $this->faker->randomElement(['pdf', 'jpg', 'png']);

        return [
            'tenant_id' => Tenant::factory(),
            'cobranca_id' => Cobranca::factory(),
            'caminho' => 'comprovantes/cobrancas/' . $this->faker->numberBetween(1, 999) . '/comprovante.' . $ext,
            'nome_arquivo' => 'comprovante.' . $ext,
            'mime_type' => $ext === 'pdf' ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext),
            'tamanho_bytes' => $this->faker->numberBetween(50000, 2000000),
            'observacoes' => null,
        ];
    }
}
