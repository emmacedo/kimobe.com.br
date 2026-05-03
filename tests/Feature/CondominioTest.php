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
                'dia_vencimento' => 10,
                'valor' => '850,50',
                'acesso_login' => 'cond123',
                'acesso_senha' => 'senha-secreta',
                'acesso_descricao' => 'Portal: https://exemplo.com',
            ],
        ]));

    $response->assertSessionHasNoErrors();
    $imovel = Imovel::where('tenant_id', $tenant->id)->first();
    expect($imovel->condominio)->not->toBeNull();
    expect($imovel->condominio->entidade_externa_id)->toBe($adm->id);
    expect($imovel->condominio->dia_vencimento)->toBe(10);
    expect((float) $imovel->condominio->valor)->toBe(850.50);
    expect($imovel->condominio->acesso_senha)->toBe('senha-secreta');
});

test('condomínio com todos os campos vazios não é criado', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelCond([
            'condominio' => [
                'entidade_externa_id' => null,
                'dia_vencimento' => null,
                'valor' => null,
                'acesso_login' => '',
                'acesso_senha' => '',
                'acesso_descricao' => '',
            ],
        ]));

    $response->assertSessionHasNoErrors();
    $imovel = Imovel::where('tenant_id', $tenant->id)->first();
    expect($imovel->condominio)->toBeNull();
});

test('dia de vencimento fora do intervalo 1-31 falha', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelCond([
            'condominio' => ['dia_vencimento' => 32],
        ]));

    $response->assertSessionHasErrors(['condominio.dia_vencimento']);
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

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->put("/imoveis/{$imovel->id}", dadosImovelCond([
            'condominio' => ['valor' => '500,00', 'dia_vencimento' => 5],
        ]));

    $response->assertSessionHasNoErrors();
    expect($imovel->fresh()->condominio)->not->toBeNull();
    expect((float) $imovel->fresh()->condominio->valor)->toBe(500.00);
});

test('update atualiza condomínio existente', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    Condominio::factory()->create([
        'tenant_id' => $tenant->id,
        'imovel_id' => $imovel->id,
        'valor' => 100,
        'dia_vencimento' => 5,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->put("/imoveis/{$imovel->id}", dadosImovelCond([
            'condominio' => ['valor' => '750,00', 'dia_vencimento' => 15],
        ]));

    $response->assertSessionHasNoErrors();
    $imovel->refresh()->load('condominio');
    expect((float) $imovel->condominio->valor)->toBe(750.00);
    expect($imovel->condominio->dia_vencimento)->toBe(15);
    // Não criou um segundo registro
    expect(Condominio::where('imovel_id', $imovel->id)->count())->toBe(1);
});

test('update remove condomínio quando todos os campos ficam vazios', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    Condominio::factory()->create([
        'tenant_id' => $tenant->id,
        'imovel_id' => $imovel->id,
        'valor' => 500,
    ]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->put("/imoveis/{$imovel->id}", dadosImovelCond([
            'condominio' => [
                'entidade_externa_id' => null,
                'dia_vencimento' => null,
                'valor' => null,
                'acesso_login' => '',
                'acesso_senha' => '',
                'acesso_descricao' => '',
            ],
        ]));

    $response->assertSessionHasNoErrors();
    expect($imovel->fresh()->condominio)->toBeNull();
});

test('upsert via endpoint dedicado funciona', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);

    $response = $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->put("/imoveis/{$imovel->id}/condominio", [
            'valor' => '1.250,00',
            'dia_vencimento' => 20,
        ]);

    $response->assertSessionHasNoErrors();
    expect((float) $imovel->fresh()->condominio->valor)->toBe(1250.00);
    expect($imovel->fresh()->condominio->dia_vencimento)->toBe(20);
});

test('remoção via update faz soft delete (não hard delete)', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $cond = Condominio::factory()->create([
        'tenant_id' => $tenant->id,
        'imovel_id' => $imovel->id,
        'valor' => 500,
    ]);

    $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->put("/imoveis/{$imovel->id}", dadosImovelCond([
            'condominio' => [
                'entidade_externa_id' => null,
                'dia_vencimento' => null,
                'valor' => null,
                'acesso_login' => '',
                'acesso_senha' => '',
                'acesso_descricao' => '',
            ],
        ]))
        ->assertSessionHasNoErrors();

    expect($cond->fresh()->trashed())->toBeTrue();
});

test('upsert restaura registro soft-deleted em vez de criar duplicata', function () {
    [$tenant, $user] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $cond = Condominio::factory()->create([
        'tenant_id' => $tenant->id,
        'imovel_id' => $imovel->id,
        'valor' => 100,
    ]);
    $cond->delete(); // soft delete

    $this->actingAs($user)
        ->withSession(['tenant_id' => $tenant->id])
        ->put("/imoveis/{$imovel->id}/condominio", [
            'valor' => '999,00',
            'dia_vencimento' => 7,
        ])
        ->assertSessionHasNoErrors();

    // Apenas 1 registro existe (incluindo soft-deleted), restaurado e atualizado
    expect(Condominio::withTrashed()->where('imovel_id', $imovel->id)->count())->toBe(1);
    expect($imovel->fresh()->condominio?->trashed())->toBeFalse();
    expect((float) $imovel->fresh()->condominio->valor)->toBe(999.00);
});
