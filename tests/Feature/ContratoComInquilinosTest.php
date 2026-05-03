<?php

use App\Models\Contrato;
use App\Models\ContratoInquilino;
use App\Models\Imovel;
use App\Models\ItemCobranca;
use App\Models\User;
use App\Models\Vinculo;

function dadosContratoBase(): array
{
    return [
        'data_inicio' => '2026-01-01',
        'data_fim' => '2027-12-31',
        'valor_aluguel' => 1500.00,
        'dia_vencimento' => 10,
        'modelo_repasse' => 'por_recebimento',
        'taxa_administracao_pct' => 10,
        'multa_atraso_pct' => 2,
        'juros_atraso_pct_dia' => 0.0333,
        'dias_carencia' => 0,
        'indice_reajuste' => 'igpm',
        'mes_reajuste' => 1,
        'tipo_garantia' => 'sem_garantia',
    ];
}

function criarInquilinoVinculo($tenantId): Vinculo
{
    return Vinculo::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenantId,
        'papel' => 'inquilino',
        'status' => 'ativo',
    ]);
}

test('admin cria contrato com 1 inquilino principal', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id, 'status' => 'disponivel']);
    $inq = criarInquilinoVinculo($tenant->id);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/contratos', array_merge(dadosContratoBase(), [
            'imovel_id' => $imovel->id,
            'inquilinos' => [
                ['vinculo_id' => $inq->id, 'principal' => true],
            ],
        ]));

    $response->assertSessionHasNoErrors();

    $contrato = Contrato::where('imovel_id', $imovel->id)->first();
    expect($contrato)->not->toBeNull();
    expect($contrato->inquilino_vinculo_id)->toBe($inq->id);
    expect($contrato->inquilinos()->count())->toBe(1);
    expect($contrato->inquilinos()->where('principal', true)->count())->toBe(1);
});

test('admin cria contrato com múltiplos inquilinos (1 principal + 1 co)', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id, 'status' => 'disponivel']);
    $inq1 = criarInquilinoVinculo($tenant->id);
    $inq2 = criarInquilinoVinculo($tenant->id);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/contratos', array_merge(dadosContratoBase(), [
            'imovel_id' => $imovel->id,
            'inquilinos' => [
                ['vinculo_id' => $inq1->id, 'principal' => true],
                ['vinculo_id' => $inq2->id, 'principal' => false],
            ],
        ]));

    $response->assertSessionHasNoErrors();
    $contrato = Contrato::where('imovel_id', $imovel->id)->first();
    expect($contrato->inquilinos()->count())->toBe(2);
    expect($contrato->inquilino_vinculo_id)->toBe($inq1->id); // cache aponta pro principal
});

test('rejeita criação sem inquilinos', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id, 'status' => 'disponivel']);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/contratos', array_merge(dadosContratoBase(), [
            'imovel_id' => $imovel->id,
            'inquilinos' => [],
        ]));

    $response->assertSessionHasErrors(['inquilinos']);
});

test('rejeita 0 ou 2 inquilinos principais', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id, 'status' => 'disponivel']);
    $inq1 = criarInquilinoVinculo($tenant->id);
    $inq2 = criarInquilinoVinculo($tenant->id);

    // 0 principais
    $r0 = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/contratos', array_merge(dadosContratoBase(), [
            'imovel_id' => $imovel->id,
            'inquilinos' => [
                ['vinculo_id' => $inq1->id, 'principal' => false],
                ['vinculo_id' => $inq2->id, 'principal' => false],
            ],
        ]));
    $r0->assertSessionHasErrors(['inquilinos']);

    // 2 principais
    $r2 = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/contratos', array_merge(dadosContratoBase(), [
            'imovel_id' => $imovel->id,
            'inquilinos' => [
                ['vinculo_id' => $inq1->id, 'principal' => true],
                ['vinculo_id' => $inq2->id, 'principal' => true],
            ],
        ]));
    $r2->assertSessionHasErrors(['inquilinos']);
});

test('rejeita criação se imóvel já tem contrato ativo', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id, 'status' => 'disponivel']);
    $inqA = criarInquilinoVinculo($tenant->id);
    $inqB = criarInquilinoVinculo($tenant->id);
    Contrato::factory()->create([
        'tenant_id' => $tenant->id,
        'imovel_id' => $imovel->id,
        'inquilino_vinculo_id' => $inqA->id,
        'status' => 'ativo',
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/contratos', array_merge(dadosContratoBase(), [
            'imovel_id' => $imovel->id,
            'inquilinos' => [['vinculo_id' => $inqB->id, 'principal' => true]],
        ]));

    $response->assertSessionHasErrors(['imovel_id']);
});

