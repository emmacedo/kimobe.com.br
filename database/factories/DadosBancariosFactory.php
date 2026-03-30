<?php

namespace Database\Factories;

use App\Models\DadosBancarios;
use App\Models\Tenant;
use App\Models\Vinculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DadosBancarios>
 */
class DadosBancariosFactory extends Factory
{
    protected $model = DadosBancarios::class;

    /**
     * Bancos brasileiros reais com código e nome.
     */
    private const BANCOS = [
        ['codigo' => '001', 'nome' => 'Banco do Brasil'],
        ['codigo' => '033', 'nome' => 'Santander'],
        ['codigo' => '104', 'nome' => 'Caixa Econômica Federal'],
        ['codigo' => '237', 'nome' => 'Bradesco'],
        ['codigo' => '341', 'nome' => 'Itaú Unibanco'],
        ['codigo' => '260', 'nome' => 'Nubank'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $banco = $this->faker->randomElement(self::BANCOS);
        $pixTipo = $this->faker->optional(0.7)->randomElement(['cpf', 'cnpj', 'email', 'telefone', 'aleatoria']);

        return [
            'tenant_id' => Tenant::factory(),
            'vinculo_id' => Vinculo::factory(),
            'apelido' => 'Conta ' . $banco['nome'],
            'banco_codigo' => $banco['codigo'],
            'banco_nome' => $banco['nome'],
            'agencia' => (string) $this->faker->numberBetween(1000, 9999),
            'conta' => $this->faker->numerify('#####-#'),
            'tipo_conta' => $this->faker->randomElement(['corrente', 'poupanca']),
            'pix_tipo' => $pixTipo,
            'pix_chave' => $pixTipo ? $this->gerarPixChave($pixTipo) : null,
        ];
    }

    /**
     * Gera uma chave PIX fictícia conforme o tipo.
     */
    private function gerarPixChave(string $tipo): string
    {
        return match ($tipo) {
            'cpf' => $this->faker->numerify('###.###.###-##'),
            'cnpj' => $this->faker->numerify('##.###.###/####-##'),
            'email' => $this->faker->safeEmail(),
            'telefone' => $this->faker->numerify('+5521#########'),
            'aleatoria' => $this->faker->uuid(),
        };
    }
}
