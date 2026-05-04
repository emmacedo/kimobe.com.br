<?php

use App\Models\Contrato;
use App\Models\DadosBancarios;
use App\Models\Fatura;
use App\Models\Imovel;
use App\Models\Repasse;
use App\Models\Tenant;
use App\Models\Titularidade;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\ItemCobrancaService;
use App\Services\TenantService;

function criarContratoComTitular(Tenant $tenant, string $nomeTitular = 'Titular Teste', float $percentual = 100, bool $comSeguro = false): Contrato
{
    app(TenantService::class)->setTenant($tenant);

    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $vincTitular = Vinculo::create([
        'user_id' => User::factory()->create(['name' => $nomeTitular])->id,
        'tenant_id' => $tenant->id,
        'papel' => 'proprietario',
        'status' => 'ativo',
    ]);
    $banco = DadosBancarios::factory()->create([
        'tenant_id' => $tenant->id,
        'vinculo_id' => $vincTitular->id,
    ]);
    Titularidade::create([
        'tenant_id' => $tenant->id,
        'imovel_id' => $imovel->id,
        'vinculo_id' => $vincTitular->id,
        'dados_bancarios_id' => $banco->id,
        'tipo_titular' => 'pessoa_fisica',
        'papel' => 'responsavel',
        'percentual' => $percentual,
    ]);

    $vincInquilino = Vinculo::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenant->id,
        'papel' => 'inquilino',
        'status' => 'ativo',
    ]);

    $contrato = Contrato::create([
        'imovel_id' => $imovel->id,
        'inquilino_vinculo_id' => $vincInquilino->id,
        'data_inicio' => '2026-01-01',
        'data_fim' => '2026-12-31',
        'valor_aluguel' => 2000.00,
        'dia_vencimento' => 5,
        'modelo_repasse' => $comSeguro ? 'garantido' : 'por_recebimento',
        'taxa_administracao_pct' => 10,
        'taxa_seguro_inadimplencia_pct' => $comSeguro ? 4 : null,
        'indice_reajuste' => 'igpm',
        'mes_reajuste' => 1,
        'multa_atraso_pct' => 2,
        'juros_atraso_pct_dia' => 0.0333,
        'dias_carencia' => 0,
        'tipo_garantia' => 'sem_garantia',
        'status' => 'ativo',
    ]);

    // Geração automática que ContratoController::store faria — replicada aqui
    // para os testes que criam contratos diretamente via Contrato::create.
    $itemService = app(ItemCobrancaService::class);
    $itemService->criar($contrato, [
        'descricao' => 'Aluguel',
        'natureza' => 'aluguel',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => $contrato->valor_aluguel,
        'dia_vencimento' => $contrato->dia_vencimento,
        'mes_referencia' => $contrato->data_inicio->format('m/Y'),
        'visivel_inquilino' => true,
    ]);
    $itemService->criar($contrato, [
        'descricao' => 'Taxa administrativa',
        'natureza' => 'taxa_admin',
        'pagante' => 'proprietario',
        'recebedor' => 'administradora',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => $contrato->valorTaxaAdministrativa(),
        'dia_vencimento' => $contrato->dia_vencimento,
        'mes_referencia' => $contrato->data_inicio->format('m/Y'),
        'visivel_inquilino' => false,
    ]);

    return $contrato;
}

it('calcula preview de repasse pela fórmula bruto - taxa_admin - seguro', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $contrato = criarContratoComTitular($tenant, 'Carlos Único', 100, comSeguro: true);

    // bruto = 2000 * 1.0 = 2000
    // taxa_admin = 2000 * 10% = 200
    // seguro = 2000 * 4% = 80
    // liquido = 2000 - 200 - 80 = 1720
    $response = $this->actingAs($admin)->get('/financeiro/repasses?mes=2026-03');

    $linhas = $response->viewData('page')['props']['linhas'];
    expect($linhas)->toHaveCount(1);
    expect($linhas[0]['titular'])->toBe('Carlos Único');
    expect($linhas[0]['status'])->toBe('preview');
    expect($linhas[0]['is_preview'])->toBeTrue();
    expect($linhas[0]['fatura_id'])->toBeNull();
    expect((float) $linhas[0]['valor_liquido'])->toBe(1720.0);
});