test('inquilino vinculado vê o contrato no scope (co-inquilino também)', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);

    // Cria 2 inquilinos: um principal, um co. Ambos devem ver.
    $userPrincipal = User::factory()->create();
    $vPrincipal = Vinculo::create(['user_id' => $userPrincipal->id, 'tenant_id' => $tenant->id, 'papel' => 'inquilino', 'status' => 'ativo']);

    $userCo = User::factory()->create();
    $vCo = Vinculo::create(['user_id' => $userCo->id, 'tenant_id' => $tenant->id, 'papel' => 'inquilino', 'status' => 'ativo']);

    $contrato = Contrato::factory()->create([
        'tenant_id' => $tenant->id,
        'imovel_id' => $imovel->id,
        'inquilino_vinculo_id' => $vPrincipal->id,
        'status' => 'ativo',
    ]);
    ContratoInquilino::factory()->create([
        'tenant_id' => $tenant->id, 'contrato_id' => $contrato->id, 'vinculo_id' => $vPrincipal->id, 'principal' => true,
    ]);
    ContratoInquilino::factory()->create([
        'tenant_id' => $tenant->id, 'contrato_id' => $contrato->id, 'vinculo_id' => $vCo->id, 'principal' => false,
    ]);

    // Co-inquilino vê o contrato.
    $resCo = $this->actingAs($userCo)
        ->withSession(['tenant_id' => $tenant->id])
        ->get('/contratos');

    $resCo->assertOk();
});

test('inquilino de outro tenant é rejeitado', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id, 'status' => 'disponivel']);
    [$outroTenant] = setupTenantComAdmin();
    $inqOutro = criarInquilinoVinculo($outroTenant->id);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/contratos', array_merge(dadosContratoBase(), [
            'imovel_id' => $imovel->id,
            'inquilinos' => [['vinculo_id' => $inqOutro->id, 'principal' => true]],
        ]));

    $response->assertSessionHasErrors(['inquilinos.0.vinculo_id']);
});

test('criação de contrato gera item de cobrança recorrente "Aluguel" automaticamente', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id, 'status' => 'disponivel']);
    $inq = criarInquilinoVinculo($tenant->id);

    $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/contratos', array_merge(dadosContratoBase(), [
            'imovel_id' => $imovel->id,
            'inquilinos' => [['vinculo_id' => $inq->id, 'principal' => true]],
        ]))
        ->assertSessionHasNoErrors();

    $contrato = Contrato::where('imovel_id', $imovel->id)->first();
    $aluguelPai = ItemCobranca::where('contrato_id', $contrato->id)
        ->whereNull('parent_item_id')
        ->where('descricao', 'Aluguel')
        ->first();

    expect($aluguelPai)->not->toBeNull();
    expect($aluguelPai->tipo)->toBe('recorrente');
    expect($aluguelPai->periodicidade)->toBe('mensal');
    expect($aluguelPai->pagante)->toBe('inquilino');
    expect($aluguelPai->recebedor)->toBe('proprietario');
});

test('item de aluguel gerado herda valor_aluguel, dia_vencimento e mes_referencia do contrato', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id, 'status' => 'disponivel']);
    $inq = criarInquilinoVinculo($tenant->id);

    $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/contratos', array_merge(dadosContratoBase(), [
            'imovel_id' => $imovel->id,
            'data_inicio' => '2026-03-15',
            'data_fim' => '2026-12-31',
            'valor_aluguel' => 2500.00,
            'dia_vencimento' => 7,
            'inquilinos' => [['vinculo_id' => $inq->id, 'principal' => true]],
        ]))
        ->assertSessionHasNoErrors();

    $contrato = Contrato::where('imovel_id', $imovel->id)->first();
    $aluguelPai = ItemCobranca::where('contrato_id', $contrato->id)
        ->whereNull('parent_item_id')
        ->where('descricao', 'Aluguel')
        ->first();

    expect((float) $aluguelPai->valor_unitario)->toBe(2500.00);
    expect($aluguelPai->dia_vencimento)->toBe(7);
    expect($aluguelPai->mes_referencia)->toBe('03/2026');
});

test('série de aluguel pré-gera ocorrências até data_fim do contrato', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id, 'status' => 'disponivel']);
    $inq = criarInquilinoVinculo($tenant->id);

    // 24 meses: jan/2026 a dez/2027
    $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/contratos', array_merge(dadosContratoBase(), [
            'imovel_id' => $imovel->id,
            'data_inicio' => '2026-01-01',
            'data_fim' => '2027-12-31',
            'inquilinos' => [['vinculo_id' => $inq->id, 'principal' => true]],
        ]))
        ->assertSessionHasNoErrors();

    $contrato = Contrato::where('imovel_id', $imovel->id)->first();
    $totalOcorrencias = ItemCobranca::where('contrato_id', $contrato->id)
        ->where('descricao', 'Aluguel')
        ->count();

    expect($totalOcorrencias)->toBe(24);
});

test('contrato com data_inicio retroativa gera ocorrências dos meses passados', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id, 'status' => 'disponivel']);
    $inq = criarInquilinoVinculo($tenant->id);

    $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/contratos', array_merge(dadosContratoBase(), [
            'imovel_id' => $imovel->id,
            'data_inicio' => '2025-10-01',
            'data_fim' => '2026-03-31',
            'inquilinos' => [['vinculo_id' => $inq->id, 'principal' => true]],
        ]))
        ->assertSessionHasNoErrors();

    $contrato = Contrato::where('imovel_id', $imovel->id)->first();

    // Deve haver ocorrência com mes_referencia=10/2025 (passada)
    $temMesPassado = ItemCobranca::where('contrato_id', $contrato->id)
        ->where('descricao', 'Aluguel')
        ->where('mes_referencia', '10/2025')
        ->exists();

    expect($temMesPassado)->toBeTrue();
});
