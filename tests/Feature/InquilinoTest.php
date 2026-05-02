<?php

use App\Models\Contrato;
use App\Models\ContratoInquilino;
use App\Models\Imovel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vinculo;

test('admin acessa listagem de inquilinos', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->get('/inquilinos');

    $response->assertOk();
});

test('admin cria inquilino PF apenas com nome', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/inquilinos', [
            'name' => 'Maria Silva',
            'tipo_pessoa' => 'pf',
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect('/inquilinos');

    $vinculo = Vinculo::where('tenant_id', $tenant->id)->where('papel', 'inquilino')->first();
    expect($vinculo)->not->toBeNull();
    expect($vinculo->user->name)->toBe('Maria Silva');
    expect($vinculo->status)->toBe('ativo');
});

test('endpoint inline retorna inquilino criado em JSON com 201', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson('/inquilinos/inline', ['name' => 'João Inline', 'tipo_pessoa' => 'pf']);

    $response->assertCreated();
    $response->assertJson(['name' => 'João Inline', 'tipo_pessoa' => 'pf']);
});

test('busca aplica AND por palavra', function () {
    [$tenant, $admin] = setupTenantComAdmin();

    $u1 = User::factory()->create(['name' => 'Eduardo Macedo']);
    $u2 = User::factory()->create(['name' => 'Eduardo Silva']);
    Vinculo::create(['user_id' => $u1->id, 'tenant_id' => $tenant->id, 'papel' => 'inquilino', 'status' => 'ativo']);
    Vinculo::create(['user_id' => $u2->id, 'tenant_id' => $tenant->id, 'papel' => 'inquilino', 'status' => 'ativo']);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->getJson('/inquilinos/buscar?q=eduardo macedo');

    $response->assertOk();
    expect(collect($response->json())->pluck('name')->toArray())->toBe(['Eduardo Macedo']);
});

test('busca não retorna inquilinos de outro tenant', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $outroTenant = Tenant::factory()->create(['is_exempt_from_subscription' => true]);
    $u = User::factory()->create(['name' => 'Carlos Outro']);
    Vinculo::create(['user_id' => $u->id, 'tenant_id' => $outroTenant->id, 'papel' => 'inquilino', 'status' => 'ativo']);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->getJson('/inquilinos/buscar?q=carlos');

    $response->assertOk();
    expect($response->json())->toBe([]);
});

test('busca não retorna proprietários (papel diferente)', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $u = User::factory()->create(['name' => 'Pedro Proprietário']);
    Vinculo::create(['user_id' => $u->id, 'tenant_id' => $tenant->id, 'papel' => 'proprietario', 'status' => 'ativo']);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->getJson('/inquilinos/buscar?q=pedro');

    $response->assertOk();
    expect($response->json())->toBe([]);
});

test('inativação bloqueada quando há contrato ativo', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $u = User::factory()->create();
    $v = Vinculo::create(['user_id' => $u->id, 'tenant_id' => $tenant->id, 'papel' => 'inquilino', 'status' => 'ativo']);
    $contrato = Contrato::factory()->create([
        'tenant_id' => $tenant->id,
        'imovel_id' => $imovel->id,
        'inquilino_vinculo_id' => $v->id,
        'status' => 'ativo',
    ]);
    ContratoInquilino::factory()->create([
        'tenant_id' => $tenant->id, 'contrato_id' => $contrato->id, 'vinculo_id' => $v->id, 'principal' => true,
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->delete("/inquilinos/{$v->id}");

    $response->assertSessionHasErrors(['inquilino']);
    expect($v->fresh()->status)->toBe('ativo');
});

test('listagem conta corretamente co-inquilinos (não só principal)', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $u = User::factory()->create(['name' => 'Co Inquilino']);
    $v = Vinculo::create(['user_id' => $u->id, 'tenant_id' => $tenant->id, 'papel' => 'inquilino', 'status' => 'ativo']);
    $vPrincipal = Vinculo::create(['user_id' => User::factory()->create()->id, 'tenant_id' => $tenant->id, 'papel' => 'inquilino', 'status' => 'ativo']);
    $contrato = Contrato::factory()->create([
        'tenant_id' => $tenant->id, 'imovel_id' => $imovel->id,
        'inquilino_vinculo_id' => $vPrincipal->id, 'status' => 'ativo',
    ]);
    ContratoInquilino::factory()->create([
        'tenant_id' => $tenant->id, 'contrato_id' => $contrato->id, 'vinculo_id' => $vPrincipal->id, 'principal' => true,
    ]);
    ContratoInquilino::factory()->create([
        'tenant_id' => $tenant->id, 'contrato_id' => $contrato->id, 'vinculo_id' => $v->id, 'principal' => false,
    ]);

    $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->get('/inquilinos?busca=Co Inquilino')
        ->assertInertia(fn ($page) => $page
            ->where('inquilinos.data.0.contratos_como_inquilino_count', 1));
});

test('admin não consegue editar inquilino de outro tenant', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $outroTenant = Tenant::factory()->create(['is_exempt_from_subscription' => true]);
    $u = User::factory()->create();
    $v = Vinculo::create(['user_id' => $u->id, 'tenant_id' => $outroTenant->id, 'papel' => 'inquilino', 'status' => 'ativo']);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->get("/inquilinos/{$v->id}/editar");

    $response->assertNotFound();
});
