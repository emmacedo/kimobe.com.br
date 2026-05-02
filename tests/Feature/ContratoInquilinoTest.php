<?php

use App\Models\Contrato;
use App\Models\ContratoInquilino;
use App\Models\Imovel;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Models\Vinculo;

function novoInquilinoAtivo($tenantId): Vinculo
{
    return Vinculo::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenantId,
        'papel' => 'inquilino',
        'status' => 'ativo',
    ]);
}

function montarContratoComPrincipal($tenant): array
{
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $inqPrincipal = novoInquilinoAtivo($tenant->id);
    $contrato = Contrato::factory()->create([
        'tenant_id' => $tenant->id,
        'imovel_id' => $imovel->id,
        'inquilino_vinculo_id' => $inqPrincipal->id,
        'status' => 'ativo',
    ]);
    ContratoInquilino::factory()->create([
        'tenant_id' => $tenant->id, 'contrato_id' => $contrato->id, 'vinculo_id' => $inqPrincipal->id, 'principal' => true,
    ]);

    return [$contrato, $inqPrincipal];
}

test('admin adiciona co-inquilino sem alterar principal', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    [$contrato] = montarContratoComPrincipal($tenant);
    $coInq = novoInquilinoAtivo($tenant->id);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/contratos/{$contrato->id}/inquilinos", [
            'vinculo_id' => $coInq->id,
            'principal' => false,
        ]);

    $response->assertCreated();
    expect($contrato->inquilinos()->count())->toBe(2);
    expect($contrato->fresh()->inquilinos()->where('principal', true)->count())->toBe(1);
});

test('marcar novo como principal demove o atual', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    [$contrato, $inqPrincipal] = montarContratoComPrincipal($tenant);
    $novoInq = novoInquilinoAtivo($tenant->id);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/contratos/{$contrato->id}/inquilinos", [
            'vinculo_id' => $novoInq->id,
            'principal' => true,
        ]);

    $response->assertCreated();
    $contrato->refresh();
    expect($contrato->inquilino_vinculo_id)->toBe($novoInq->id); // cache atualizado
    expect($contrato->inquilinos()->where('principal', true)->count())->toBe(1);
    expect($contrato->inquilinos()->where('vinculo_id', $inqPrincipal->id)->first()->principal)->toBeFalse();
});

test('PUT promove co-inquilino a principal e demove o atual', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    [$contrato, $inqPrincipal] = montarContratoComPrincipal($tenant);
    $coInq = novoInquilinoAtivo($tenant->id);
    $coCi = ContratoInquilino::factory()->create([
        'contrato_id' => $contrato->id,
        'tenant_id' => $tenant->id, 'vinculo_id' => $coInq->id, 'principal' => false,
    ]);

    $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->putJson("/contratos/{$contrato->id}/inquilinos/{$coCi->id}", ['principal' => true])
        ->assertOk();

    $contrato->refresh();
    expect($contrato->inquilino_vinculo_id)->toBe($coInq->id);
    expect($coCi->fresh()->principal)->toBeTrue();
    expect($contrato->inquilinos()->where('vinculo_id', $inqPrincipal->id)->first()->principal)->toBeFalse();
});

test('PUT bloqueia desmarcar único principal', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    [$contrato, $inqPrincipal] = montarContratoComPrincipal($tenant);
    $ciPrincipal = $contrato->inquilinos()->where('vinculo_id', $inqPrincipal->id)->first();

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->putJson("/contratos/{$contrato->id}/inquilinos/{$ciPrincipal->id}", ['principal' => false]);

    $response->assertStatus(422);
    expect($ciPrincipal->fresh()->principal)->toBeTrue();
});

test('DELETE bloqueia remover último inquilino', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    [$contrato, $inqPrincipal] = montarContratoComPrincipal($tenant);
    $ci = $contrato->inquilinos()->where('vinculo_id', $inqPrincipal->id)->first();

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->deleteJson("/contratos/{$contrato->id}/inquilinos/{$ci->id}");

    $response->assertStatus(422);
});

test('DELETE do principal auto-promove o próximo e atualiza cache', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    [$contrato, $inqPrincipal] = montarContratoComPrincipal($tenant);
    $coInq = novoInquilinoAtivo($tenant->id);
    $coCi = ContratoInquilino::factory()->create([
        'contrato_id' => $contrato->id,
        'tenant_id' => $tenant->id, 'vinculo_id' => $coInq->id, 'principal' => false,
    ]);
    $ciPrincipal = $contrato->inquilinos()->where('vinculo_id', $inqPrincipal->id)->first();

    $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->deleteJson("/contratos/{$contrato->id}/inquilinos/{$ciPrincipal->id}")
        ->assertOk();

    expect($coCi->fresh()->principal)->toBeTrue();
    expect($contrato->fresh()->inquilino_vinculo_id)->toBe($coInq->id);
});

test('IDOR bloqueado: PUT em ContratoInquilino de outro contrato retorna 404', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    [$contratoA] = montarContratoComPrincipal($tenant);
    [$contratoB, $inqB] = montarContratoComPrincipal($tenant);
    $ciB = $contratoB->inquilinos()->where('vinculo_id', $inqB->id)->first();

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->putJson("/contratos/{$contratoA->id}/inquilinos/{$ciB->id}", ['principal' => false]);

    $response->assertNotFound();
});

test('cache inquilino_vinculo_id mantém consistência quando 2 principais simultâneos são desmarcados', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    [$contrato, $inqA] = montarContratoComPrincipal($tenant);
    $inqB = novoInquilinoAtivo($tenant->id);
    $ciB = ContratoInquilino::factory()->create([
        'tenant_id' => $tenant->id, 'contrato_id' => $contrato->id, 'vinculo_id' => $inqB->id, 'principal' => true,
    ]);

    // PUT desmarca B como principal — A continua. Cache deve apontar para A.
    $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->putJson("/contratos/{$contrato->id}/inquilinos/{$ciB->id}", ['principal' => false])
        ->assertOk();

    expect($contrato->fresh()->inquilino_vinculo_id)->toBe($inqA->id);
});

test('DELETE retorna novo_principal_id no JSON', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    [$contrato, $inqPrincipal] = montarContratoComPrincipal($tenant);
    $coInq = novoInquilinoAtivo($tenant->id);
    $coCi = ContratoInquilino::factory()->create([
        'tenant_id' => $tenant->id, 'contrato_id' => $contrato->id, 'vinculo_id' => $coInq->id, 'principal' => false,
    ]);
    $ciPrincipal = $contrato->inquilinos()->where('vinculo_id', $inqPrincipal->id)->first();

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->deleteJson("/contratos/{$contrato->id}/inquilinos/{$ciPrincipal->id}");

    $response->assertOk();
    $response->assertJson(['novo_principal_id' => $coCi->id]);
});

test('inquilino soft-deletado pode ser re-adicionado (restore)', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    [$contrato, $inqPrincipal] = montarContratoComPrincipal($tenant);
    $coInq = novoInquilinoAtivo($tenant->id);
    $coCi = ContratoInquilino::factory()->create([
        'contrato_id' => $contrato->id,
        'tenant_id' => $tenant->id, 'vinculo_id' => $coInq->id, 'principal' => false,
    ]);
    $coCi->delete();

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/contratos/{$contrato->id}/inquilinos", [
            'vinculo_id' => $coInq->id,
            'principal' => false,
        ]);

    $response->assertCreated();
    expect(ContratoInquilino::withoutGlobalScopes([TenantScope::class])
        ->where('contrato_id', $contrato->id)
        ->where('vinculo_id', $coInq->id)
        ->count())->toBe(1); // mesmo registro restaurado
});
