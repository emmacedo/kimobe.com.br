<?php

use App\Models\Administradora;
use App\Models\Tenant;

test('admin acessa listagem de administradoras', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->get('/administradoras');

    $response->assertOk();
});

test('admin cria administradora apenas com nome obrigatório', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/administradoras', ['nome' => 'Imobiliária Teste']);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect('/administradoras');

    expect(Administradora::where('tenant_id', $tenant->id)->count())->toBe(1);
    expect(Administradora::where('tenant_id', $tenant->id)->first()->nome)->toBe('Imobiliária Teste');
});

test('nome é obrigatório ao criar administradora', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/administradoras', []);

    $response->assertSessionHasErrors(['nome']);
});

test('admin cria administradora completa com endereço e contato', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/administradoras', [
            'nome' => 'Predial Carioca',
            'cpf_cnpj' => '12.345.678/0001-90',
            'telefone' => '(21) 99999-8888',
            'email' => 'contato@predialcarioca.com.br',
            'site' => 'https://predialcarioca.com.br',
            'contato_interno_nome' => 'João da Silva',
            'cep' => '20040-020',
            'logradouro' => 'Av. Rio Branco',
            'numero' => '100',
            'complemento' => 'Sala 1010',
            'bairro' => 'Centro',
            'cidade' => 'Rio de Janeiro',
            'uf' => 'RJ',
            'observacoes' => 'Atende de segunda a sexta.',
        ]);

    $response->assertSessionHasNoErrors();

    $adm = Administradora::where('tenant_id', $tenant->id)->first();
    expect($adm->cpf_cnpj)->toBe('12345678000190'); // sanitizado para apenas dígitos
    expect($adm->telefone)->toBe('21999998888');
    expect($adm->cep)->toBe('20040020');
    expect($adm->email)->toBe('contato@predialcarioca.com.br');
});

test('CPF/CNPJ inválido falha na validação', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/administradoras', [
            'nome' => 'Teste',
            'cpf_cnpj' => '123', // muito curto
        ]);

    $response->assertSessionHasErrors(['cpf_cnpj']);
});

test('admin atualiza administradora', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $adm = Administradora::factory()->create(['tenant_id' => $tenant->id, 'nome' => 'Antigo']);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->put("/administradoras/{$adm->id}", [
            'nome' => 'Novo Nome',
            'telefone' => '(21) 3333-4444',
        ]);

    $response->assertSessionHasNoErrors();
    expect($adm->fresh()->nome)->toBe('Novo Nome');
    expect($adm->fresh()->telefone)->toBe('2133334444');
});

test('admin exclui administradora (soft delete)', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $adm = Administradora::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->delete("/administradoras/{$adm->id}");

    $response->assertSessionHasNoErrors();
    expect($adm->fresh()->trashed())->toBeTrue();
});

test('admin não vê administradoras de outro tenant', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $outroTenant = Tenant::factory()->create(['is_exempt_from_subscription' => true]);
    Administradora::factory()->create(['tenant_id' => $outroTenant->id, 'nome' => 'Da Outra Empresa']);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->get('/administradoras');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('administradoras/index')
        ->where('administradoras.total', 0));
});

test('endpoint inline cria administradora e retorna JSON', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson('/administradoras/inline', ['nome' => 'Criada Inline']);

    $response->assertOk();
    $response->assertJson(['nome' => 'Criada Inline']);
    expect(Administradora::where('tenant_id', $tenant->id)->where('nome', 'Criada Inline')->exists())->toBeTrue();
});
