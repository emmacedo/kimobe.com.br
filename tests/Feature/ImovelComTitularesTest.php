<?php

use App\Models\DadosBancarios;
use App\Models\Imovel;
use App\Models\User;
use App\Models\Vinculo;

function dadosImovelTit(array $overrides = []): array
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

function criarProprietarioVinculado($tenantId): Vinculo
{
    $u = User::factory()->create();

    return Vinculo::create([
        'user_id' => $u->id,
        'tenant_id' => $tenantId,
        'papel' => 'proprietario',
        'status' => 'ativo',
    ]);
}

test('imóvel pode ser criado com titulares juntos em transação', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $p1 = criarProprietarioVinculado($tenant->id);
    $p2 = criarProprietarioVinculado($tenant->id);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelTit([
            'titulares' => [
                ['vinculo_id' => $p1->id, 'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 60],
                ['vinculo_id' => $p2->id, 'tipo_titular' => 'pessoa_fisica', 'papel' => 'observador', 'percentual' => 40],
            ],
        ]));

    $response->assertSessionHasNoErrors();
    $imovel = Imovel::where('tenant_id', $tenant->id)->first();
    expect($imovel->titularidades()->count())->toBe(2);
    expect($imovel->titularidades()->where('papel', 'responsavel')->count())->toBe(1);
});

test('imóvel sem titulares cria normalmente e redireciona para edit', function () {
    [$tenant, $admin] = setupTenantComAdmin();

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelTit());

    $response->assertSessionHasNoErrors();
    $imovel = Imovel::where('tenant_id', $tenant->id)->first();
    expect($imovel->titularidades()->count())->toBe(0);
    $response->assertRedirect("/imoveis/{$imovel->id}/editar");
});

test('imóvel com titulares redireciona para show', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $p = criarProprietarioVinculado($tenant->id);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelTit([
            'titulares' => [
                ['vinculo_id' => $p->id, 'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 100],
            ],
        ]));

    $response->assertSessionHasNoErrors();
    $imovel = Imovel::where('tenant_id', $tenant->id)->first();
    $response->assertRedirect("/imoveis/{$imovel->id}");
});

test('rejeita 0 ou 2 responsáveis ao criar imóvel com titulares', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $p1 = criarProprietarioVinculado($tenant->id);
    $p2 = criarProprietarioVinculado($tenant->id);

    // 0 responsáveis
    $r0 = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelTit([
            'titulares' => [
                ['vinculo_id' => $p1->id, 'tipo_titular' => 'pessoa_fisica', 'papel' => 'observador', 'percentual' => 50],
                ['vinculo_id' => $p2->id, 'tipo_titular' => 'pessoa_fisica', 'papel' => 'observador', 'percentual' => 50],
            ],
        ]));
    $r0->assertSessionHasErrors(['titulares']);

    // 2 responsáveis
    $r2 = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelTit([
            'titulares' => [
                ['vinculo_id' => $p1->id, 'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 50],
                ['vinculo_id' => $p2->id, 'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 50],
            ],
        ]));
    $r2->assertSessionHasErrors(['titulares']);
});

test('soma de percentuais maior que 100 é rejeitada', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $p1 = criarProprietarioVinculado($tenant->id);
    $p2 = criarProprietarioVinculado($tenant->id);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelTit([
            'titulares' => [
                ['vinculo_id' => $p1->id, 'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 70],
                ['vinculo_id' => $p2->id, 'tipo_titular' => 'pessoa_fisica', 'papel' => 'observador', 'percentual' => 50],
            ],
        ]));

    $response->assertSessionHasErrors(['titulares']);
});

test('vinculo_id duplicado é rejeitado', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $p = criarProprietarioVinculado($tenant->id);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelTit([
            'titulares' => [
                ['vinculo_id' => $p->id, 'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 50],
                ['vinculo_id' => $p->id, 'tipo_titular' => 'pessoa_fisica', 'papel' => 'observador', 'percentual' => 50],
            ],
        ]));

    $response->assertSessionHasErrors(['titulares']);
});

test('proprietário de outro tenant é rejeitado nos titulares', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    [$outroTenant] = setupTenantComAdmin();
    $pOutro = criarProprietarioVinculado($outroTenant->id);

    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelTit([
            'titulares' => [
                ['vinculo_id' => $pOutro->id, 'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 100],
            ],
        ]));

    $response->assertSessionHasErrors(['titulares.0.vinculo_id']);
});

test('dados_bancarios_id de outro vínculo é rejeitado ao criar com titulares', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $p1 = criarProprietarioVinculado($tenant->id);
    $p2 = criarProprietarioVinculado($tenant->id);

    // Cria conta bancária pertencente ao p2
    $contaP2 = DadosBancarios::factory()->create([
        'tenant_id' => $tenant->id,
        'vinculo_id' => $p2->id,
    ]);

    // Tenta usar a conta de p2 com titular p1 — deve falhar
    $response = $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelTit([
            'titulares' => [
                [
                    'vinculo_id' => $p1->id,
                    'tipo_titular' => 'pessoa_fisica',
                    'papel' => 'responsavel',
                    'percentual' => 100,
                    'dados_bancarios_id' => $contaP2->id,
                ],
            ],
        ]));

    $response->assertSessionHasErrors(['titulares.0.dados_bancarios_id']);
});

test('rollback: se um titular falha, imóvel não é criado', function () {
    [$tenant, $admin] = setupTenantComAdmin();
    $p1 = criarProprietarioVinculado($tenant->id);

    $imoveisAntes = Imovel::where('tenant_id', $tenant->id)->count();

    // Tenta criar com vinculo inválido (fará a validação falhar antes do create do imóvel)
    $this->actingAs($admin)
        ->withSession(['tenant_id' => $tenant->id])
        ->post('/imoveis', dadosImovelTit([
            'titulares' => [
                ['vinculo_id' => 99999, 'tipo_titular' => 'pessoa_fisica', 'papel' => 'responsavel', 'percentual' => 100],
            ],
        ]));

    // Imóvel não foi criado.
    expect(Imovel::where('tenant_id', $tenant->id)->count())->toBe($imoveisAntes);
});
