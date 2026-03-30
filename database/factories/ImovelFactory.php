<?php

namespace Database\Factories;

use App\Models\Imovel;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Imovel>
 */
class ImovelFactory extends Factory
{
    protected $model = Imovel::class;

    /**
     * Endereços realistas de cidades brasileiras.
     */
    private const ENDERECOS = [
        ['cep' => '20040-020', 'logradouro' => 'Av. Rio Branco', 'bairro' => 'Centro', 'cidade' => 'Rio de Janeiro', 'uf' => 'RJ'],
        ['cep' => '22041-080', 'logradouro' => 'Rua Barata Ribeiro', 'bairro' => 'Copacabana', 'cidade' => 'Rio de Janeiro', 'uf' => 'RJ'],
        ['cep' => '22071-060', 'logradouro' => 'Rua Tonelero', 'bairro' => 'Copacabana', 'cidade' => 'Rio de Janeiro', 'uf' => 'RJ'],
        ['cep' => '24020-100', 'logradouro' => 'Rua Visconde de Itaboraí', 'bairro' => 'Centro', 'cidade' => 'Niterói', 'uf' => 'RJ'],
        ['cep' => '24360-030', 'logradouro' => 'Rua Lopes Trovão', 'bairro' => 'Icaraí', 'cidade' => 'Niterói', 'uf' => 'RJ'],
        ['cep' => '01310-100', 'logradouro' => 'Av. Paulista', 'bairro' => 'Bela Vista', 'cidade' => 'São Paulo', 'uf' => 'SP'],
        ['cep' => '04543-011', 'logradouro' => 'Av. Brigadeiro Faria Lima', 'bairro' => 'Itaim Bibi', 'cidade' => 'São Paulo', 'uf' => 'SP'],
        ['cep' => '30130-000', 'logradouro' => 'Av. Afonso Pena', 'bairro' => 'Centro', 'cidade' => 'Belo Horizonte', 'uf' => 'MG'],
        ['cep' => '80010-000', 'logradouro' => 'Rua XV de Novembro', 'bairro' => 'Centro', 'cidade' => 'Curitiba', 'uf' => 'PR'],
        ['cep' => '90010-280', 'logradouro' => 'Rua dos Andradas', 'bairro' => 'Centro Histórico', 'cidade' => 'Porto Alegre', 'uf' => 'RS'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tipo = $this->faker->randomElement(['apartamento', 'casa', 'sala', 'loja', 'galpao']);
        $endereco = $this->faker->randomElement(self::ENDERECOS);

        return [
            'tenant_id' => Tenant::factory(),
            'cep' => $endereco['cep'],
            'logradouro' => $endereco['logradouro'],
            'numero' => (string) $this->faker->numberBetween(1, 2000),
            'complemento' => $this->gerarComplemento($tipo),
            'bairro' => $endereco['bairro'],
            'cidade' => $endereco['cidade'],
            'uf' => $endereco['uf'],
            'tipo' => $tipo,
            'status' => $this->faker->randomElement(
                // 70% alugado, 30% outros
                array_merge(
                    array_fill(0, 7, 'alugado'),
                    ['disponivel', 'manutencao', 'inativo']
                )
            ),
            'quartos' => $this->gerarQuartos($tipo),
            'suites' => $this->gerarSuites($tipo),
            'banheiros' => $this->gerarBanheiros($tipo),
            'vagas_garagem' => $this->faker->numberBetween(0, 3),
            'andar' => in_array($tipo, ['apartamento', 'sala', 'loja']) ? $this->faker->numberBetween(1, 25) : null,
            'area_m2' => $this->gerarArea($tipo),
            'valor_aluguel_sugerido' => $this->faker->randomFloat(2, 800, 15000),
            'observacoes' => $this->faker->optional(0.3)->sentence(),
        ];
    }

    private function gerarComplemento(string $tipo): ?string
    {
        return match ($tipo) {
            'apartamento' => 'Apt ' . $this->faker->numberBetween(101, 2504),
            'sala' => 'Sala ' . $this->faker->numberBetween(101, 1510),
            'loja' => 'Loja ' . $this->faker->numberBetween(1, 30),
            default => null,
        };
    }

    private function gerarQuartos(string $tipo): ?int
    {
        return match ($tipo) {
            'apartamento' => $this->faker->numberBetween(1, 4),
            'casa' => $this->faker->numberBetween(2, 5),
            default => null, // sala, loja, galpao não têm quartos
        };
    }

    private function gerarSuites(string $tipo): ?int
    {
        return match ($tipo) {
            'apartamento', 'casa' => $this->faker->numberBetween(0, 2),
            default => null,
        };
    }

    private function gerarBanheiros(string $tipo): ?int
    {
        return match ($tipo) {
            'apartamento' => $this->faker->numberBetween(1, 3),
            'casa' => $this->faker->numberBetween(1, 4),
            'sala', 'loja' => 1,
            'galpao' => $this->faker->numberBetween(1, 2),
        };
    }

    private function gerarArea(string $tipo): float
    {
        return match ($tipo) {
            'apartamento' => $this->faker->randomFloat(2, 40, 200),
            'casa' => $this->faker->randomFloat(2, 80, 400),
            'sala' => $this->faker->randomFloat(2, 20, 80),
            'loja' => $this->faker->randomFloat(2, 30, 200),
            'galpao' => $this->faker->randomFloat(2, 100, 1000),
        };
    }
}
