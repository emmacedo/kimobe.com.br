<?php

use App\Models\DadosBancarios;
use App\Models\Imovel;
use App\Models\Repasse;
use App\Models\Scopes\TenantScope;
use App\Models\Titularidade;
use App\Models\User;
use App\Models\Vinculo;

function criarPropAtivo($tenantId): Vinculo
{
    return Vinculo::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenantId,
        'papel' => 'proprietario',
        'status' => 'ativo',
    ]);
}

test('admin cria titularidade com 100% e responsavel', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $p = criarPropAtivo($tenant->id);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/imoveis/{$imovel->id}/titularidades", [
            'vinculo_id' => $p->id,
            'tipo_titular' => 'pessoa_fisica',
            'papel' => 'responsavel',
            'percentual' => 100,
        ]);

    $response->assertCreated();
    expect(Titularidade::where('imovel_id', $imovel->id)->count())->toBe(1);
});

test('rejeita vínculo inativo como titular', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $p = Vinculo::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenant->id,
        'papel' => 'proprietario',
        'status' => 'inativo',
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/imoveis/{$imovel->id}/titularidades", [
            'vinculo_id' => $p->id,
            'tipo_titular' => 'pessoa_fisica',
            'papel' => 'responsavel',
            'percentual' => 100,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['vinculo_id']);
});

test('rejeita proprietário duplicado no mesmo imóvel', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $p = criarPropAtivo($tenant->id);
    Titularidade::factory()->create([
        'tenant_id' => $tenant->id, 'imovel_id' => $imovel->id, 'vinculo_id' => $p->id,
        'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 100,
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/imoveis/{$imovel->id}/titularidades", [
            'vinculo_id' => $p->id,
            'tipo_titular' => 'pessoa_fisica',
            'papel' => 'observador',
            'percentual' => 50,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['vinculo_id']);
});

test('rejeita soma de percentuais maior que 100', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $p1 = criarPropAtivo($tenant->id);
    $p2 = criarPropAtivo($tenant->id);

    Titularidade::factory()->create([
        'tenant_id' => $tenant->id, 'imovel_id' => $imovel->id, 'vinculo_id' => $p1->id,
        'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 80,
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/imoveis/{$imovel->id}/titularidades", [
            'vinculo_id' => $p2->id,
            'tipo_titular' => 'pessoa_fisica',
            'papel' => 'observador',
            'percentual' => 30,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['percentual']);
});

test('rejeita conta bancária de outro vínculo', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $p1 = criarPropAtivo($tenant->id);
    $p2 = criarPropAtivo($tenant->id);
    $contaP2 = DadosBancarios::factory()->create([
        'tenant_id' => $tenant->id,
        'vinculo_id' => $p2->id,
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/imoveis/{$imovel->id}/titularidades", [
            'vinculo_id' => $p1->id,
            'tipo_titular' => 'pessoa_fisica',
            'papel' => 'responsavel',
            'percentual' => 100,
            'dados_bancarios_id' => $contaP2->id,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['dados_bancarios_id']);
});

test('update bloqueia troca de proprietario (vinculo_id ignorado)', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $p1 = criarPropAtivo($tenant->id);
    $p2 = criarPropAtivo($tenant->id);
    $t = Titularidade::factory()->create([
        'tenant_id' => $tenant->id, 'imovel_id' => $imovel->id, 'vinculo_id' => $p1->id,
        'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 100,
    ]);

    $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->putJson("/imoveis/{$imovel->id}/titularidades/{$t->id}", [
            'vinculo_id' => $p2->id, // tentativa de trocar — deve ser ignorada
            'tipo_titular' => 'empresa',
            'papel' => 'observador',
            'percentual' => 100,
        ])->assertOk();

    expect($t->fresh()->vinculo_id)->toBe($p1->id); // não trocou
    expect($t->fresh()->tipo_titular)->toBe('empresa'); // mas atualizou outros campos
});

test('destroy bloqueia remoção quando há repasses vinculados', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $p = criarPropAtivo($tenant->id);
    $t = Titularidade::factory()->create([
        'tenant_id' => $tenant->id, 'imovel_id' => $imovel->id, 'vinculo_id' => $p->id,
        'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 100,
    ]);
    Repasse::factory()->create(['tenant_id' => $tenant->id, 'titularidade_id' => $t->id]);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->deleteJson("/imoveis/{$imovel->id}/titularidades/{$t->id}");

    $response->assertStatus(422);
    expect($t->fresh()->trashed())->toBeFalse();
});

test('destroy faz soft delete (mantém dados para histórico)', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $p = criarPropAtivo($tenant->id);
    $t = Titularidade::factory()->create([
        'tenant_id' => $tenant->id, 'imovel_id' => $imovel->id, 'vinculo_id' => $p->id,
        'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 100,
    ]);

    $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->deleteJson("/imoveis/{$imovel->id}/titularidades/{$t->id}")
        ->assertOk();

    expect($t->fresh()->trashed())->toBeTrue();
    // Registro persiste com deleted_at — invisível em queries normais.
    expect(Titularidade::withoutGlobalScopes([TenantScope::class])->where('imovel_id', $imovel->id)->count())->toBe(0);
});

test('vínculo soft-deletado pode ser re-adicionado como titular', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $p = criarPropAtivo($tenant->id);

    $t = Titularidade::factory()->create([
        'tenant_id' => $tenant->id, 'imovel_id' => $imovel->id, 'vinculo_id' => $p->id,
        'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 100,
    ]);
    $t->delete();

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->postJson("/imoveis/{$imovel->id}/titularidades", [
            'vinculo_id' => $p->id,
            'tipo_titular' => 'pessoa_fisica',
            'papel' => 'responsavel',
            'percentual' => 100,
        ]);

    $response->assertCreated();
});
