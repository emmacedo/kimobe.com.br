<?php

use App\Models\EntidadeExterna;
use App\Models\Tenant;

test('admin acessa listagem de entidades externas', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->get('/entidades-externas');

    $response->assertOk();
});

test('admin cria entidade externa apenas com nome e tipo obrigatórios', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/entidades-externas', [
            'nome' => 'Imobiliária Teste',
            'tipo' => 'administradora_condominio',
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect('/entidades-externas');

    expect(EntidadeExterna::where('tenant_id', $tenant->id)->count())->toBe(1);
    expect(EntidadeExterna::where('tenant_id', $tenant->id)->first()->nome)->toBe('Imobiliária Teste');
});

test('nome é obrigatório ao criar entidade externa', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/entidades-externas', ['tipo' => 'administradora_condominio']);

    $response->assertSessionHasErrors(['nome']);
});

test('tipo é obrigatório ao criar entidade externa', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/entidades-externas', ['nome' => 'Sem tipo']);

    $response->assertSessionHasErrors(['tipo']);
});

test('tipo inválido é rejeitado', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/entidades-externas', [
            'nome' => 'Teste',
            'tipo' => 'tipo_inexistente',
        ]);

    $response->assertSessionHasErrors(['tipo']);
});

test('admin cria entidade externa completa com endereço e contato', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/entidades-externas', [
            'nome' => 'Predial Carioca',
            'tipo' => 'administradora_condominio',
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

    $ent = EntidadeExterna::where('tenant_id', $tenant->id)->first();
    expect($ent->cpf_cnpj)->toBe('12345678000190');
    expect($ent->telefone)->toBe('21999998888');
    expect($ent->cep)->toBe('20040020');
    expect($ent->email)->toBe('contato@predialcarioca.com.br');
    expect($ent->tipo)->toBe('administradora_condominio');
});

test('CPF/CNPJ inválido falha na validação', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/entidades-externas', [
            'nome' => 'Teste',
            'tipo' => 'administradora_condominio',
            'cpf_cnpj' => '123',
        ]);

    $response->assertSessionHasErrors(['cpf_cnpj']);
});

test('admin atualiza entidade externa', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $ent = EntidadeExterna::factory()->create(['tenant_id' => $tenant->id, 'nome' => 'Antigo']);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->put("/entidades-externas/{$ent->id}", [
            'nome' => 'Novo Nome',
            'tipo' => 'administradora_condominio',
            'telefone' => '(21) 3333-4444',
        ]);

    $response->assertSessionHasNoErrors();
    expect($ent->fresh()->nome)->toBe('Novo Nome');
    expect($ent->fresh()->telefone)->toBe('2133334444');
});

test('admin exclui entidade externa (soft delete)', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $ent = EntidadeExterna::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->delete("/entidades-externas/{$ent->id}");

    $response->assertSessionHasNoErrors();
    expect($ent->fresh()->trashed())->toBeTrue();
});

test('admin não vê entidades externas de outro tenant', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $outroTenant = Tenant::factory()->create(['is_exempt_from_subscription' => true]);
    EntidadeExterna::factory()->create(['tenant_id' => $outroTenant->id, 'nome' => 'Da Outra Empresa']);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->get('/entidades-externas');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('entidades-externas/index')
        ->where('entidades.total', 0));
});

test('endpoint inline cria entidade externa e retorna JSON', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson('/entidades-externas/inline', [
            'nome' => 'Criada Inline',
            'tipo' => 'administradora_condominio',
        ]);

    $response->assertOk();
    $response->assertJson(['nome' => 'Criada Inline']);
    expect(EntidadeExterna::where('tenant_id', $tenant->id)->where('nome', 'Criada Inline')->exists())->toBeTrue();
});
