<?php

use App\Models\Contrato;
use App\Models\EntidadeExterna;
use App\Models\Imovel;
use App\Models\ItemCobranca;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\ItemCobrancaService;
use App\Services\TenantService;

function criarContratoParaItens(string $dataInicio = '2026-01-01', string $dataFim = '2026-12-31'): Contrato
{
    $tenant = Tenant::factory()->create();
    // BelongsToTenant exige tenant na sessão para auto-popular tenant_id em
    // models que não têm tenant_id no fillable (caso do Contrato).
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
}

it('cria item avulso com 1 ocorrência', function () {
    $contrato = criarContratoParaItens();

    $service = app(ItemCobrancaService::class);
    $item = $service->criar($contrato, [
        'descricao' => 'Troca de chuveiro',
        'pagante' => 'proprietario',
        'recebedor' => 'inquilino',
        'tipo' => 'avulso',
        'valor_unitario' => 300.00,
        'mes_referencia' => '03/2026',
    ]);

    expect($item->parent_item_id)->toBeNull();
    expect($item->mes_referencia)->toBe('03/2026');
    expect(ItemCobranca::where('contrato_id', $contrato->id)->count())->toBe(1);
});

it('cria item recorrente mensal e pré-gera ocorrências até data_fim do contrato', function () {
    $contrato = criarContratoParaItens('2026-01-01', '2026-12-31');

    $service = app(ItemCobrancaService::class);
    $service->criar($contrato, [
        'descricao' => 'Aluguel',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 2000.00,
        'mes_referencia' => '01/2026',
    ]);

    expect(ItemCobranca::where('contrato_id', $contrato->id)->count())->toBe(12);
    expect(ItemCobranca::where('mes_referencia', '12/2026')->exists())->toBeTrue();
});

it('cria item recorrente trimestral com intervalo correto', function () {
    $contrato = criarContratoParaItens('2026-01-01', '2026-12-31');

    $service = app(ItemCobrancaService::class);
    $service->criar($contrato, [
        'descricao' => 'Cota condominial extra',
        'pagante' => 'inquilino',
        'recebedor' => 'administradora',
        'tipo' => 'recorrente',
        'periodicidade' => 'trimestral',
        'valor_unitario' => 500.00,
        'mes_referencia' => '01/2026',
    ]);

    // 01/2026, 04/2026, 07/2026, 10/2026 = 4 ocorrências em 12 meses
    expect(ItemCobranca::where('contrato_id', $contrato->id)->count())->toBe(4);
    expect(ItemCobranca::where('mes_referencia', '04/2026')->exists())->toBeTrue();
    expect(ItemCobranca::where('mes_referencia', '07/2026')->exists())->toBeTrue();
    expect(ItemCobranca::where('mes_referencia', '10/2026')->exists())->toBeTrue();
});

it('cria item parcelado com N parcelas', function () {
    $contrato = criarContratoParaItens('2026-01-01', '2027-12-31');

    $service = app(ItemCobrancaService::class);
    $pai = $service->criar($contrato, [
        'descricao' => 'IPTU 2026',
        'pagante' => 'inquilino',
        'recebedor' => 'administradora',
        'tipo' => 'parcelado',
        'num_parcelas_total' => 12,
        'valor_unitario' => 150.00,
        'mes_referencia' => '02/2026',
    ]);

    $serie = ItemCobranca::where('contrato_id', $contrato->id)->orderBy('num_parcela')->get();

    expect($serie)->toHaveCount(12);
    expect($pai->num_parcela)->toBe(1);
    expect($serie->last()->num_parcela)->toBe(12);
    expect($serie->last()->mes_referencia)->toBe('01/2027');
});

it('força visivel_inquilino=true quando pagante=inquilino', function () {
    $contrato = criarContratoParaItens();

    $service = app(ItemCobrancaService::class);
    $item = $service->criar($contrato, [
        'descricao' => 'Aluguel',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'avulso',
        'valor_unitario' => 100.00,
        'mes_referencia' => '03/2026',
        'visivel_inquilino' => false, // tenta forçar false — service deve sobrescrever
    ]);

    expect($item->visivel_inquilino)->toBeTrue();
});

it('atualiza apenas a ocorrência selecionada (escopo: somente)', function () {
    $contrato = criarContratoParaItens('2026-01-01', '2026-12-31');
    $service = app(ItemCobrancaService::class);

    $pai = $service->criar($contrato, [
        'descricao' => 'Condomínio',
        'pagante' => 'inquilino',
        'recebedor' => 'administradora',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 800.00,
        'mes_referencia' => '01/2026',
    ]);

    $marco = ItemCobranca::where('contrato_id', $contrato->id)->where('mes_referencia', '03/2026')->firstOrFail();
    $service->atualizarOcorrencia($marco, ['valor_unitario' => 850.00]);

    expect($marco->fresh()->valor_unitario)->toBe('850.00');
    expect($pai->fresh()->valor_unitario)->toBe('800.00');
    expect(ItemCobranca::where('mes_referencia', '04/2026')->first()->valor_unitario)->toBe('800.00');
});

