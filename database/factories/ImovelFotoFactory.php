<?php

namespace Database\Factories;

use App\Models\Imovel;
use App\Models\ImovelFoto;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImovelFoto>
 */
class ImovelFotoFactory extends Factory
{
    protected $model = ImovelFoto::class;

    /**
     * Legendas realistas para fotos de imóveis.
     */
    private const LEGENDAS = [
        'Sala de estar', 'Cozinha', 'Quarto principal', 'Banheiro social',
        'Varanda', 'Área de serviço', 'Vista da janela', 'Fachada do prédio',
        'Garagem', 'Hall de entrada', 'Quarto secundário', 'Suíte',
        'Escritório', 'Área gourmet', 'Piscina',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $legenda = $this->faker->randomElement(self::LEGENDAS);
        $extensao = $this->faker->randomElement(['jpg', 'png', 'webp']);
        $nomeArquivo = strtolower(str_replace(' ', '-', $legenda)) . '.' . $extensao;

        return [
            'tenant_id' => Tenant::factory(),
            'imovel_id' => Imovel::factory(),
            'caminho' => 'imoveis/' . $this->faker->numberBetween(1, 999) . '/' . $nomeArquivo,
            'nome_arquivo' => $nomeArquivo,
            'legenda' => $legenda,
            'ordem' => 1,
            'mime_type' => 'image/' . ($extensao === 'jpg' ? 'jpeg' : $extensao),
            'tamanho_bytes' => $this->faker->numberBetween(100000, 5000000),
        ];
    }
}
