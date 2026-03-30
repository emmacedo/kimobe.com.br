<?php

namespace Database\Factories;

use App\Models\Contrato;
use App\Models\Fiador;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fiador>
 */
class FiadorFactory extends Factory
{
    protected $model = Fiador::class;

    /**
     * Nomes brasileiros realistas.
     */
    private const NOMES = [
        'José Aparecido de Souza', 'Maria Lúcia Ferreira', 'Antônio Carlos Pereira',
        'Francisca das Chagas Lima', 'Sebastião Rodrigues', 'Ana Paula de Oliveira',
        'Raimundo Nonato Silva', 'Teresinha de Jesus Costa', 'Manoel Ribeiro Santos',
        'Luzia Aparecida Martins',
    ];

    private const PROFISSOES = [
        'Engenheiro Civil', 'Médico', 'Advogada', 'Contador', 'Professora',
        'Empresário', 'Dentista', 'Arquiteta', 'Administrador', 'Farmacêutica',
    ];

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'contrato_id' => Contrato::factory(),
            'nome' => $this->faker->randomElement(self::NOMES),
            'cpf' => $this->faker->numerify('###.###.###-##'),
            'rg' => $this->faker->numerify('##.###.###-#'),
            'telefone' => $this->faker->numerify('(21) 9####-####'),
            'email' => $this->faker->safeEmail(),
            'profissao' => $this->faker->randomElement(self::PROFISSOES),
            'estado_civil' => $this->faker->randomElement(['Solteiro(a)', 'Casado(a)', 'Divorciado(a)', 'Viúvo(a)']),
            'cep' => '22041-080',
            'logradouro' => 'Rua Barata Ribeiro',
            'numero' => (string) $this->faker->numberBetween(1, 500),
            'complemento' => 'Apt ' . $this->faker->numberBetween(101, 1204),
            'bairro' => 'Copacabana',
            'cidade' => 'Rio de Janeiro',
            'uf' => 'RJ',
        ];
    }
}
