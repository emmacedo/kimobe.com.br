<?php

namespace Database\Seeders;

use App\Models\DadosBancarios;
use App\Models\Imovel;
use App\Models\ImovelFoto;
use App\Models\Tenant;
use App\Models\Titularidade;
use App\Models\User;
use App\Models\Vinculo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ImoveisSeeder extends Seeder
{
    /**
     * Cria cenário de teste com 5 imóveis demonstrando:
     * - Imóveis com titular único
     * - Imóvel com múltiplos titulares (herança com 3 proprietários)
     * - Fotos simuladas
     * - Dados bancários para repasse
     *
     * Depende do TenantSeeder (tenants e users já existem).
     */
    public function run(): void
    {
        // Recuperar entidades do TenantSeeder
        $tenant1 = Tenant::where('nome', 'Imobiliária Horizonte')->firstOrFail();
        $tenant3 = Tenant::where('nome', 'Carlos Mendes Imóveis')->firstOrFail();

        $ana = User::where('email', 'ana@kimobe.test')->firstOrFail();
        $eduardo = User::where('email', 'eduardo@kimobe.test')->firstOrFail();
        $carlos = User::where('email', 'carlos@kimobe.test')->firstOrFail();

        $vinculoAna = Vinculo::where('user_id', $ana->id)->where('tenant_id', $tenant1->id)->firstOrFail();
        $vinculoEduardo = Vinculo::where('user_id', $eduardo->id)->where('tenant_id', $tenant1->id)->firstOrFail();
        $vinculoCarlos = Vinculo::where('user_id', $carlos->id)->where('tenant_id', $tenant3->id)->where('papel', 'proprietario')->firstOrFail();

        // === Dados bancários ===
        $contaAna = DadosBancarios::create([
            'tenant_id' => $tenant1->id,
            'vinculo_id' => $vinculoAna->id,
            'apelido' => 'Conta Itaú principal',
            'banco_codigo' => '341',
            'banco_nome' => 'Itaú Unibanco',
            'agencia' => '1234',
            'conta' => '56789-0',
            'tipo_conta' => 'corrente',
            'pix_tipo' => 'cpf',
            'pix_chave' => '123.456.789-00',
        ]);

        $contaEduardo = DadosBancarios::create([
            'tenant_id' => $tenant1->id,
            'vinculo_id' => $vinculoEduardo->id,
            'apelido' => 'Nubank pessoal',
            'banco_codigo' => '260',
            'banco_nome' => 'Nubank',
            'agencia' => '0001',
            'conta' => '98765-4',
            'tipo_conta' => 'corrente',
            'pix_tipo' => 'email',
            'pix_chave' => 'eduardo@email.com',
        ]);

        $contaCarlos = DadosBancarios::create([
            'tenant_id' => $tenant3->id,
            'vinculo_id' => $vinculoCarlos->id,
            'apelido' => 'Bradesco PJ',
            'banco_codigo' => '237',
            'banco_nome' => 'Bradesco',
            'agencia' => '3456',
            'conta' => '12345-6',
            'tipo_conta' => 'corrente',
            'pix_tipo' => 'cnpj',
            'pix_chave' => '12.345.678/0001-90',
        ]);

        // === Imóvel 1 — Apt 302, Ed. Aurora (Rio de Janeiro) ===
        $imovel1 = Imovel::create([
            'tenant_id' => $tenant1->id,
            'cep' => '22041-080',
            'logradouro' => 'Rua Barata Ribeiro',
            'numero' => '302',
            'complemento' => 'Apt 302, Ed. Aurora',
            'bairro' => 'Copacabana',
            'cidade' => 'Rio de Janeiro',
            'uf' => 'RJ',
            'tipo' => 'apartamento',
            'status' => 'alugado',
            'quartos' => 3,
            'suites' => 1,
            'banheiros' => 2,
            'vagas_garagem' => 1,
            'andar' => 3,
            'area_m2' => 85.00,
            'valor_aluguel_sugerido' => 3500.00,
        ]);

        Titularidade::create([
            'tenant_id' => $tenant1->id,
            'imovel_id' => $imovel1->id,
            'vinculo_id' => $vinculoAna->id,
            'dados_bancarios_id' => $contaAna->id,
            'tipo_titular' => 'pessoa_fisica',
            'papel' => 'responsavel',
            'percentual' => 100.00,
        ]);

        $this->criarFotos($tenant1->id, $imovel1->id, ['Sala de estar', 'Cozinha', 'Quarto principal']);

        // === Imóvel 2 — Casa 14, Cond. Verde (Niterói) ===
        $imovel2 = Imovel::create([
            'tenant_id' => $tenant1->id,
            'cep' => '24360-030',
            'logradouro' => 'Rua Lopes Trovão',
            'numero' => '14',
            'complemento' => 'Casa 14, Cond. Verde',
            'bairro' => 'Icaraí',
            'cidade' => 'Niterói',
            'uf' => 'RJ',
            'tipo' => 'casa',
            'status' => 'alugado',
            'quartos' => 4,
            'suites' => 2,
            'banheiros' => 3,
            'vagas_garagem' => 2,
            'andar' => null,
            'area_m2' => 220.00,
            'valor_aluguel_sugerido' => 6500.00,
        ]);

        Titularidade::create([
            'tenant_id' => $tenant1->id,
            'imovel_id' => $imovel2->id,
            'vinculo_id' => $vinculoEduardo->id,
            'dados_bancarios_id' => $contaEduardo->id,
            'tipo_titular' => 'pessoa_fisica',
            'papel' => 'responsavel',
            'percentual' => 100.00,
        ]);

        $this->criarFotos($tenant1->id, $imovel2->id, ['Fachada', 'Sala de estar']);

        // === Imóvel 3 — Sala 507, Comm Tower (Rio de Janeiro) ===
        $imovel3 = Imovel::create([
            'tenant_id' => $tenant1->id,
            'cep' => '20040-020',
            'logradouro' => 'Av. Rio Branco',
            'numero' => '120',
            'complemento' => 'Sala 507, Comm Tower',
            'bairro' => 'Centro',
            'cidade' => 'Rio de Janeiro',
            'uf' => 'RJ',
            'tipo' => 'sala',
            'status' => 'disponivel',
            'quartos' => null,
            'suites' => null,
            'banheiros' => 1,
            'vagas_garagem' => 1,
            'andar' => 5,
            'area_m2' => 45.00,
            'valor_aluguel_sugerido' => 2200.00,
        ]);

        Titularidade::create([
            'tenant_id' => $tenant1->id,
            'imovel_id' => $imovel3->id,
            'vinculo_id' => $vinculoAna->id,
            'dados_bancarios_id' => $contaAna->id,
            'tipo_titular' => 'pessoa_fisica',
            'papel' => 'responsavel',
            'percentual' => 100.00,
        ]);

        $this->criarFotos($tenant1->id, $imovel3->id, ['Sala comercial']);

        // === Imóvel 4 — Apt herdado, Ed. Família (Rio de Janeiro) — cenário de herança ===
        $imovel4 = Imovel::create([
            'tenant_id' => $tenant1->id,
            'cep' => '22071-060',
            'logradouro' => 'Rua Tonelero',
            'numero' => '45',
            'complemento' => 'Apt 801, Ed. Família',
            'bairro' => 'Copacabana',
            'cidade' => 'Rio de Janeiro',
            'uf' => 'RJ',
            'tipo' => 'apartamento',
            'status' => 'alugado',
            'quartos' => 2,
            'suites' => 0,
            'banheiros' => 1,
            'vagas_garagem' => 1,
            'andar' => 8,
            'area_m2' => 70.00,
            'valor_aluguel_sugerido' => 2800.00,
            'observacoes' => 'Imóvel em processo de inventário. Rateio entre 3 herdeiros.',
        ]);

        // Criar novos users herdeiros com vínculo proprietario no tenant 1
        $joao = User::factory()->create([
            'name' => 'João Costa',
            'email' => 'joao@kimobe.test',
            'password' => Hash::make('password'),
        ]);
        $vinculoJoao = Vinculo::create([
            'user_id' => $joao->id,
            'tenant_id' => $tenant1->id,
            'papel' => 'proprietario',
            'status' => 'ativo',
        ]);

        $maria = User::factory()->create([
            'name' => 'Maria Costa',
            'email' => 'maria@kimobe.test',
            'password' => Hash::make('password'),
        ]);
        $vinculoMaria = Vinculo::create([
            'user_id' => $maria->id,
            'tenant_id' => $tenant1->id,
            'papel' => 'proprietario',
            'status' => 'ativo',
        ]);

        // Ana (50% responsável, inventário) + João (25% observador) + Maria (25% observador)
        Titularidade::create([
            'tenant_id' => $tenant1->id,
            'imovel_id' => $imovel4->id,
            'vinculo_id' => $vinculoAna->id,
            'dados_bancarios_id' => $contaAna->id,
            'tipo_titular' => 'inventario',
            'papel' => 'responsavel',
            'percentual' => 50.00,
        ]);

        Titularidade::create([
            'tenant_id' => $tenant1->id,
            'imovel_id' => $imovel4->id,
            'vinculo_id' => $vinculoJoao->id,
            'dados_bancarios_id' => null,
            'tipo_titular' => 'pessoa_fisica',
            'papel' => 'observador',
            'percentual' => 25.00,
        ]);

        Titularidade::create([
            'tenant_id' => $tenant1->id,
            'imovel_id' => $imovel4->id,
            'vinculo_id' => $vinculoMaria->id,
            'dados_bancarios_id' => null,
            'tipo_titular' => 'pessoa_fisica',
            'papel' => 'observador',
            'percentual' => 25.00,
        ]);

        $this->criarFotos($tenant1->id, $imovel4->id, ['Sala de estar', 'Vista da janela']);

        // === Imóvel 5 — Loja 3, Galeria Central (São Paulo) — tenant Carlos Mendes ===
        $imovel5 = Imovel::create([
            'tenant_id' => $tenant3->id,
            'cep' => '01310-100',
            'logradouro' => 'Av. Paulista',
            'numero' => '1578',
            'complemento' => 'Loja 3, Galeria Central',
            'bairro' => 'Bela Vista',
            'cidade' => 'São Paulo',
            'uf' => 'SP',
            'tipo' => 'loja',
            'status' => 'alugado',
            'quartos' => null,
            'suites' => null,
            'banheiros' => 1,
            'vagas_garagem' => 0,
            'andar' => null,
            'area_m2' => 120.00,
            'valor_aluguel_sugerido' => 8500.00,
        ]);

        Titularidade::create([
            'tenant_id' => $tenant3->id,
            'imovel_id' => $imovel5->id,
            'vinculo_id' => $vinculoCarlos->id,
            'dados_bancarios_id' => $contaCarlos->id,
            'tipo_titular' => 'pessoa_fisica',
            'papel' => 'responsavel',
            'percentual' => 100.00,
        ]);

        $this->criarFotos($tenant3->id, $imovel5->id, ['Fachada da loja']);
    }

    /**
     * Cria fotos simuladas para um imóvel (sem arquivo real, apenas metadados).
     */
    private function criarFotos(int $tenantId, int $imovelId, array $legendas): void
    {
        foreach ($legendas as $ordem => $legenda) {
            $nomeArquivo = strtolower(str_replace(' ', '-', $legenda)) . '.jpg';

            ImovelFoto::create([
                'tenant_id' => $tenantId,
                'imovel_id' => $imovelId,
                'caminho' => "imoveis/{$imovelId}/{$nomeArquivo}",
                'nome_arquivo' => $nomeArquivo,
                'legenda' => $legenda,
                'ordem' => $ordem + 1,
                'mime_type' => 'image/jpeg',
                'tamanho_bytes' => random_int(200000, 3000000),
            ]);
        }
    }
}