it('atualiza esta e as futuras (escopo: futuras)', function () {
    $contrato = criarContratoParaItens('2026-01-01', '2026-12-31');
    $service = app(ItemCobrancaService::class);

    $service->criar($contrato, [
        'descricao' => 'Aluguel',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 2000.00,
        'mes_referencia' => '01/2026',
    ]);

    $junho = ItemCobranca::where('mes_referencia', '06/2026')->firstOrFail();
    $afetadas = $service->atualizarEstaEFuturas($junho, ['valor_unitario' => 2100.00]);

    // De junho a dezembro = 7 ocorrências
    expect($afetadas)->toBe(7);
    expect(ItemCobranca::where('mes_referencia', '01/2026')->first()->valor_unitario)->toBe('2000.00');
    expect(ItemCobranca::where('mes_referencia', '06/2026')->first()->valor_unitario)->toBe('2100.00');
    expect(ItemCobranca::where('mes_referencia', '12/2026')->first()->valor_unitario)->toBe('2100.00');
});

it('atualiza todas as ocorrências pendentes (escopo: todas)', function () {
    $contrato = criarContratoParaItens('2026-01-01', '2026-06-30');
    $service = app(ItemCobrancaService::class);

    $pai = $service->criar($contrato, [
        'descricao' => 'Aluguel',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 1500.00,
        'mes_referencia' => '01/2026',
    ]);

    $afetadas = $service->atualizarTodas($pai, ['valor_unitario' => 1600.00]);

    expect($afetadas)->toBe(6);
    $todos = ItemCobranca::where('contrato_id', $contrato->id)->get();
    foreach ($todos as $item) {
        expect($item->valor_unitario)->toBe('1600.00');
    }
});

it('não altera ocorrências conciliadas em nenhum modo', function () {
    $contrato = criarContratoParaItens('2026-01-01', '2026-06-30');
    $service = app(ItemCobrancaService::class);

    $pai = $service->criar($contrato, [
        'descricao' => 'Aluguel',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 1000.00,
        'mes_referencia' => '01/2026',
    ]);

    // Marca 01/2026 e 02/2026 como conciliadas
    ItemCobranca::whereIn('mes_referencia', ['01/2026', '02/2026'])
        ->update(['status' => 'conciliado']);

    $afetadas = $service->atualizarTodas($pai, ['valor_unitario' => 1100.00]);

    expect($afetadas)->toBe(4);
    expect(ItemCobranca::where('mes_referencia', '01/2026')->first()->valor_unitario)->toBe('1000.00');
    expect(ItemCobranca::where('mes_referencia', '02/2026')->first()->valor_unitario)->toBe('1000.00');
    expect(ItemCobranca::where('mes_referencia', '03/2026')->first()->valor_unitario)->toBe('1100.00');
});

it('rejeita atualização direta em ocorrência conciliada', function () {
    $contrato = criarContratoParaItens();
    $service = app(ItemCobrancaService::class);

    $item = $service->criar($contrato, [
        'descricao' => 'Aluguel',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'avulso',
        'valor_unitario' => 1000.00,
        'mes_referencia' => '03/2026',
    ]);

    $item->update(['status' => 'conciliado']);

    expect(fn () => $service->atualizarOcorrencia($item, ['valor_unitario' => 1500.00]))
        ->toThrow(DomainException::class);
});

it('proíbe alteração de tipo, mes_referencia e parent_item_id', function () {
    $contrato = criarContratoParaItens();
    $service = app(ItemCobrancaService::class);

    $item = $service->criar($contrato, [
        'descricao' => 'Aluguel',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'avulso',
        'valor_unitario' => 1000.00,
        'mes_referencia' => '03/2026',
    ]);

    expect(fn () => $service->atualizarOcorrencia($item, ['tipo' => 'recorrente']))
        ->toThrow(DomainException::class);
    expect(fn () => $service->atualizarOcorrencia($item, ['mes_referencia' => '04/2026']))
        ->toThrow(DomainException::class);
});

it('cancela apenas uma ocorrência', function () {
    $contrato = criarContratoParaItens('2026-01-01', '2026-06-30');
    $service = app(ItemCobrancaService::class);

    $service->criar($contrato, [
        'descricao' => 'Aluguel',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 1000.00,
        'mes_referencia' => '01/2026',
    ]);

    $marco = ItemCobranca::where('mes_referencia', '03/2026')->firstOrFail();
    $service->cancelarOcorrencia($marco);

    expect($marco->fresh()->status)->toBe('cancelado');
    expect(ItemCobranca::where('mes_referencia', '04/2026')->first()->status)->toBe('pendente');
});

