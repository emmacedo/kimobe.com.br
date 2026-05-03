<?php

use App\Models\Contrato;
use App\Models\ContratoReajuste;
use App\Models\Imovel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\TenantService;

function criarContratoParaTenant(Tenant $tenant, string $dataInicio = '2026-01-01', string $dataFim = '2027-12-31'): Contrato
{
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
        'indice_reajuste' => 'igpm',
        'mes_reajuste' => 1,
        'multa_atraso_pct' => 2,
        'juros_atraso_pct_dia' => 0.0333,
        'dias_carencia' => 0,
        'tipo_garantia' => 'sem_garantia',
        'status' => 'ativo',
    ]);
}

it('admin aplica reajuste via POST e é redirecionado para o detalhe do contrato', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $contrato = criarContratoParaTenant($tenant);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post("/contratos/{$contrato->id}/reajustes", [
            'valor_novo' => 2200.00,
            'data_aplicacao' => '2027-01-01',
            'indice_usado' => 'igpm',
            'origem' => 'reajuste_anual',
            'observacao' => 'Reajuste anual.',
        ]);

    $response->assertRedirect("/contratos/{$contrato->id}");
    expect(ContratoReajuste::where('contrato_id', $contrato->id)->count())->toBe(1);

    $reajuste = ContratoReajuste::where('contrato_id', $contrato->id)->first();
    expect($reajuste->alterado_por_user_id)->toBe($admin->id);
    expect((float) $contrato->fresh()->valor_aluguel)->toBe(2200.0);
});

it('valida campos obrigatórios', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $contrato = criarContratoParaTenant($tenant);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->from("/contratos/{$contrato->id}")
        ->post("/contratos/{$contrato->id}/reajustes", []);

    $response->assertSessionHasErrors(['valor_novo', 'data_aplicacao', 'indice_usado', 'origem']);
});

it('inquilino não consegue aplicar reajuste', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $contrato = criarContratoParaTenant($tenant);

    $inquilinoUser = User::factory()->create();
    Vinculo::create([
        'user_id' => $inquilinoUser->id,
        'tenant_id' => $tenant->id,
        'papel' => 'inquilino',
        'status' => 'ativo',
    ]);

    $response = $this->actingAs($inquilinoUser)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/contratos/{$contrato->id}/reajustes", [
            'valor_novo' => 2200,
            'data_aplicacao' => '2026-06-01',
            'indice_usado' => 'igpm',
            'origem' => 'reajuste_anual',
        ]);

    $response->assertForbidden();
    expect(ContratoReajuste::count())->toBe(0);
});
