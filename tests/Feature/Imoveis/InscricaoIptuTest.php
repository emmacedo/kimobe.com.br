<?php

use App\Models\Imovel;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vinculo;

function actingAsAdminDoTenant(): array
{
    $tenant = Tenant::factory()->create(['is_exempt_from_subscription' => true]);
    $user = User::factory()->create();
    Vinculo::create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'papel' => 'admin',
        'status' => 'ativo',
    ]);

    return [$tenant, $user];
}

function dadosImovelValidos(array $overrides = []): array
{
    return array_merge([
        'cep' => '20040-020',
        'logradouro' => 'Av. Rio Branco',
        'numero' => '100',
        'bairro' => 'Centro',
        'cidade' => 'Rio de Janeiro',
        'uf' => 'RJ',
        'tipo' => 'apartamento',
    ], $overrides);
}

test('imóvel pode ser criado sem inscrição IPTU', function () {
    [$tenant, $user] = actingAsAdminDoTenant();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelValidos());

    $response->assertSessionHasNoErrors();
    expect(Imovel::where('tenant_id', $tenant->id)->first()->inscricao_iptu)->toBeNull();
});

test('imóvel pode ser criado com inscrição IPTU', function () {
    [$tenant, $user] = actingAsAdminDoTenant();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelValidos(['inscricao_iptu' => '12.345.678-9']));

    $response->assertSessionHasNoErrors();
    expect(Imovel::where('tenant_id', $tenant->id)->first()->inscricao_iptu)->toBe('12.345.678-9');
});

test('inscrição IPTU não pode ter mais de 50 caracteres', function () {
    [$tenant, $user] = actingAsAdminDoTenant();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelValidos(['inscricao_iptu' => str_repeat('A', 51)]));

    $response->assertSessionHasErrors(['inscricao_iptu']);
});

test('inscrição IPTU pode ser atualizada via update', function () {
    [$tenant, $user] = actingAsAdminDoTenant();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id, 'inscricao_iptu' => null]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->put("/imoveis/{$imovel->id}", dadosImovelValidos([
            'inscricao_iptu' => 'NOVA-12345',
        ]));

    $response->assertSessionHasNoErrors();
    expect($imovel->fresh()->inscricao_iptu)->toBe('NOVA-12345');
});
