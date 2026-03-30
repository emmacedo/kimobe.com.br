<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * Nomes realistas de imobiliárias para geração de dados fake.
     */
    private const NOMES_IMOBILIARIAS = [
        'Imobiliária Horizonte', 'Costa & Lima Imóveis', 'Imobiliária Paulista',
        'Nova Terra Imóveis', 'Alves & Souza Administradora', 'Imobiliária Solar',
        'Pinheiro Imóveis', 'Rede Casa Imobiliária', 'Oliveira & Martins Imóveis',
        'Imobiliária Confiança', 'Brasil Imóveis', 'Porto Seguro Administradora',
        'Imobiliária Cidade Jardim', 'Barros & Filho Imóveis', 'Imobiliária Central',
    ];

    /**
     * Nomes realistas de proprietários diretos.
     */
    private const NOMES_PROPRIETARIOS = [
        'Carlos Mendes Imóveis', 'Sérgio Almeida - Imóveis', 'Dona Maria Locações',
        'Roberto Dias Aluguéis', 'Família Pereira Imóveis', 'Luís Fernando Locações',
        'Antônio Barbosa Imóveis', 'Marcos Teixeira Aluguéis', 'Renata Souza Imóveis',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tipo = $this->faker->randomElement(['imobiliaria', 'proprietario_direto']);

        return [
            'nome' => $tipo === 'imobiliaria'
                ? $this->faker->randomElement(self::NOMES_IMOBILIARIAS)
                : $this->faker->randomElement(self::NOMES_PROPRIETARIOS),
            'tipo' => $tipo,
            'documento' => $tipo === 'imobiliaria'
                ? $this->gerarCnpj()
                : $this->gerarCpf(),
            'plano' => $this->faker->randomElement(['basico', 'profissional', 'enterprise']),
            'status' => $this->faker->randomElement(
                // 90% ativo, 10% suspenso/cancelado
                array_merge(array_fill(0, 9, 'ativo'), ['suspenso', 'cancelado'])
            ),
        ];
    }

    /**
     * Gera um CNPJ fake formatado (apenas dígitos).
     */
    private function gerarCnpj(): string
    {
        $n = [];
        for ($i = 0; $i < 12; $i++) {
            $n[] = random_int(0, 9);
        }

        // Primeiro dígito verificador
        $pesos1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $soma = 0;
        for ($i = 0; $i < 12; $i++) {
            $soma += $n[$i] * $pesos1[$i];
        }
        $resto = $soma % 11;
        $n[12] = $resto < 2 ? 0 : 11 - $resto;

        // Segundo dígito verificador
        $pesos2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $soma = 0;
        for ($i = 0; $i < 13; $i++) {
            $soma += $n[$i] * $pesos2[$i];
        }
        $resto = $soma % 11;
        $n[13] = $resto < 2 ? 0 : 11 - $resto;

        return implode('', $n);
    }

    /**
     * Gera um CPF fake formatado (apenas dígitos).
     */
    private function gerarCpf(): string
    {
        $n = [];
        for ($i = 0; $i < 9; $i++) {
            $n[] = random_int(0, 9);
        }

        // Primeiro dígito verificador
        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += $n[$i] * (10 - $i);
        }
        $resto = $soma % 11;
        $n[9] = $resto < 2 ? 0 : 11 - $resto;

        // Segundo dígito verificador
        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += $n[$i] * (11 - $i);
        }
        $resto = $soma % 11;
        $n[10] = $resto < 2 ? 0 : 11 - $resto;

        return implode('', $n);
    }
}
