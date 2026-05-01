<?php

namespace Database\Factories;

use App\Models\Administradora;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Administradora>
 */
class AdministradoraFactory extends Factory
{
    protected $model = Administradora::class;

    /**
     * Nomes realistas de administradoras brasileiras.
     */
    private const NOMES = [
        'Imobiliária Sul Atlântica', 'Administradora Vista Mar', 'Predial Carioca',
        'Sindyc Soluções Condominiais', 'Lopes Administração', 'Bicalho Imóveis',
        'Tijuca Administradora', 'Niterói Predial', 'Centro Administradora',
        'Costa Sul Imóveis',
    ];

    /**
     * Endereços comerciais realistas.
     */
    private const ENDERECOS = [
        ['cep' => '20040020', 'logradouro' => 'Av. Rio Branco', 'bairro' => 'Centro', 'cidade' => 'Rio de Janeiro', 'uf' => 'RJ'],
        ['cep' => '22041080', 'logradouro' => 'Rua Barata Ribeiro', 'bairro' => 'Copacabana', 'cidade' => 'Rio de Janeiro', 'uf' => 'RJ'],
        ['cep' => '24020100', 'logradouro' => 'Rua Visconde de Itaboraí', 'bairro' => 'Centro', 'cidade' => 'Niterói', 'uf' => 'RJ'],
        ['cep' => '01310100', 'logradouro' => 'Av. Paulista', 'bairro' => 'Bela Vista', 'cidade' => 'São Paulo', 'uf' => 'SP'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $endereco = $this->faker->randomElement(self::ENDERECOS);

        return [
            'tenant_id' => Tenant::factory(),
            'nome' => $this->faker->randomElement(self::NOMES),
            'cpf_cnpj' => $this->faker->optional(0.7)->numerify('##############'),
            'telefone' => $this->faker->optional(0.8)->numerify('219########'),
            'email' => $this->faker->optional(0.6)->companyEmail(),
            'site' => $this->faker->optional(0.4)->url(),
            'contato_interno_nome' => $this->faker->optional(0.5)->name(),
            'cep' => $endereco['cep'],
            'logradouro' => $endereco['logradouro'],
            'numero' => (string) $this->faker->numberBetween(1, 2000),
            'complemento' => 'Sala '.$this->faker->numberBetween(101, 1510),
            'bairro' => $endereco['bairro'],
            'cidade' => $endereco['cidade'],
            'uf' => $endereco['uf'],
            'observacoes' => $this->faker->optional(0.3)->sentence(),
        ];
    }

    /**
     * Estado: administradora apenas com nome (mínimo obrigatório).
     */
    public function minimo(): static
    {
        return $this->state(fn () => [
            'cpf_cnpj' => null, 'telefone' => null, 'email' => null,
            'site' => null, 'contato_interno_nome' => null,
            'cep' => null, 'logradouro' => null, 'numero' => null,
            'complemento' => null, 'bairro' => null, 'cidade' => null, 'uf' => null,
            'observacoes' => null,
        ]);
    }
}
