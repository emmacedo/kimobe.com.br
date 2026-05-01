<?php

use App\Models\Imovel;
use App\Models\Scopes\TenantScope;
use App\Models\Titularidade;
use App\Models\User;
use App\Models\Vinculo;

function criarProprietario($tenantId, string $nome = 'Prop Teste'): Vinculo
{
    $u = User::factory()->create(['name' => $nome]);

    return Vinculo::create([
        'user_id' => $u->id,
        'tenant_id' => $tenantId,
        'papel' => 'proprietario',
        'status' => 'ativo',
    ]);
}

test('marcar 2º titular como responsável demove o 1º para observador', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);

    $p1 = criarProprietario($tenant->id, 'Primeiro');
    $p2 = criarProprietario($tenant->id, 'Segundo');

    // Adiciona 1º como responsável
    $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/imoveis/{$imovel->id}/titularidades", [
            'vinculo_id' => $p1->id,
            'tipo_titular' => 'pessoa_fisica',
            'papel' => 'responsavel',
            'percentual' => 50,
        ])->assertCreated();

    // Adiciona 2º como responsável — deve demover o 1º
    $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/imoveis/{$imovel->id}/titularidades", [
            'vinculo_id' => $p2->id,
            'tipo_titular' => 'pessoa_fisica',
            'papel' => 'responsavel',
            'percentual' => 50,
        ])->assertCreated();

    $titulares = Titularidade::withoutGlobalScopes()->where('imovel_id', $imovel->id)->get();
    $responsaveis = $titulares->where('papel', 'responsavel');

    expect($responsaveis->count())->toBe(1);
    expect($responsaveis->first()->vinculo_id)->toBe($p2->id);
});

test('atualizar T2 para responsável demove T1', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);

    $p1 = criarProprietario($tenant->id, 'P1');
    $p2 = criarProprietario($tenant->id, 'P2');

    $t1 = Titularidade::factory()->create([
        'tenant_id' => $tenant->id, 'imovel_id' => $imovel->id, 'vinculo_id' => $p1->id,
        'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 50,
    ]);
    $t2 = Titularidade::factory()->create([
        'tenant_id' => $tenant->id, 'imovel_id' => $imovel->id, 'vinculo_id' => $p2->id,
        'tipo_titular' => 'pessoa_fisica', 'papel' => 'observador', 'percentual' => 50,
    ]);

    $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->putJson("/imoveis/{$imovel->id}/titularidades/{$t2->id}", [
            'tipo_titular' => 'pessoa_fisica',
            'papel' => 'responsavel',
            'percentual' => 50,
        ])->assertOk();

    expect($t1->fresh()->papel)->toBe('observador');
    expect($t2->fresh()->papel)->toBe('responsavel');
});

test('remover responsável auto-promove o próximo titular', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);

    $p1 = criarProprietario($tenant->id, 'P1');
    $p2 = criarProprietario($tenant->id, 'P2');

    $t1 = Titularidade::factory()->create([
        'tenant_id' => $tenant->id, 'imovel_id' => $imovel->id, 'vinculo_id' => $p1->id,
        'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 50,
    ]);
    $t2 = Titularidade::factory()->create([
        'tenant_id' => $tenant->id, 'imovel_id' => $imovel->id, 'vinculo_id' => $p2->id,
        'tipo_titular' => 'pessoa_fisica', 'papel' => 'observador', 'percentual' => 50,
    ]);

    $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->deleteJson("/imoveis/{$imovel->id}/titularidades/{$t1->id}")
        ->assertOk();

    // Soft delete: registro existe com deleted_at preenchido, mas não é mais visível.
    expect($t1->fresh()->trashed())->toBeTrue();
    expect($t2->fresh()->papel)->toBe('responsavel');
});

test('IDOR bloqueado: update de titularidade de outro imóvel retorna 404', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovelA = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $imovelB = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $p = criarProprietario($tenant->id);
    $tA = Titularidade::factory()->create([
        'tenant_id' => $tenant->id, 'imovel_id' => $imovelA->id, 'vinculo_id' => $p->id,
        'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 100,
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->putJson("/imoveis/{$imovelB->id}/titularidades/{$tA->id}", [
            'tipo_titular' => 'pessoa_fisica',
            'papel' => 'observador',
            'percentual' => 100,
        ]);

    $response->assertNotFound();
});

test('IDOR bloqueado: destroy de titularidade de outro imóvel retorna 404', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovelA = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $imovelB = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $p = criarProprietario($tenant->id);
    $tA = Titularidade::factory()->create([
        'tenant_id' => $tenant->id, 'imovel_id' => $imovelA->id, 'vinculo_id' => $p->id,
        'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 100,
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->deleteJson("/imoveis/{$imovelB->id}/titularidades/{$tA->id}");

    $response->assertNotFound();
});

test('remover último titular não tenta promover ninguém', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $p = criarProprietario($tenant->id);
    $t = Titularidade::factory()->create([
        'tenant_id' => $tenant->id, 'imovel_id' => $imovel->id, 'vinculo_id' => $p->id,
        'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 100,
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->deleteJson("/imoveis/{$imovel->id}/titularidades/{$t->id}");

    $response->assertOk();
    // Soft delete: a titularidade existe no banco mas não conta como ativa.
    expect(Titularidade::withoutGlobalScopes([TenantScope::class])->where('imovel_id', $imovel->id)->count())->toBe(0);
});
