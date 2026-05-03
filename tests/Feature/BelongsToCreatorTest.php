<?php

use App\Models\EntidadeExterna;
use App\Models\Imovel;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantService;

it('preenche criado_por_user_id automaticamente quando há auth', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $this->actingAs($user);
    app(TenantService::class)->setTenant($tenant);

    $entidade = EntidadeExterna::create([
        'nome' => 'Teste',
        'tipo' => 'administradora_condominio',
    ]);

    expect($entidade->criado_por_user_id)->toBe($user->id);
    expect($entidade->atualizado_por_user_id)->toBeNull();
});

it('preenche atualizado_por_user_id quando há edição com auth', function () {
    [$tenant, $user] = setupTenantComAdmin();

    $this->actingAs($user);
    app(TenantService::class)->setTenant($tenant);

    $entidade = EntidadeExterna::create([
        'nome' => 'Original',
        'tipo' => 'administradora_condominio',
    ]);

    // Outro user faz a edição
    $outroUser = User::factory()->create();
    $this->actingAs($outroUser);

    $entidade->update(['nome' => 'Editado']);

    expect($entidade->fresh()->criado_por_user_id)->toBe($user->id);
    expect($entidade->fresh()->atualizado_por_user_id)->toBe($outroUser->id);
});

it('deixa criado_por_user_id null quando não há auth', function () {
    $tenant = Tenant::factory()->create();
    app(TenantService::class)->setTenant($tenant);

    // Sem actingAs — simula seeder/job
    $imovel = Imovel::create([
        'tenant_id' => $tenant->id,
        'cep' => '20040020',
        'logradouro' => 'Av. Rio Branco',
        'numero' => '1',
        'bairro' => 'Centro',
        'cidade' => 'RJ',
        'uf' => 'RJ',
        'tipo' => 'apartamento',
    ]);

    expect($imovel->criado_por_user_id)->toBeNull();
});
