<?php

use App\Models\Imovel;
use App\Models\Tenant;
use App\Models\Titularidade;
use App\Models\User;
use App\Models\Vinculo;

test('admin acessa listagem de proprietários', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->get('/proprietarios');

    $response->assertOk();
});

test('admin cria proprietário PF apenas com nome', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/proprietarios', [
            'name' => 'João da Silva',
            'tipo_pessoa' => 'pf',
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect('/proprietarios');

    $vinculo = Vinculo::where('tenant_id', $tenant->id)->where('papel', 'proprietario')->first();
    expect($vinculo)->not->toBeNull();
    expect($vinculo->user->name)->toBe('João da Silva');
    expect($vinculo->user->tipo_pessoa)->toBe('pf');
    expect($vinculo->status)->toBe('ativo');
});

test('email gerado é placeholder único quando não informado', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/proprietarios', ['name' => 'Sem Email', 'tipo_pessoa' => 'pf']);

    $vinculo = Vinculo::where('tenant_id', $tenant->id)->where('papel', 'proprietario')->first();
    expect($vinculo->user->email)->toEndWith('@'.User::EMAIL_PLACEHOLDER_DOMAIN);
    expect($vinculo->user->hasPlaceholderEmail())->toBeTrue();
});

test('admin cria proprietário PJ com CNPJ', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/proprietarios', [
            'name' => 'Imóveis Centro Ltda',
            'tipo_pessoa' => 'pj',
            'documento' => '12.345.678/0001-90',
            'telefone' => '(21) 3333-4444',
            'email' => 'contato@imoveiscentro.com.br',
        ]);

    $response->assertSessionHasNoErrors();

    $vinculo = Vinculo::where('tenant_id', $tenant->id)->where('papel', 'proprietario')->first();
    expect($vinculo->user->tipo_pessoa)->toBe('pj');
    expect($vinculo->user->documento)->toBe('12345678000190');
    expect($vinculo->user->telefone)->toBe('2133334444');
});

test('CPF de 14 dígitos para PF é rejeitado', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/proprietarios', [
            'name' => 'Errado',
            'tipo_pessoa' => 'pf',
            'documento' => '12345678000190',
        ]);

    $response->assertSessionHasErrors(['documento']);
});

test('endpoint inline retorna proprietário criado em JSON', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson('/proprietarios/inline', ['name' => 'Maria Inline', 'tipo_pessoa' => 'pf']);

    $response->assertCreated();
    $response->assertJsonStructure(['vinculo_id', 'name', 'tipo_pessoa', 'status']);
    $response->assertJson(['name' => 'Maria Inline', 'tipo_pessoa' => 'pf', 'status' => 'ativo']);
});

test('busca aplica AND por palavra entre nome e documento', function () {
    [$tenant, $admin] = setupTenantComAdmin();

    $u1 = User::factory()->create(['name' => 'Eduardo Macedo', 'documento' => '11111111111']);
    $u2 = User::factory()->create(['name' => 'Eduardo Silva', 'documento' => '22222222222']);
    $u3 = User::factory()->create(['name' => 'Macedo Santos', 'documento' => '33333333333']);
    Vinculo::create(['user_id' => $u1->id, 'tenant_id' => $tenant->id, 'papel' => 'proprietario', 'status' => 'ativo']);
    Vinculo::create(['user_id' => $u2->id, 'tenant_id' => $tenant->id, 'papel' => 'proprietario', 'status' => 'ativo']);
    Vinculo::create(['user_id' => $u3->id, 'tenant_id' => $tenant->id, 'papel' => 'proprietario', 'status' => 'ativo']);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->getJson('/proprietarios/buscar?q=eduardo macedo');

    $response->assertOk();
    $nomes = collect($response->json())->pluck('name');
    expect($nomes->toArray())->toBe(['Eduardo Macedo']); // só ele tem AS DUAS palavras
});

test('busca não retorna proprietários de outro tenant', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $outroTenant = Tenant::factory()->create(['is_exempt_from_subscription' => true]);
    $outroUser = User::factory()->create(['name' => 'Eduardo De Outro Tenant']);
    Vinculo::create(['user_id' => $outroUser->id, 'tenant_id' => $outroTenant->id, 'papel' => 'proprietario', 'status' => 'ativo']);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->getJson('/proprietarios/buscar?q=eduardo');

    $response->assertOk();
    expect($response->json())->toBe([]);
});

test('busca não retorna proprietários inativos', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $u = User::factory()->create(['name' => 'Joana Inativa']);
    Vinculo::create(['user_id' => $u->id, 'tenant_id' => $tenant->id, 'papel' => 'proprietario', 'status' => 'inativo']);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->getJson('/proprietarios/buscar?q=joana');

    $response->assertOk();
    expect($response->json())->toBe([]);
});

test('admin atualiza proprietário', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $u = User::factory()->create(['name' => 'Antigo', 'tipo_pessoa' => 'pf']);
    $v = Vinculo::create(['user_id' => $u->id, 'tenant_id' => $tenant->id, 'papel' => 'proprietario', 'status' => 'ativo']);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->put("/proprietarios/{$v->id}", [
            'name' => 'Nome Novo',
            'tipo_pessoa' => 'pf',
            'telefone' => '(21) 99999-8888',
        ]);

    $response->assertSessionHasNoErrors();
    expect($u->fresh()->name)->toBe('Nome Novo');
    expect($u->fresh()->telefone)->toBe('21999998888');
});

test('inativação bloqueada quando há titularidades', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $u = User::factory()->create();
    $v = Vinculo::create(['user_id' => $u->id, 'tenant_id' => $tenant->id, 'papel' => 'proprietario', 'status' => 'ativo']);
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    Titularidade::factory()->create([
        'tenant_id' => $tenant->id,
        'imovel_id' => $imovel->id,
        'vinculo_id' => $v->id,
        'tipo_titular' => 'pessoa_fisica',
        'papel' => 'responsavel',
        'percentual' => 100,
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->delete("/proprietarios/{$v->id}");

    $response->assertSessionHasErrors(['proprietario']);
    expect($v->fresh()->status)->toBe('ativo');
});

test('inativação ok quando proprietário não tem titularidades', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $u = User::factory()->create();
    $v = Vinculo::create(['user_id' => $u->id, 'tenant_id' => $tenant->id, 'papel' => 'proprietario', 'status' => 'ativo']);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->delete("/proprietarios/{$v->id}");

    $response->assertSessionHasNoErrors();
    expect($v->fresh()->status)->toBe('inativo');
});

test('admin não consegue editar proprietário de outro tenant', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $outroTenant = Tenant::factory()->create(['is_exempt_from_subscription' => true]);
    $u = User::factory()->create();
    $v = Vinculo::create(['user_id' => $u->id, 'tenant_id' => $outroTenant->id, 'papel' => 'proprietario', 'status' => 'ativo']);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->get("/proprietarios/{$v->id}/editar");

    // O resolveRouteBinding retorna null e o Laravel emite 404.
    $response->assertNotFound();
});
