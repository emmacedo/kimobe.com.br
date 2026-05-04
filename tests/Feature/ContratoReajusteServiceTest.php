<?php

use App\Models\ItemCobranca;
use App\Services\ContratoReajusteService;
use App\Services\ItemCobrancaService;

it('aplica reajuste e registra histórico estruturado', function () {
    $contrato = criarContratoParaItens('2026-01-01', '2027-12-31');

    $reajuste = app(ContratoReajusteService::class)->aplicar($contrato, [
        'valor_novo' => 2200.00,
        'data_aplicacao' => '2027-01-01',
        'indice_usado' => 'igpm',
        'origem' => 'reajuste_anual',
        'observacao' => 'Aplicação anual conforme cláusula 5ª.',
    ]);

    expect($reajuste->valor_anterior)->toEqual('2000.00');
    expect($reajuste->valor_novo)->toEqual('2200.00');
    expect((float) $reajuste->percentual)->toBe(10.0);
    expect($reajuste->indice_usado)->toBe('igpm');
    expect($reajuste->origem)->toBe('reajuste_anual');

    $contrato->refresh();
    expect((float) $contrato->valor_aluguel)->toBe(2200.0);
});

it('propaga novo valor apenas para itens de aluguel pendentes a partir da data', function () {
    $contrato = criarContratoParaItens('2026-01-01', '2026-12-31');

    app(ItemCobrancaService::class)->criar($contrato, [
        'descricao' => 'Aluguel',
        'natureza' => 'aluguel',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 2000.00,
        'mes_referencia' => '01/2026',
    ]);

    // Concilia janeiro (vira imutável)
    ItemCobranca::where('contrato_id', $contrato->id)
        ->where('mes_referencia', '01/2026')
        ->update(['status' => 'conciliado']);

    app(ContratoReajusteService::class)->aplicar($contrato, [
        'valor_novo' => 2400.00,
        'data_aplicacao' => '2026-04-01',
        'indice_usado' => 'manual',
        'origem' => 'aditivo',
    ]);

    $valorJaneiro = ItemCobranca::where('contrato_id', $contrato->id)
        ->where('mes_referencia', '01/2026')
        ->value('valor_unitario');

    $valorFevereiro = ItemCobranca::where('contrato_id', $contrato->id)
        ->where('mes_referencia', '02/2026')
        ->value('valor_unitario');

    $valorAbril = ItemCobranca::where('contrato_id', $contrato->id)
        ->where('mes_referencia', '04/2026')
        ->value('valor_unitario');

    $valorDezembro = ItemCobranca::where('contrato_id', $contrato->id)
        ->where('mes_referencia', '12/2026')
        ->value('valor_unitario');

    expect((float) $valorJaneiro)->toBe(2000.0); // conciliado, intocado
    expect((float) $valorFevereiro)->toBe(2000.0); // pendente mas anterior à data
    expect((float) $valorAbril)->toBe(2400.0); // pendente, na data
    expect((float) $valorDezembro)->toBe(2400.0); // pendente, posterior
});

it('rejeita valor zero ou negativo', function () {
    $contrato = criarContratoParaItens();

    expect(fn () => app(ContratoReajusteService::class)->aplicar($contrato, [
        'valor_novo' => 0,
        'data_aplicacao' => '2026-06-01',
        'indice_usado' => 'manual',
        'origem' => 'correcao',
    ]))->toThrow(DomainException::class);
});

it('rejeita data fora do intervalo do contrato', function () {
    $contrato = criarContratoParaItens('2026-01-01', '2026-12-31');

    expect(fn () => app(ContratoReajusteService::class)->aplicar($contrato, [
        'valor_novo' => 2100,
        'data_aplicacao' => '2025-06-01',
        'indice_usado' => 'manual',
        'origem' => 'correcao',
    ]))->toThrow(DomainException::class);

    expect(fn () => app(ContratoReajusteService::class)->aplicar($contrato, [
        'valor_novo' => 2100,
        'data_aplicacao' => '2027-06-01',
        'indice_usado' => 'manual',
        'origem' => 'correcao',
    ]))->toThrow(DomainException::class);
});

it('relação reajustes() retorna histórico em ordem decrescente de data_aplicacao', function () {
    $contrato = criarContratoParaItens('2026-01-01', '2027-12-31');

    app(ContratoReajusteService::class)->aplicar($contrato, [
        'valor_novo' => 2100,
        'data_aplicacao' => '2026-06-01',
        'indice_usado' => 'igpm',
        'origem' => 'reajuste_anual',
    ]);

    app(ContratoReajusteService::class)->aplicar($contrato, [
        'valor_novo' => 2300,
        'data_aplicacao' => '2027-01-01',
        'indice_usado' => 'igpm',
        'origem' => 'reajuste_anual',
    ]);

    $historico = $contrato->reajustes;

    expect($historico)->toHaveCount(2);
    expect($historico->first()->data_aplicacao->format('Y-m-d'))->toBe('2027-01-01');
    expect($historico->last()->data_aplicacao->format('Y-m-d'))->toBe('2026-06-01');
});

it('contrato_reajuste preserva tenant_id automaticamente', function () {
    $contrato = criarContratoParaItens();

    $reajuste = app(ContratoReajusteService::class)->aplicar($contrato, [
        'valor_novo' => 2100,
        'data_aplicacao' => '2026-06-01',
        'indice_usado' => 'manual',
        'origem' => 'correcao',
    ]);

    expect($reajuste->tenant_id)->toBe($contrato->tenant_id);
});