it('preview sem seguro de inadimplência só desconta taxa de administração', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    criarContratoComTitular($tenant, 'Sem Seguro', 100, comSeguro: false);

    // 2000 - 200 (10%) - 0 = 1800
    $response = $this->actingAs($admin)->get('/financeiro/repasses?mes=2026-03');
    $linhas = $response->viewData('page')['props']['linhas'];

    expect((float) $linhas[0]['valor_liquido'])->toBe(1800.0);
});

it('soma o líquido de múltiplas titularidades em uma única linha por contrato', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $contrato = criarContratoComTitular($tenant, 'Sócio A', 60, comSeguro: false);

    // Adiciona segundo titular ao MESMO imóvel
    $imovel = $contrato->imovel;
    $vincSocioB = Vinculo::create([
        'user_id' => User::factory()->create(['name' => 'Sócio B'])->id,
        'tenant_id' => $tenant->id,
        'papel' => 'proprietario',
        'status' => 'ativo',
    ]);
    $banco = DadosBancarios::factory()->create([
        'tenant_id' => $tenant->id,
        'vinculo_id' => $vincSocioB->id,
    ]);
    Titularidade::create([
        'tenant_id' => $tenant->id,
        'imovel_id' => $imovel->id,
        'vinculo_id' => $vincSocioB->id,
        'dados_bancarios_id' => $banco->id,
        'tipo_titular' => 'pessoa_fisica',
        'papel' => 'observador',
        'percentual' => 40,
    ]);

    // Sócio A: 2000 * 0.6 = 1200; admin = 120; liquido = 1080
    // Sócio B: 2000 * 0.4 =  800; admin =  80; liquido =  720
    // Total: 1800
    $response = $this->actingAs($admin)->get('/financeiro/repasses?mes=2026-03');
    $linhas = $response->viewData('page')['props']['linhas'];

    expect($linhas)->toHaveCount(1);
    expect((float) $linhas[0]['valor_liquido'])->toBe(1800.0);
    expect($linhas[0]['titular'])->toBe('Sócio A'); // titular responsável em destaque
    expect($linhas[0]['qtd_titularidades'])->toBe(2);
});

it('retorna repasses persistidos agregados quando existem', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $contrato = criarContratoComTitular($tenant);

    $fatura = Fatura::create([
        'tenant_id' => $tenant->id,
        'contrato_id' => $contrato->id,
        'referencia' => '03/2026',
        'valor_total' => 2000.00,
        'data_vencimento' => '2026-03-05',
        'tipo_geracao' => 'manual',
        'status' => 'pendente',
    ]);

    $titularidade = $contrato->imovel->titularidades->first();
    Repasse::create([
        'tenant_id' => $tenant->id,
        'fatura_id' => $fatura->id,
        'titularidade_id' => $titularidade->id,
        'valor_aluguel_bruto' => 2000.00,
        'taxa_administracao_valor' => 200.00,
        'taxa_seguro_inadimplencia_valor' => null,
        'valor_liquido' => 1800.00,
        'data_prevista' => '2026-03-12',
        'status' => 'pendente',
    ]);

    $response = $this->actingAs($admin)->get('/financeiro/repasses?mes=2026-03');
    $linhas = $response->viewData('page')['props']['linhas'];

    expect($linhas[0]['fatura_id'])->toBe($fatura->id);
    expect($linhas[0]['is_preview'])->toBeFalse();
    expect($linhas[0]['status'])->toBe('pendente');
    expect((float) $linhas[0]['valor_liquido'])->toBe(1800.0);
});

it('ignora contratos inativos', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $contrato = criarContratoComTitular($tenant);
    $contrato->update(['status' => 'encerrado']);

    $response = $this->actingAs($admin)->get('/financeiro/repasses?mes=2026-03');

    expect($response->viewData('page')['props']['linhas'])->toBeEmpty();
});

it('filtra por busca textual no nome do titular', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    criarContratoComTitular($tenant, 'Ana Costa');
    criarContratoComTitular($tenant, 'Pedro Lima');

    $response = $this->actingAs($admin)->get('/financeiro/repasses?mes=2026-03&busca=Ana');
    $linhas = $response->viewData('page')['props']['linhas'];

    expect($linhas)->toHaveCount(1);
    expect($linhas[0]['titular'])->toBe('Ana Costa');
});
