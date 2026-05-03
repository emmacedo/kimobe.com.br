<?php

namespace Database\Seeders;

use App\Models\Contrato;
use App\Models\Fiador;
use App\Models\Garantia;
use App\Models\Imovel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vinculo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ContratosSeeder extends Seeder
{
    /**
     * Cria 4 contratos demonstrando:
     * - Fiador (contrato 1)
     * - Caução / repasse garantido (contrato 2)
     * - Seguro fiança / imóvel com split entre 3 titulares (contrato 3)
     * - Título capitalização / proprietário direto sem taxa admin (contrato 4)
     *
     * Depende de TenantSeeder e ImoveisSeeder.
     */
    public function run(): void
    {
        // Recuperar entidades existentes
        $tenant1 = Tenant::where('nome', 'Imobiliária Horizonte')->firstOrFail();
        $tenant3 = Tenant::where('nome', 'Carlos Mendes Imóveis')->firstOrFail();

        $imovel1 = Imovel::withoutGlobalScopes()->where('complemento', 'like', '%Ed. Aurora%')->firstOrFail();
        $imovel2 = Imovel::withoutGlobalScopes()->where('complemento', 'like', '%Cond. Verde%')->firstOrFail();
        $imovel4 = Imovel::withoutGlobalScopes()->where('complemento', 'like', '%Ed. Família%')->firstOrFail();
        $imovel5 = Imovel::withoutGlobalScopes()->where('complemento', 'like', '%Galeria Central%')->firstOrFail();

        $pedro = User::where('email', 'pedro@kimobe.test')->firstOrFail();
        $vinculoPedro = Vinculo::where('user_id', $pedro->id)->where('tenant_id', $tenant1->id)->firstOrFail();

        // === Contrato 1 — Apt 302, Ed. Aurora (fiador) ===
        $contrato1 = Contrato::create([
            'tenant_id' => $tenant1->id,
            'imovel_id' => $imovel1->id,
            'inquilino_vinculo_id' => $vinculoPedro->id,
            'data_inicio' => '2025-03-01',
            'data_fim' => '2027-09-01',
            'valor_aluguel' => 2400.00,
            'dia_vencimento' => 5,
            'modelo_repasse' => 'por_recebimento',
            'taxa_administracao_pct' => 10.00,
            'taxa_seguro_inadimplencia_pct' => null,
            'indice_reajuste' => 'igpm',
            'mes_reajuste' => 3,
            'multa_atraso_pct' => 2.00,
            'juros_atraso_pct_dia' => 0.0333,
            'dias_carencia' => 0,
            'multa_rescisoria_pct' => 20.00,
            'desconto_pontualidade_pct' => null,
            'tipo_garantia' => 'fiador',
            'status' => 'ativo',
        ]);

        // Garantia tipo fiador
        Garantia::create([
            'tenant_id' => $tenant1->id,
            'contrato_id' => $contrato1->id,
            'tipo' => 'fiador',
            'valor' => null,
            'data_inicio' => '2025-03-01',
            'data_fim' => '2027-09-01',
            'status' => 'ativo',
        ]);

        // Fiador
        Fiador::create([
            'tenant_id' => $tenant1->id,
            'contrato_id' => $contrato1->id,
            'nome' => 'José Aparecido de Souza',
            'cpf' => '987.654.321-00',
            'rg' => '12.345.678-9',
            'telefone' => '(21) 98765-4321',
            'email' => 'jose.souza@email.com',
            'profissao' => 'Engenheiro Civil',
            'estado_civil' => 'Casado(a)',
            'cep' => '22071-060',
            'logradouro' => 'Rua Tonelero',
            'numero' => '150',
            'complemento' => 'Apt 902',
            'bairro' => 'Copacabana',
            'cidade' => 'Rio de Janeiro',
            'uf' => 'RJ',
        ]);

        // Responsabilidades

        // === Contrato 2 — Casa 14, Cond. Verde (caução / repasse garantido) ===
        $ricardo = User::factory()->create([
            'name' => 'Ricardo Santos',
            'email' => 'ricardo@kimobe.test',
            'password' => Hash::make('password'),
        ]);
        $vinculoRicardo = Vinculo::create([
            'user_id' => $ricardo->id,
            'tenant_id' => $tenant1->id,
            'papel' => 'inquilino',
            'status' => 'ativo',
        ]);

        $contrato2 = Contrato::create([
            'tenant_id' => $tenant1->id,
            'imovel_id' => $imovel2->id,
            'inquilino_vinculo_id' => $vinculoRicardo->id,
            'data_inicio' => '2025-07-01',
            'data_fim' => '2028-01-01',
            'valor_aluguel' => 3800.00,
            'dia_vencimento' => 10,
            'modelo_repasse' => 'garantido',
            'taxa_administracao_pct' => 8.00,
            'taxa_seguro_inadimplencia_pct' => 4.00,
            'indice_reajuste' => 'ipca',
            'mes_reajuste' => 7,
            'multa_atraso_pct' => 2.00,
            'juros_atraso_pct_dia' => 0.0333,
            'dias_carencia' => 0,
            'multa_rescisoria_pct' => 25.00,
            'desconto_pontualidade_pct' => 5.00,
            'tipo_garantia' => 'caucao',
            'status' => 'ativo',
        ]);

        // Garantia tipo caução (2x aluguel)
        Garantia::create([
            'tenant_id' => $tenant1->id,
            'contrato_id' => $contrato2->id,
            'tipo' => 'caucao',
            'valor' => 7600.00,
            'data_inicio' => '2025-07-01',
            'data_fim' => '2028-01-01',
            'status' => 'ativo',
            'observacoes' => 'Valor equivalente a 2x aluguel, depositado em poupança.',
        ]);

        // === Contrato 3 — Apt herdado, Ed. Família (seguro fiança / split 3 titulares) ===
        $rita = User::factory()->create([
            'name' => 'Rita Mendes',
            'email' => 'rita@kimobe.test',
            'password' => Hash::make('password'),
        ]);
        $vinculoRita = Vinculo::create([
            'user_id' => $rita->id,
            'tenant_id' => $tenant1->id,
            'papel' => 'inquilino',
            'status' => 'ativo',
        ]);

        $contrato3 = Contrato::create([
            'tenant_id' => $tenant1->id,
            'imovel_id' => $imovel4->id,
            'inquilino_vinculo_id' => $vinculoRita->id,
            'data_inicio' => '2026-01-01',
            'data_fim' => '2028-07-01',
            'valor_aluguel' => 1900.00,
            'dia_vencimento' => 28,
            'modelo_repasse' => 'por_recebimento',
            'taxa_administracao_pct' => 10.00,
            'taxa_seguro_inadimplencia_pct' => null,
            'indice_reajuste' => 'igpm',
            'mes_reajuste' => 1,
            'multa_atraso_pct' => 2.00,
            'juros_atraso_pct_dia' => 0.0333,
            'dias_carencia' => 0,
            'multa_rescisoria_pct' => 15.00,
            'desconto_pontualidade_pct' => null,
            'tipo_garantia' => 'seguro_fianca',
            'status' => 'ativo',
            'observacoes' => 'Repasse dividido entre 3 titulares (Ana 50%, João 25%, Maria 25%).',
        ]);

        // Garantia tipo seguro fiança
        Garantia::create([
            'tenant_id' => $tenant1->id,
            'contrato_id' => $contrato3->id,
            'tipo' => 'seguro_fianca',
            'valor' => 2280.00,
            'seguradora' => 'Porto Seguro',
            'numero_apolice' => 'PSF-2024-88712',
            'data_inicio' => '2026-01-01',
            'data_fim' => '2027-01-01',
            'status' => 'ativo',
            'observacoes' => 'Prêmio anual de R$ 2.280, renovação em janeiro.',
        ]);

        // === Contrato 4 — Loja 3, Galeria Central (título capitalização / proprietário direto) ===
        $cafeAroma = User::factory()->create([
            'name' => 'Café Aroma Ltda',
            'email' => 'cafearoma@kimobe.test',
            'password' => Hash::make('password'),
        ]);
        $vinculoCafe = Vinculo::create([
            'user_id' => $cafeAroma->id,
            'tenant_id' => $tenant3->id,
            'papel' => 'inquilino',
            'status' => 'ativo',
        ]);

        $contrato4 = Contrato::create([
            'tenant_id' => $tenant3->id,
            'imovel_id' => $imovel5->id,
            'inquilino_vinculo_id' => $vinculoCafe->id,
            'data_inicio' => '2025-05-01',
            'data_fim' => '2028-05-01',
            'valor_aluguel' => 4100.00,
            'dia_vencimento' => 1,
            'modelo_repasse' => 'por_recebimento',
            'taxa_administracao_pct' => 0.00,
            'taxa_seguro_inadimplencia_pct' => null,
            'indice_reajuste' => 'ipca',
            'mes_reajuste' => 5,
            'multa_atraso_pct' => 2.00,
            'juros_atraso_pct_dia' => 0.0333,
            'dias_carencia' => 0,
            'multa_rescisoria_pct' => 20.00,
            'desconto_pontualidade_pct' => null,
            'tipo_garantia' => 'titulo_capitalizacao',
            'status' => 'ativo',
            'observacoes' => 'Proprietário direto, sem taxa de administração.',
        ]);

        // Garantia tipo título de capitalização
        Garantia::create([
            'tenant_id' => $tenant3->id,
            'contrato_id' => $contrato4->id,
            'tipo' => 'titulo_capitalizacao',
            'valor' => 12300.00,
            'numero_titulo' => 'TC-2024-55443',
            'data_inicio' => '2025-05-01',
            'data_fim' => '2028-05-01',
            'status' => 'ativo',
        ]);
    }
}
