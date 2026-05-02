<?php

use App\Models\Contrato;
use App\Models\Imovel;
use App\Models\Tenant;
use App\Models\Titularidade;
use App\Models\User;
use App\Models\Vinculo;

test('busca de imóveis disponíveis exclui os com contrato ativo', function () {
    [$tenant, $admin] = setupTenantComAdmin();

    $imovelLivre = Imovel::factory()->create(['tenant_id' => $tenant->id, 'logradouro' => 'Rua das Flores']);
    $imovelOcupado = Imovel::factory()->create(['tenant_id' => $tenant->id, 'logradouro' => 'Rua das Pedras']);
    $u = User::factory()->create();
    $v = Vinculo::create(['user_id' => $u->id, 'tenant_id' => $tenant->id, 'papel' => 'inquilino', 'status' => 'ativo']);
    Contrato::factory()->create([
        'tenant_id' => $tenant->id,
        'imovel_id' => $imovelOcupado->id,
        'inquilino_vinculo_id' => $v->id,
        'status' => 'ativo',
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->getJson('/contratos/imoveis-disponiveis?q=rua das');

    $response->assertOk();
    $ids = collect($response->json())->pluck('id')->toArray();
    expect($ids)->toContain($imovelLivre->id);
    expect($ids)->not->toContain($imovelOcupado->id);
});

test('busca de imóveis disponíveis inclui os com contrato encerrado', function () {
    [$tenant, $admin] = setupTenantComAdmin();

    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id, 'logradouro' => 'Av. Final']);
    $u = User::factory()->create();
    $v = Vinculo::create(['user_id' => $u->id, 'tenant_id' => $tenant->id, 'papel' => 'inquilino', 'status' => 'ativo']);
    Contrato::factory()->create([
        'tenant_id' => $tenant->id,
        'imovel_id' => $imovel->id,
        'inquilino_vinculo_id' => $v->id,
        'status' => 'encerrado',
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->getJson('/contratos/imoveis-disponiveis?q=final');

    $response->assertOk();
    $ids = collect($response->json())->pluck('id')->toArray();
    expect($ids)->toContain($imovel->id);
});

test('busca aplica AND por palavra em endereço e nome de proprietário', function () {
    [$tenant, $admin] = setupTenantComAdmin();

    // Imóvel A: endereço "Rua Centro" + proprietário "João Silva"
    $imA = Imovel::factory()->create(['tenant_id' => $tenant->id, 'logradouro' => 'Rua Centro', 'cidade' => 'Niterói']);
    $u1 = User::factory()->create(['name' => 'João Silva']);
    $v1 = Vinculo::create(['user_id' => $u1->id, 'tenant_id' => $tenant->id, 'papel' => 'proprietario', 'status' => 'ativo']);
    Titularidade::factory()->create([
        'tenant_id' => $tenant->id, 'imovel_id' => $imA->id, 'vinculo_id' => $v1->id,
        'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 100,
    ]);

    // Imóvel B: endereço "Rua Centro" + proprietário "Maria Souza"
    $imB = Imovel::factory()->create(['tenant_id' => $tenant->id, 'logradouro' => 'Rua Centro', 'cidade' => 'Rio de Janeiro']);
    $u2 = User::factory()->create(['name' => 'Maria Souza']);
    $v2 = Vinculo::create(['user_id' => $u2->id, 'tenant_id' => $tenant->id, 'papel' => 'proprietario', 'status' => 'ativo']);
    Titularidade::factory()->create([
        'tenant_id' => $tenant->id, 'imovel_id' => $imB->id, 'vinculo_id' => $v2->id,
        'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 100,
    ]);

    // Busca "centro joão" — só imóvel A satisfaz: endereço tem "centro" + proprietário tem "joão"
    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->getJson('/contratos/imoveis-disponiveis?q=centro joão');

    $response->assertOk();
    $ids = collect($response->json())->pluck('id')->toArray();
    expect($ids)->toContain($imA->id);
    expect($ids)->not->toContain($imB->id);
});

test('busca não retorna imóveis de outro tenant', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $outroTenant = Tenant::factory()->create(['is_exempt_from_subscription' => true]);
    Imovel::factory()->create(['tenant_id' => $outroTenant->id, 'logradouro' => 'Rua Outra Tenant']);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->getJson('/contratos/imoveis-disponiveis?q=outra');

    $response->assertOk();
    expect($response->json())->toBe([]);
});

test('termo com menos de 2 caracteres retorna vazio', function () {
    [$tenant, $admin] = setupTenantComAdmin();

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->getJson('/contratos/imoveis-disponiveis?q=a');

    $response->assertOk();
    expect($response->json())->toBe([]);
});
