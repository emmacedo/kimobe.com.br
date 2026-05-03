<?php

use App\Models\ContratoAlteracao;

it('registra alteração quando taxa_administracao_pct muda', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);

    $contrato = criarContratoParaTenant($tenant);

    expect(ContratoAlteracao::count())->toBe(0);

    $contrato->update(['taxa_administracao_pct' => 12.5]);

    $alt = ContratoAlteracao::where('contrato_id', $contrato->id)->first();
    expect($alt)->not->toBeNull();
    expect($alt->campo)->toBe('taxa_administracao_pct');
    expect($alt->valor_anterior)->toBe(['taxa_administracao_pct' => '10.00']);
    expect($alt->valor_novo)->toBe(['taxa_administracao_pct' => '12.50']);
    expect($alt->alterado_por_user_id)->toBe($user->id);
});

it('registra alteração quando modelo_repasse muda', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);

    $contrato = criarContratoParaTenant($tenant);
    $contrato->update(['modelo_repasse' => 'garantido']);

    $alt = ContratoAlteracao::where('contrato_id', $contrato->id)
        ->where('campo', 'modelo_repasse')
        ->first();
    expect($alt)->not->toBeNull();
    expect($alt->valor_anterior)->toBe(['modelo_repasse' => 'por_recebimento']);
    expect($alt->valor_novo)->toBe(['modelo_repasse' => 'garantido']);
});

it('registra alteração quando status muda (encerramento)', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);

    $contrato = criarContratoParaTenant($tenant);
    $contrato->update(['status' => 'encerrado']);

    $alt = ContratoAlteracao::where('contrato_id', $contrato->id)
        ->where('campo', 'status')
        ->first();
    expect($alt)->not->toBeNull();
    expect($alt->valor_anterior)->toBe(['status' => 'ativo']);
    expect($alt->valor_novo)->toBe(['status' => 'encerrado']);
});

it('registra alteração quando data_fim muda (renovação)', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);

    $contrato = criarContratoParaTenant($tenant);
    $contrato->update(['data_fim' => '2028-12-31']);

    $alt = ContratoAlteracao::where('contrato_id', $contrato->id)
        ->where('campo', 'data_fim')
        ->first();
    expect($alt)->not->toBeNull();
    expect($alt->valor_anterior)->toBe(['data_fim' => '2027-12-31']);
    expect($alt->valor_novo)->toBe(['data_fim' => '2028-12-31']);
});

it('NÃO registra alteração para campos fora da Camada 3', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);

    $contrato = criarContratoParaTenant($tenant);
    $contrato->update([
        'multa_atraso_pct' => 5,
        'observacoes' => 'Nota qualquer',
        'dias_carencia' => 3,
    ]);

    expect(ContratoAlteracao::where('contrato_id', $contrato->id)->count())->toBe(0);
});

it('registra múltiplas alterações em um único save', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);

    $contrato = criarContratoParaTenant($tenant);
    $contrato->update([
        'taxa_administracao_pct' => 11,
        'status' => 'encerrado',
        'data_fim' => '2026-06-30',
    ]);

    expect(ContratoAlteracao::where('contrato_id', $contrato->id)->count())->toBe(3);
});

it('valor_aluguel não cai aqui (responsabilidade do ContratoReajusteService)', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);

    $contrato = criarContratoParaTenant($tenant);
    $contrato->update(['valor_aluguel' => 2500]);

    expect(ContratoAlteracao::where('contrato_id', $contrato->id)
        ->where('campo', 'valor_aluguel')
        ->count())->toBe(0);
});

it('relação alteracoes() retorna em ordem decrescente', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $this->actingAs($user);

    $contrato = criarContratoParaTenant($tenant);
    $contrato->update(['taxa_administracao_pct' => 11]);
    sleep(1);
    $contrato->update(['status' => 'encerrado']);

    $alteracoes = $contrato->alteracoes;
    expect($alteracoes)->toHaveCount(2);
    expect($alteracoes->first()->campo)->toBe('status');
    expect($alteracoes->last()->campo)->toBe('taxa_administracao_pct');
});
