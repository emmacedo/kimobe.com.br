<?php

use App\Models\Condominio;
use App\Models\EntidadeExterna;
use App\Models\Imovel;
use App\Models\Tenant;

function dadosImovelCond(array $overrides = []): array
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

test('imóvel pode ser criado sem condomínio', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelCond());

    $response->assertSessionHasNoErrors();
    $imovel = Imovel::where('tenant_id', $tenant->id)->first();
    expect($imovel->condominio)->toBeNull();
});

test('imóvel pode ser criado com condomínio completo', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $adm = EntidadeExterna::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelCond([
            'condominio' => [
                'entidade_externa_id' => $adm->id,
                'acesso_login' => 'cond123',
                'acesso_senha' => 'senha-secreta',
                'acesso_descricao' => 'Portal: https://exemplo.com',
            ],
        ]));

    $response->assertSessionHasNoErrors();
    $imovel = Imovel::where('tenant_id', $tenant->id)->first();
    expect($imovel->condominio)->not->toBeNull();
    expect($imovel->condominio->entidade_externa_id)->toBe($adm->id);
    expect($imovel->condominio->acesso_senha)->toBe('senha-secreta');
});

test('condomínio com todos os campos vazios não é criado', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelCond([
            'condominio' => [
                'entidade_externa_id' => null,
                'acesso_login' => '',
                'acesso_senha' => '',
                'acesso_descricao' => '',
            ],
        ]));

    $response->assertSessionHasNoErrors();
    $imovel = Imovel::where('tenant_id', $tenant->id)->first();
    expect($imovel->condominio)->toBeNull();
});

test('entidade externa de outro tenant é rejeitada', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $outroTenant = Tenant::factory()->create();
    $admOutra = EntidadeExterna::factory()->create(['tenant_id' => $outroTenant->id]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelCond([
            'condominio' => ['entidade_externa_id' => $admOutra->id],
        ]));

    $response->assertSessionHasErrors(['condominio.entidade_externa_id']);
});

test('update adiciona condomínio a imóvel sem condomínio', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $adm = EntidadeExterna::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->put("/imoveis/{$imovel->id}", dadosImovelCond([
            'condominio' => ['entidade_externa_id' => $adm->id, 'acesso_login' => 'novo'],
        ]));

    $response->assertSessionHasNoErrors();
    expect($imovel->fresh()->condominio)->not->toBeNull();
    expect($imovel->fresh()->condominio->entidade_externa_id)->toBe($adm->id);
});

test('update atualiza condomínio existente', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $adm = EntidadeExterna::factory()->create(['tenant_id' => $tenant->id]);
    Condominio::factory()->create([
        'tenant_id' => $tenant->id,
        'imovel_id' => $imovel->id,
        'acesso_login' => 'antigo',
    ]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->put("/imoveis/{$imovel->id}", dadosImovelCond([
            'condominio' => ['entidade_externa_id' => $adm->id, 'acesso_login' => 'atualizado'],
        ]));

    $response->assertSessionHasNoErrors();
    $imovel->refresh()->load('condominio');
    expect($imovel->condominio->entidade_externa_id)->toBe($adm->id);
    expect($imovel->condominio->acesso_login)->toBe('atualizado');
    // Não criou um segundo registro
    expect(Condominio::where('imovel_id', $imovel->id)->count())->toBe(1);
});

test('update remove condomínio quando todos os campos ficam vazios', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    Condominio::factory()->create([
        'tenant_id' => $tenant->id,
        'imovel_id' => $imovel->id,
        'acesso_login' => 'qualquer',
    ]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->put("/imoveis/{$imovel->id}", dadosImovelCond([
            'condominio' => [
                'entidade_externa_id' => null,
                'acesso_login' => '',
                'acesso_senha' => '',
                'acesso_descricao' => '',
            ],
        ]));

    $response->assertSessionHasNoErrors();
    expect($imovel->fresh()->condominio)->toBeNull();
});

test('remoção via update faz soft delete (não hard delete)', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $cond = Condominio::factory()->create([
        'tenant_id' => $tenant->id,
        'imovel_id' => $imovel->id,
        'acesso_login' => 'qualquer',
    ]);

    $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->put("/imoveis/{$imovel->id}", dadosImovelCond([
            'condominio' => [
                'entidade_externa_id' => null,
                'acesso_login' => '',
                'acesso_senha' => '',
                'acesso_descricao' => '',
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect($cond->fresh()->trashed())->toBeTrue();
});
