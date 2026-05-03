<?php

use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\Imovel;
use App\Models\ItemCobranca;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\FaturaService;
use App\Services\ItemCobrancaService;
use App\Services\TenantService;

function criarContratoParaFatura(string $dataInicio = '2026-01-01', string $dataFim = '2026-12-31', string $modelo = 'por_recebimento'): Contrato
{
    $tenant = Tenant::factory()->create();
    app(TenantService::class)->setTenant($tenant);

    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);

    $vinculo = Vinculo::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenant->id,
        'papel' => 'inquilino',
        'status' => 'ativo',
    ]);

    return Contrato::create([
        'imovel_id' => $imovel->id,
        'inquilino_vinculo_id' => $vinculo->id,
        'data_inicio' => $dataInicio,
        'data_fim' => $dataFim,
        'valor_aluguel' => 2000.00,
        'dia_vencimento' => 5,
        'modelo_repasse' => $modelo,
        'taxa_administracao_pct' => 10,
        'taxa_seguro_inadimplencia_pct' => $modelo === 'garantido' ? 4 : null,
        'indice_reajuste' => 'igpm',
        'mes_reajuste' => 1,
        'multa_atraso_pct' => 2,
        'juros_atraso_pct_dia' => 0.0333,
        'dias_carencia' => 0,
        'multa_rescisoria_pct' => null,
        'desconto_pontualidade_pct' => null,
        'tipo_garantia' => 'sem_garantia',
        'status' => 'ativo',
    ]);
}

it('gera fatura individual e concilia itens pendentes do mês', function () {
    $contrato = criarContratoParaFatura('2026-01-01', '2026-12-31');

    // Pré-gera aluguel mensal e condomínio mensal
    $itemService = app(ItemCobrancaService::class);
    $itemService->criar($contrato, [
        'descricao' => 'Aluguel',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 2000.00,
        'mes_referencia' => '01/2026',
    ]);
    $itemService->criar($contrato, [
        'descricao' => 'Condomínio',
        'pagante' => 'inquilino',
        'recebedor' => 'administradora',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 800.00,
        'mes_referencia' => '01/2026',
    ]);

    $faturaService = app(FaturaService::class);
    $fatura = $faturaService->gerarFaturaIndividual($contrato, '03/2026');

    // 2 itens conciliados em 03/2026: aluguel + condomínio = 2800
    expect($fatura->valor_total)->toBe('2800.00');
    $itensConciliados = ItemCobranca::where('fatura_id', $fatura->id)->get();
    expect($itensConciliados)->toHaveCount(2);
    foreach ($itensConciliados as $item) {
        expect($item->status)->toBe('conciliado');
        expect($item->mes_referencia)->toBe('03/2026');
    }
});

it('não toca em itens de outro mês', function () {
    $contrato = criarContratoParaFatura('2026-01-01', '2026-12-31');
    $itemService = app(ItemCobrancaService::class);
    $itemService->criar($contrato, [
        'descricao' => 'Aluguel',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 1500.00,
        'mes_referencia' => '01/2026',
    ]);

    $faturaService = app(FaturaService::class);
    $fatura = $faturaService->gerarFaturaIndividual($contrato, '03/2026');

    // Item de 03/2026 conciliado
    expect(ItemCobranca::where('fatura_id', $fatura->id)->count())->toBe(1);

    // Item de 04/2026 ainda pendente
    $abril = ItemCobranca::where('mes_referencia', '04/2026')->first();
    expect($abril->status)->toBe('pendente');
    expect($abril->fatura_id)->toBeNull();
});

