<?php

use App\Models\Contrato;
use App\Models\Imovel;
use App\Models\ItemCobranca;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\ContratoReajusteService;
use App\Services\ItemCobrancaService;
use App\Services\TenantService;

it('auto-gera item de cobrança de natureza taxa_admin ao criar contrato', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $this->actingAs($admin);
    app(TenantService::class)->setTenant($tenant);

    $imovel = Imovel::factory()->create([
        'tenant_id' => $tenant->id,
        'status' => 'disponivel',
    ]);
    $vincInq = Vinculo::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenant->id,
        'papel' => 'inquilino',
        'status' => 'ativo',
    ]);

    $response = $this->post('/contratos', [
        'imovel_id' => $imovel->id,
        'inquilinos' => [['vinculo_id' => $vincInq->id, 'principal' => true]],
        'data_inicio' => '2026-06-01',
        'data_fim' => '2027-05-31',
        'valor_aluguel' => 2000.00,
        'dia_vencimento' => 10,
        'modelo_repasse' => 'por_recebimento',
        'taxa_administracao_pct' => 10,
        'indice_reajuste' => 'igpm',
        'mes_reajuste' => 6,
        'multa_atraso_pct' => 2,
        'juros_atraso_pct_dia' => 0.0333,
        'dias_carencia' => 0,
        'tipo_garantia' => 'sem_garantia',
    ]);

    $response->assertSessionHasNoErrors();

    $contrato = Contrato::latest('id')->first();

    $aluguel = ItemCobranca::where('contrato_id', $contrato->id)
        ->where('natureza', 'aluguel')
        ->whereNull('parent_item_id')
        ->first();
    expect($aluguel)->not->toBeNull();
    expect($aluguel->descricao)->toBe('Aluguel');

    $taxa = ItemCobranca::where('contrato_id', $contrato->id)
        ->where('natureza', 'taxa_admin')
        ->whereNull('parent_item_id')
        ->first();
    expect($taxa)->not->toBeNull();
    expect($taxa->descricao)->toBe('Taxa administrativa');
    expect($taxa->pagante)->toBe('proprietario');
    expect($taxa->recebedor)->toBe('administradora');
    expect($taxa->tipo)->toBe('recorrente');
    expect((float) $taxa->valor_unitario)->toBe(200.0); // 2000 * 10%
    expect($taxa->dia_vencimento)->toBe(10);
    expect($taxa->mes_referencia)->toBe('06/2026');
    expect((bool) $taxa->visivel_inquilino)->toBeFalse();
});

it('pré-gera ocorrências mensais de taxa_admin até o fim do contrato', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $this->actingAs($admin);
    app(TenantService::class)->setTenant($tenant);

    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id, 'status' => 'disponivel']);
    $vincInq = Vinculo::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenant->id, 'papel' => 'inquilino', 'status' => 'ativo',
    ]);

    $this->post('/contratos', [
        'imovel_id' => $imovel->id,
        'inquilinos' => [['vinculo_id' => $vincInq->id, 'principal' => true]],
        'data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31',
        'valor_aluguel' => 1500.00, 'dia_vencimento' => 5,
        'modelo_repasse' => 'por_recebimento', 'taxa_administracao_pct' => 8,
        'indice_reajuste' => 'igpm', 'mes_reajuste' => 1,
        'multa_atraso_pct' => 2, 'juros_atraso_pct_dia' => 0.0333,
        'dias_carencia' => 0, 'tipo_garantia' => 'sem_garantia',
    ])->assertSessionHasNoErrors();

    $contrato = Contrato::latest('id')->first();

    $totalTaxa = ItemCobranca::where('contrato_id', $contrato->id)
        ->where('natureza', 'taxa_admin')
        ->count();
    expect($totalTaxa)->toBe(12); // jan a dez

    $valorEsperado = 120.0; // 1500 * 8%
    ItemCobranca::where('contrato_id', $contrato->id)
        ->where('natureza', 'taxa_admin')
        ->each(function (ItemCobranca $item) use ($valorEsperado) {
            expect((float) $item->valor_unitario)->toBe($valorEsperado);
        });
});