it('cancela toda a série pendente', function () {
    $contrato = criarContratoParaItens('2026-01-01', '2026-06-30');
    $service = app(ItemCobrancaService::class);

    $pai = $service->criar($contrato, [
        'descricao' => 'Aluguel',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 1000.00,
        'mes_referencia' => '01/2026',
    ]);

    $count = $service->cancelarSerie($pai);

    expect($count)->toBe(6);
    expect(ItemCobranca::where('contrato_id', $contrato->id)->where('status', 'cancelado')->count())->toBe(6);
});

it('rejeita criação de recorrente sem periodicidade', function () {
    $contrato = criarContratoParaItens();
    $service = app(ItemCobrancaService::class);

    expect(fn () => $service->criar($contrato, [
        'descricao' => 'X',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'recorrente',
        'valor_unitario' => 100.00,
        'mes_referencia' => '01/2026',
    ]))->toThrow(DomainException::class);
});

it('rejeita criação de parcelado sem num_parcelas_total', function () {
    $contrato = criarContratoParaItens();
    $service = app(ItemCobrancaService::class);

    expect(fn () => $service->criar($contrato, [
        'descricao' => 'X',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'parcelado',
        'valor_unitario' => 100.00,
        'mes_referencia' => '01/2026',
    ]))->toThrow(DomainException::class);
});

it('aceita entidade_externa em intermediação', function () {
    $contrato = criarContratoParaItens();
    $entidade = EntidadeExterna::factory()->create(['tenant_id' => $contrato->tenant_id]);
    $service = app(ItemCobrancaService::class);

    $item = $service->criar($contrato, [
        'descricao' => 'Condomínio',
        'pagante' => 'inquilino',
        'recebedor' => 'administradora',
        'entidade_externa_id' => $entidade->id,
        'tipo' => 'avulso',
        'valor_unitario' => 800.00,
        'mes_referencia' => '03/2026',
    ]);

    expect($item->entidade_externa_id)->toBe($entidade->id);
});

it('persiste dia_vencimento informado na criação', function () {
    $contrato = criarContratoParaItens();
    $service = app(ItemCobrancaService::class);

    $item = $service->criar($contrato, [
        'descricao' => 'Condomínio',
        'pagante' => 'inquilino',
        'recebedor' => 'administradora',
        'tipo' => 'avulso',
        'valor_unitario' => 800.00,
        'dia_vencimento' => 10,
        'mes_referencia' => '03/2026',
    ]);

    expect($item->dia_vencimento)->toBe(10);
});

it('propaga dia_vencimento para todas as ocorrências pré-geradas de uma série recorrente', function () {
    $contrato = criarContratoParaItens('2026-01-01', '2026-12-31');
    $service = app(ItemCobrancaService::class);

    $pai = $service->criar($contrato, [
        'descricao' => 'Condomínio',
        'pagante' => 'inquilino',
        'recebedor' => 'administradora',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 800.00,
        'dia_vencimento' => 15,
        'mes_referencia' => '01/2026',
    ]);

    $ocorrencias = ItemCobranca::where('contrato_id', $contrato->id)->get();

    expect($ocorrencias)->toHaveCount(12);
    expect($ocorrencias->pluck('dia_vencimento')->unique()->all())->toBe([15]);
    expect($pai->dia_vencimento)->toBe(15);
});

it('permite criar item sem dia_vencimento (campo opcional)', function () {
    $contrato = criarContratoParaItens();
    $service = app(ItemCobrancaService::class);

    $item = $service->criar($contrato, [
        'descricao' => 'Aluguel',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'avulso',
        'valor_unitario' => 1500.00,
        'mes_referencia' => '03/2026',
    ]);

    expect($item->dia_vencimento)->toBeNull();
});

it('atualiza dia_vencimento de toda a série quando escopo é todas', function () {
    $contrato = criarContratoParaItens('2026-01-01', '2026-06-30');
    $service = app(ItemCobrancaService::class);

    $pai = $service->criar($contrato, [
        'descricao' => 'Condomínio',
        'pagante' => 'inquilino',
        'recebedor' => 'administradora',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 800.00,
        'dia_vencimento' => 5,
        'mes_referencia' => '01/2026',
    ]);

    $service->atualizarTodas($pai, ['dia_vencimento' => 20]);

    $diasUnicos = ItemCobranca::where('contrato_id', $contrato->id)
        ->where('status', 'pendente')
        ->pluck('dia_vencimento')
        ->unique()
        ->all();

    expect($diasUnicos)->toBe([20]);
});
