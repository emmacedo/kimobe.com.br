<?php

use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\Imovel;
use App\Models\Repasse;
use App\Models\Tenant;
use App\Models\Titularidade;
use App\Models\User;
use App\Models\Vinculo;
use App\Services\FaturaService;
use App\Services\ItemCobrancaService;
use App\Services\TenantService;

function criarContratoParaSnapshot(string $modelo = 'por_recebimento'): Contrato
{
    $tenant = Tenant::factory()->create();
    app(TenantService::class)->setTenant($tenant);

    $imovel = Imovel::factory()->create(['tenant_id' => $tenant->id]);
    $vinculo = Vinculo::create([
        'user_id' => User::factory()->create()->id,
        'tenant_id' => $tenant->id,
        'papel' => 'inquilino',
        'status' => 'ativo',
    ]);

    return Contrato::create([
        'imovel_id' => $imovel->id,
        'inquilino_vinculo_id' => $vinculo->id,
        'data_inicio' => '2026-01-01',
        'data_fim' => '2026-12-31',
        'valor_aluguel' => 2000,
        'dia_vencimento' => 5,
        'modelo_repasse' => $modelo,
        'taxa_administracao_pct' => 10,
        'taxa_seguro_inadimplencia_pct' => $modelo === 'garantido' ? 4 : null,
        'indice_reajuste' => 'igpm',
        'mes_reajuste' => 1,
        'multa_atraso_pct' => 2.5,
        'juros_atraso_pct_dia' => 0.0333,
        'dias_carencia' => 3,
        'multa_rescisoria_pct' => null,
        'desconto_pontualidade_pct' => 5,
        'tipo_garantia' => 'sem_garantia',
        'status' => 'ativo',
    ]);
}

it('fatura captura snapshot dos percentuais do contrato na geração', function () {
    $contrato = criarContratoParaSnapshot();
    $fatura = app(FaturaService::class)->gerarFaturaIndividual($contrato, '03/2026');

    expect((float) $fatura->multa_atraso_pct_aplicada)->toBe(2.5);
    expect((float) $fatura->juros_atraso_pct_dia_aplicada)->toBe(0.0333);
    expect((float) $fatura->desconto_pontualidade_pct_aplicada)->toBe(5.0);
    expect($fatura->dias_carencia_aplicada)->toBe(3);
});

it('snapshot sobrevive a alterações no contrato após a geração', function () {
    $contrato = criarContratoParaSnapshot();
    $fatura = app(FaturaService::class)->gerarFaturaIndividual($contrato, '04/2026');

    // Contrato é alterado depois da geração
    $contrato->update(['multa_atraso_pct' => 10, 'dias_carencia' => 0]);

    // Snapshot da fatura permanece o original
    expect((float) $fatura->fresh()->multa_atraso_pct_aplicada)->toBe(2.5);
    expect($fatura->fresh()->dias_carencia_aplicada)->toBe(3);
});

it('repasse no modelo garantido captura percentuais aplicados', function () {
    $contrato = criarContratoParaSnapshot('garantido');

    Titularidade::create([
        'tenant_id' => $contrato->tenant_id,
        'imovel_id' => $contrato->imovel_id,
        'vinculo_id' => Vinculo::create([
            'user_id' => User::factory()->create()->id,
            'tenant_id' => $contrato->tenant_id,
            'papel' => 'proprietario',
            'status' => 'ativo',
        ])->id,
        'tipo_titular' => 'pessoa_fisica',
        'papel' => 'responsavel',
        'percentual' => 100,
    ]);

    $fatura = app(FaturaService::class)->gerarFaturaIndividual($contrato, '05/2026');

    $repasse = Repasse::where('fatura_id', $fatura->id)->firstOrFail();

    expect((float) $repasse->taxa_administracao_pct_aplicada)->toBe(10.0);
    expect((float) $repasse->taxa_seguro_inadimplencia_pct_aplicada)->toBe(4.0);
    expect((float) $repasse->percentual_titularidade_aplicado)->toBe(100.0);
});

it('baixa de pagamento usa snapshot da fatura mesmo se contrato mudou', function () {
    $contrato = criarContratoParaSnapshot();
    $fatura = app(FaturaService::class)->gerarFaturaIndividual($contrato, '06/2026');

    // Cria um item para a fatura ter valor_total > 0
    app(ItemCobrancaService::class)->criar($contrato, [
        'descricao' => 'Aluguel',
        'pagante' => 'inquilino', 'recebedor' => 'proprietario',
        'tipo' => 'avulso',
        'valor_unitario' => 1000,
        'mes_referencia' => '07/2026',
    ]);
    $fatura2 = app(FaturaService::class)->gerarFaturaIndividual($contrato, '07/2026');

    expect((float) $fatura2->valor_total)->toBe(1000.0);

    // Contrato é mudado: multa de 2.5% → 10% (drasticamente)
    $contrato->update(['multa_atraso_pct' => 10]);

    // Pagamento atrasado simulado — usar snapshot (2.5%) e não 10%
    expect((float) $fatura2->multa_atraso_pct_aplicada)->toBe(2.5);
});