it('reajuste de aluguel não atinge itens de natureza taxa_admin', function () {
    [$tenant] = setupTenantComAdmin();
    app(TenantService::class)->setTenant($tenant);

    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $vincInq = Vinculo::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenant->id, 'papel' => 'inquilino', 'status' => 'ativo',
    ]);
    $contrato = Contrato::create([
        'imovel_id' => $imovel->id,
        'inquilino_vinculo_id' => $vincInq->id,
        'data_inicio' => '2026-01-01', 'data_fim' => '2026-12-31',
        'valor_aluguel' => 2000.00, 'dia_vencimento' => 5,
        'modelo_repasse' => 'por_recebimento', 'taxa_administracao_pct' => 10,
        'indice_reajuste' => 'igpm', 'mes_reajuste' => 1,
        'multa_atraso_pct' => 2, 'juros_atraso_pct_dia' => 0.0333,
        'dias_carencia' => 0, 'tipo_garantia' => 'sem_garantia', 'status' => 'ativo',
    ]);

    $itemService = app(ItemCobrancaService::class);
    $itemService->criar($contrato, [
        'descricao' => 'Aluguel', 'natureza' => 'aluguel',
        'pagante' => 'inquilino', 'recebedor' => 'proprietario',
        'tipo' => 'recorrente', 'periodicidade' => 'mensal',
        'valor_unitario' => 2000.00, 'mes_referencia' => '01/2026',
    ]);
    $itemService->criar($contrato, [
        'descricao' => 'Taxa administrativa', 'natureza' => 'taxa_admin',
        'pagante' => 'proprietario', 'recebedor' => 'administradora',
        'tipo' => 'recorrente', 'periodicidade' => 'mensal',
        'valor_unitario' => 200.00, 'mes_referencia' => '01/2026',
    ]);

    app(ContratoReajusteService::class)->aplicar($contrato, [
        'valor_novo' => 2400.00,
        'data_aplicacao' => '2026-04-01',
        'indice_usado' => 'manual',
        'origem' => 'aditivo',
    ]);

    $valorTaxaAbril = ItemCobranca::where('contrato_id', $contrato->id)
        ->where('natureza', 'taxa_admin')
        ->where('mes_referencia', '04/2026')
        ->value('valor_unitario');
    $valorAluguelAbril = ItemCobranca::where('contrato_id', $contrato->id)
        ->where('natureza', 'aluguel')
        ->where('mes_referencia', '04/2026')
        ->value('valor_unitario');

    expect((float) $valorTaxaAbril)->toBe(200.0); // não muda
    expect((float) $valorAluguelAbril)->toBe(2400.0); // reajustado
});

it('não cria item taxa_admin quando a taxa configurada resulta em 0', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $this->actingAs($admin);
    app(TenantService::class)->setTenant($tenant);

    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id, 'status' => 'disponivel']);
    $vincInq = Vinculo::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenant->id, 'papel' => 'inquilino', 'status' => 'ativo',
    ]);

    $this->post('/contratos', [
        'imovel_id' => $imovel->id,
        'inquilinos' => [['vinculo_id' => $vincInq->id, 'principal' => true]],
        'data_inicio' => '2026-06-01', 'data_fim' => '2027-05-31',
        'valor_aluguel' => 2000.00, 'dia_vencimento' => 10,
        'modelo_repasse' => 'por_recebimento', 'taxa_administracao_pct' => 0,
        'indice_reajuste' => 'igpm', 'mes_reajuste' => 6,
        'multa_atraso_pct' => 2, 'juros_atraso_pct_dia' => 0.0333,
        'dias_carencia' => 0, 'tipo_garantia' => 'sem_garantia',
    ])->assertSessionHasNoErrors();

    $contrato = Contrato::latest('id')->first();

    $totalTaxa = ItemCobranca::where('contrato_id', $contrato->id)
        ->where('natureza', 'taxa_admin')
        ->count();
    expect($totalTaxa)->toBe(0);

    // Aluguel ainda foi gerado normalmente
    $totalAluguel = ItemCobranca::where('contrato_id', $contrato->id)
        ->where('natureza', 'aluguel')
        ->count();
    expect($totalAluguel)->toBeGreaterThan(0);
});
