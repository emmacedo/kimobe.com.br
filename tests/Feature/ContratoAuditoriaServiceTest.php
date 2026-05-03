<?php

use App\Services\ContratoAuditoriaService;
use App\Services\ContratoReajusteService;

it('monta timeline com reajustes, alterações e atividades em ordem decrescente', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);

    $contrato = criarContratoParaTenant($tenant, '2026-01-01', '2027-12-31');

    // 1. atividade do activitylog (campo coberto pela camada 2)
    $contrato->update(['multa_atraso_pct' => 5]);
    sleep(1);

    // 2. alteração crítica (Camada 3 — observer)
    $contrato->update(['taxa_administracao_pct' => 12]);
    sleep(1);

    // 3. reajuste (Camada 3 — service)
    app(ContratoReajusteService::class)->aplicar($contrato, [
        'valor_novo' => 2400,
        'data_aplicacao' => '2026-06-01',
        'indice_usado' => 'igpm',
        'origem' => 'reajuste_anual',
    ]);

    $timeline = app(ContratoAuditoriaService::class)->montarTimeline($contrato);

    // 1 created (activitylog) + 1 multa updated (activitylog) + 1 alteracao + 1 reajuste = 4
    expect($timeline)->toHaveCount(4);
    // Mais recente primeiro: reajuste, alteracao, atividade(updated multa), atividade(created)
    expect($timeline->pluck('tipo')->all())->toBe(['reajuste', 'alteracao', 'atividade', 'atividade']);
});

it('separa eventos por tipo corretamente', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);

    $contrato = criarContratoParaTenant($tenant);
    app(ContratoReajusteService::class)->aplicar($contrato, [
        'valor_novo' => 2200,
        'data_aplicacao' => '2026-06-01',
        'indice_usado' => 'manual',
        'origem' => 'aditivo',
    ]);

    $timeline = app(ContratoAuditoriaService::class)->montarTimeline($contrato);
    $reajustes = $timeline->where('tipo', 'reajuste');

    expect($reajustes)->toHaveCount(1);
    $r = $reajustes->first();
    expect($r['titulo'])->toBe('Reajuste de aluguel');
    expect($r['usuario'])->toBe($user->name);
    expect($r['extra']['origem'])->toBe('aditivo');
    expect($r['extra']['indice_usado'])->toBe('manual');
});

it('inclui atividades do activitylog na timeline', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);

    $contrato = criarContratoParaTenant($tenant);
    sleep(1);
    $contrato->update(['observacoes' => 'Nova nota interna']);

    $timeline = app(ContratoAuditoriaService::class)->montarTimeline($contrato);
    $atividades = $timeline->where('tipo', 'atividade');

    expect($atividades->count())->toBe(2);
    // Mais recente: a do update
    $primeiraAtv = $atividades->first();
    expect($primeiraAtv['titulo'])->toBe('Contrato atualizado');
    expect($primeiraAtv['usuario'])->toBe($user->name);
});

it('contrato recém-criado tem apenas a atividade de criação', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);

    $contrato = criarContratoParaTenant($tenant);

    $timeline = app(ContratoAuditoriaService::class)->montarTimeline($contrato);

    expect($timeline)->toHaveCount(1);
    expect($timeline->first()['tipo'])->toBe('atividade');
    expect($timeline->first()['titulo'])->toBe('Contrato criado');
});
