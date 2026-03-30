<?php

namespace Database\Factories;

use App\Models\Repasse;
use App\Models\RepasseComprovante;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RepasseComprovante> */
class RepasseComprovanteFactory extends Factory
{
    protected $model = RepasseComprovante::class;

    public function definition(): array
    {
        $ext = $this->faker->randomElement(['pdf', 'jpg', 'png']);

        return [
            'tenant_id' => Tenant::factory(),
            'repasse_id' => Repasse::factory(),
            'caminho' => 'comprovantes/repasses/' . $this->faker->numberBetween(1, 999) . '/transferencia.' . $ext,
            'nome_arquivo' => 'transferencia.' . $ext,
            'mime_type' => $ext === 'pdf' ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext),
            'tamanho_bytes' => $this->faker->numberBetween(50000, 2000000),
            'observacoes' => null,
        ];
    }
}