it('não toca em itens de outro contrato', function () {
    $contrato1 = criarContratoParaFatura();
    $tenant = $contrato1->tenant;

    // Segundo contrato no mesmo tenant
    $imovel2 = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $vinculo2 = Vinculo::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenant->id,
        'papel' => 'inquilino',
        'status' => 'ativo',
    ]);
    $contrato2 = Contrato::create([
        'imovel_id' => $imovel2->id,
        'inquilino_vinculo_id' => $vinculo2->id,
        'data_inicio' => '2026-01-01',
        'data_fim' => '2026-12-31',
        'valor_aluguel' => 3000.00,
        'dia_vencimento' => 10,
        'modelo_repasse' => 'por_recebimento',
        'taxa_administracao_pct' => 10,
        'taxa_seguro_inadimplencia_pct' => null,
        'indice_reajuste' => 'igpm',
        'mes_reajuste' => 1,
        'multa_atraso_pct' => 2,
        'juros_atraso_pct_dia' => 0.0333,
        'dias_carencia' => 0,
        'multa_rescisoria_pct' => null,
        'desconto_pontualidade_pct' => null,
        'tipo_garantia' => 'sem_garantia',
        'status' => 'ativo',
    ]);

    $itemService = app(ItemCobrancaService::class);
    $itemService->criar($contrato1, [
        'descricao' => 'Aluguel C1',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 2000,
        'mes_referencia' => '03/2026',
    ]);
    $itemService->criar($contrato2, [
        'descricao' => 'Aluguel C2',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 3000,
        'mes_referencia' => '03/2026',
    ]);

    $faturaService = app(FaturaService::class);
    $fatura1 = $faturaService->gerarFaturaIndividual($contrato1, '03/2026');

    // Apenas itens do contrato1 são conciliados
    expect(ItemCobranca::where('fatura_id', $fatura1->id)->count())->toBe(1);
    expect(ItemCobranca::where('contrato_id', $contrato2->id)->where('status', 'pendente')->count())->toBeGreaterThan(0);
});

it('ignora itens já conciliados ou cancelados', function () {
    $contrato = criarContratoParaFatura('2026-01-01', '2026-06-30');
    $itemService = app(ItemCobrancaService::class);
    $itemService->criar($contrato, [
        'descricao' => 'Aluguel',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 2000,
        'mes_referencia' => '01/2026',
    ]);

    // Marca o de 03/2026 como cancelado
    ItemCobranca::where('mes_referencia', '03/2026')->update(['status' => 'cancelado']);

    $faturaService = app(FaturaService::class);
    $fatura = $faturaService->gerarFaturaIndividual($contrato, '03/2026');

    // Nenhum item conciliado (o único do mês foi cancelado)
    expect(ItemCobranca::where('fatura_id', $fatura->id)->count())->toBe(0);
    expect((float) $fatura->valor_total)->toBe(0.0);
});

it('geração mensal cria faturas apenas para contratos sem fatura no mês', function () {
    $contrato = criarContratoParaFatura('2026-01-01', '2026-12-31');
    $itemService = app(ItemCobrancaService::class);
    $itemService->criar($contrato, [
        'descricao' => 'Aluguel',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 1800,
        'mes_referencia' => '01/2026',
    ]);

    $faturaService = app(FaturaService::class);
    $resultado1 = $faturaService->gerarFaturasMensais('05/2026');

    expect($resultado1['quantidade'])->toBe(1);
    expect((float) $resultado1['valor_total'])->toBe(1800.0);

    // Segunda chamada não cria fatura duplicada
    $resultado2 = $faturaService->gerarFaturasMensais('05/2026');
    expect($resultado2['quantidade'])->toBe(0);
});

it('soma corretamente itens com pagantes diversos no valor_total', function () {
    $contrato = criarContratoParaFatura('2026-01-01', '2026-12-31');
    $itemService = app(ItemCobrancaService::class);

    // Aluguel (inquilino paga)
    $itemService->criar($contrato, [
        'descricao' => 'Aluguel',
        'pagante' => 'inquilino', 'recebedor' => 'proprietario',
        'tipo' => 'avulso',
        'valor_unitario' => 2000,
        'mes_referencia' => '04/2026',
    ]);
    // Frete admin (proprietário paga, admin recebe — ajuste de repasse)
    $itemService->criar($contrato, [
        'descricao' => 'Frete documentos',
        'pagante' => 'proprietario', 'recebedor' => 'administradora',
        'tipo' => 'avulso',
        'valor_unitario' => 50,
        'mes_referencia' => '04/2026',
    ]);

    $faturaService = app(FaturaService::class);
    $fatura = $faturaService->gerarFaturaIndividual($contrato, '04/2026');

    // Soma de TODOS os itens conciliados (modelo simples por enquanto)
    expect((float) $fatura->valor_total)->toBe(2050.0);
    expect(ItemCobranca::where('fatura_id', $fatura->id)->count())->toBe(2);
});
