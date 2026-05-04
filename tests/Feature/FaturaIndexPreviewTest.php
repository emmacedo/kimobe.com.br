<?php

use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\Imovel;
use App\Models\ItemCobranca;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\TenantService;

function criarContratoAtivo(Tenant $tenant, string $nomeInquilino = 'Inquilino Teste'): Contrato
{
    app(TenantService::class)->setTenant($tenant);

    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $vinculo = Vinculo::create([
        'user_id' => User::factory()->create(['name' => $nomeInquilino])->id,
        'tenant_id' => $tenant->id,
        'papel' => 'inquilino',
        'status' => 'ativo',
    ]);

    return Contrato::create([
        'imovel_id' => $imovel->id,
        'inquilino_vinculo_id' => $vinculo->id,
        'data_inicio' => '2026-01-01',
        'data_fim' => '2026-12-31',
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
        'tipo_garantia' => 'sem_garantia',
        'status' => 'ativo',
    ]);
}

it('retorna preview com soma de itens de cobrança quando não há fatura no mês', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $contrato = criarContratoAtivo($tenant, 'Maria Silva');

    ItemCobranca::create([
        'tenant_id' => $tenant->id,
        'contrato_id' => $contrato->id,
        'descricao' => 'Aluguel',
        'pagante' => 'inquilino',
        'recebedor' => 'proprietario',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 2000.00,
        'mes_referencia' => '03/2026',
        'visivel_inquilino' => true,
        'status' => 'pendente',
    ]);
    ItemCobranca::create([
        'tenant_id' => $tenant->id,
        'contrato_id' => $contrato->id,
        'descricao' => 'Condomínio',
        'pagante' => 'inquilino',
        'recebedor' => 'administradora',
        'tipo' => 'recorrente',
        'periodicidade' => 'mensal',
        'valor_unitario' => 800.00,
        'mes_referencia' => '03/2026',
        'visivel_inquilino' => true,
        'status' => 'pendente',
    ]);

    $response = $this->actingAs($admin)->get('/financeiro/faturas?mes=2026-03');

    $response->assertOk();
    $linhas = $response->viewData('page')['props']['linhas'];

    expect($linhas)->toHaveCount(1);
    expect($linhas[0]['contrato_id'])->toBe($contrato->id);
    expect($linhas[0]['inquilino'])->toBe('Maria Silva');
    expect($linhas[0]['status'])->toBe('preview');
    expect($linhas[0]['is_preview'])->toBeTrue();
    expect($linhas[0]['fatura_id'])->toBeNull();
    expect((float) $linhas[0]['valor'])->toBe(2800.00);
});

it('retorna fatura persistida em vez de preview quando ela existe', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $contrato = criarContratoAtivo($tenant);

    $fatura = Fatura::create([
        'tenant_id' => $tenant->id,
        'contrato_id' => $contrato->id,
        'referencia' => '03/2026',
        'valor_total' => 1500.00,
        'data_vencimento' => '2026-03-05',
        'tipo_geracao' => 'manual',
        'status' => 'pendente',
    ]);

    $response = $this->actingAs($admin)->get('/financeiro/faturas?mes=2026-03');

    $linhas = $response->viewData('page')['props']['linhas'];

    expect($linhas[0]['fatura_id'])->toBe($fatura->id);
    expect($linhas[0]['status'])->toBe('pendente');
    expect($linhas[0]['is_preview'])->toBeFalse();
    expect((float) $linhas[0]['valor'])->toBe(1500.00);
});

it('só ignora itens de outros meses no cálculo do preview', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $contrato = criarContratoAtivo($tenant);

    ItemCobranca::create([
        'tenant_id' => $tenant->id, 'contrato_id' => $contrato->id,
        'descricao' => 'Aluguel março', 'pagante' => 'inquilino', 'recebedor' => 'proprietario',
        'tipo' => 'recorrente', 'periodicidade' => 'mensal', 'valor_unitario' => 2000.00,
        'mes_referencia' => '03/2026', 'visivel_inquilino' => true, 'status' => 'pendente',
    ]);
    ItemCobranca::create([
        'tenant_id' => $tenant->id, 'contrato_id' => $contrato->id,
        'descricao' => 'Aluguel abril', 'pagante' => 'inquilino', 'recebedor' => 'proprietario',
        'tipo' => 'recorrente', 'periodicidade' => 'mensal', 'valor_unitario' => 2000.00,
        'mes_referencia' => '04/2026', 'visivel_inquilino' => true, 'status' => 'pendente',
    ]);

    $response = $this->actingAs($admin)->get('/financeiro/faturas?mes=2026-03');
    $linhas = $response->viewData('page')['props']['linhas'];

    expect((float) $linhas[0]['valor'])->toBe(2000.00); // só março
});

it('ignora contratos inativos', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $contrato = criarContratoAtivo($tenant);
    $contrato->update(['status' => 'encerrado']);

    $response = $this->actingAs($admin)->get('/financeiro/faturas?mes=2026-03');

    expect($response->viewData('page')['props']['linhas'])->toBeEmpty();
});

it('filtra por busca textual no nome do inquilino', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    criarContratoAtivo($tenant, 'João Silva');
    criarContratoAtivo($tenant, 'Maria Pereira');

    $response = $this->actingAs($admin)->get('/financeiro/faturas?mes=2026-03&busca=Maria');
    $linhas = $response->viewData('page')['props']['linhas'];

    expect($linhas)->toHaveCount(1);
    expect($linhas[0]['inquilino'])->toBe('Maria Pereira');
});

it('retorna preview com valor zero quando não há itens nem fatura', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $contrato = criarContratoAtivo($tenant);

    $response = $this->actingAs($admin)->get('/financeiro/faturas?mes=2026-03');
    $linhas = $response->viewData('page')['props']['linhas'];

    expect($linhas)->toHaveCount(1);
    expect($linhas[0]['status'])->toBe('preview');
    expect((float) $linhas[0]['valor'])->toBe(0.0);
});
