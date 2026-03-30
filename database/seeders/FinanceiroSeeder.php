<?php

namespace Database\Seeders;

use App\Models\Cobranca;
use App\Models\CobrancaItemExtra;
use App\Models\Contrato;
use App\Models\Imovel;
use App\Models\Repasse;
use App\Models\Tenant;
use App\Models\Titularidade;
use Illuminate\Database\Seeder;

class FinanceiroSeeder extends Seeder
{
    /**
     * Gera 3 meses de cobranças (01-03/2026) para os 4 contratos ativos,
     * com repasses calculados conforme modelo e titularidades.
     *
     * Depende de TenantSeeder, ImoveisSeeder e ContratosSeeder.
     */
    public function run(): void
    {
        $this->contrato1AptAurora();
        $this->contrato2CasaVerde();
        $this->contrato3AptFamilia();
        $this->contrato4LojaGaleria();
    }

    /**
     * Contrato 1 — Apt 302, Ed. Aurora (por_recebimento, taxa 10%)
     * Inquilino: Pedro Lima | Titular: Ana Costa 100%
     */
    private function contrato1AptAurora(): void
    {
        $contrato = Contrato::withoutGlobalScopes()
            ->whereHas('imovel', fn ($q) => $q->withoutGlobalScopes()->where('complemento', 'like', '%Ed. Aurora%'))
            ->firstOrFail();
        $tenant = $contrato->tenant_id;
        $titularidades = Titularidade::withoutGlobalScopes()->where('imovel_id', $contrato->imovel_id)->get();

        // Valores fixos: Aluguel 2400 + Condomínio 800 + IPTU 150 + Seguro incêndio 80 = 3430
        $baseData = [
            'tenant_id' => $tenant,
            'contrato_id' => $contrato->id,
            'valor_aluguel' => 2400.00,
            'valor_condominio' => 800.00,
            'valor_iptu' => 150.00,
            'valor_seguro_incendio' => 80.00,
            'valor_taxa_bombeiros' => null,
            'valor_taxa_extra_condominio' => null,
            'tipo_geracao' => 'automatica',
        ];

        // 01/2026 — pago em dia via pix + item extra R$ 45
        $cob1 = Cobranca::create(array_merge($baseData, [
            'referencia' => '01/2026',
            'valor_total' => 3475.00, // 3430 + 45 (item extra)
            'data_vencimento' => '2026-01-05',
            'data_pagamento' => '2026-01-05',
            'valor_pago' => 3475.00,
            'metodo_pagamento' => 'pix',
            'status' => 'pago',
        ]));

        CobrancaItemExtra::create([
            'tenant_id' => $tenant,
            'cobranca_id' => $cob1->id,
            'descricao' => 'Reparo no interfone — rateio',
            'valor' => 45.00,
        ]);

        // 02/2026 — pago com 2 dias de atraso via boleto
        $multaValor = round(3430.00 * 0.02, 2); // 2% = 68.60
        $jurosValor = round(3430.00 * 0.0333 * 2 / 100, 2); // 0.0333% * 2 dias = 2.28
        $cob2 = Cobranca::create(array_merge($baseData, [
            'referencia' => '02/2026',
            'valor_total' => 3430.00,
            'data_vencimento' => '2026-02-05',
            'data_pagamento' => '2026-02-07',
            'valor_multa' => $multaValor,
            'valor_juros' => $jurosValor,
            'valor_pago' => 3430.00 + $multaValor + $jurosValor,
            'metodo_pagamento' => 'boleto',
            'status' => 'pago',
        ]));

        // 03/2026 — pendente
        $cob3 = Cobranca::create(array_merge($baseData, [
            'referencia' => '03/2026',
            'valor_total' => 3430.00,
            'data_vencimento' => '2026-03-05',
            'status' => 'pendente',
        ]));

        // Repasses para cobranças pagas (modelo por_recebimento)
        foreach ([$cob1, $cob2] as $i => $cob) {
            $this->criarRepasses($tenant, $cob, $titularidades, $contrato, [
                'data_prevista' => $i === 0 ? '2026-01-10' : '2026-02-12',
                'data_realizada' => $i === 0 ? '2026-01-10' : '2026-02-12',
                'status' => 'realizado',
            ]);
        }
    }

    /**
     * Contrato 2 — Casa 14, Cond. Verde (garantido, taxa 8%, seguro 4%)
     * Inquilino: Ricardo Santos | Titular: Eduardo Silva 100%
     */
    private function contrato2CasaVerde(): void
    {
        $contrato = Contrato::withoutGlobalScopes()
            ->whereHas('imovel', fn ($q) => $q->withoutGlobalScopes()->where('complemento', 'like', '%Cond. Verde%'))
            ->firstOrFail();
        $tenant = $contrato->tenant_id;
        $titularidades = Titularidade::withoutGlobalScopes()->where('imovel_id', $contrato->imovel_id)->get();

        // Valores fixos: Aluguel 3800 + Condomínio 1200 + IPTU 280 = 5280
        $baseData = [
            'tenant_id' => $tenant,
            'contrato_id' => $contrato->id,
            'valor_aluguel' => 3800.00,
            'valor_condominio' => 1200.00,
            'valor_iptu' => 280.00,
            'valor_seguro_incendio' => null, // proprietário paga
            'valor_taxa_bombeiros' => null,
            'valor_taxa_extra_condominio' => null,
            'tipo_geracao' => 'automatica',
        ];

        $meses = [
            ['ref' => '01/2026', 'venc' => '2026-01-10', 'pgto' => '2026-01-10', 'metodo' => 'transferencia', 'status' => 'pago'],
            ['ref' => '02/2026', 'venc' => '2026-02-10', 'pgto' => '2026-02-10', 'metodo' => 'pix', 'status' => 'pago'],
            ['ref' => '03/2026', 'venc' => '2026-03-10', 'pgto' => null, 'metodo' => null, 'status' => 'pendente'],
        ];

        $cobrancas = [];
        foreach ($meses as $m) {
            $cobrancas[] = Cobranca::create(array_merge($baseData, [
                'referencia' => $m['ref'],
                'valor_total' => 5280.00,
                'data_vencimento' => $m['venc'],
                'data_pagamento' => $m['pgto'],
                'valor_pago' => $m['pgto'] ? 5280.00 : null,
                'metodo_pagamento' => $m['metodo'],
                'status' => $m['status'],
            ]));
        }

        // Repasses para TODOS os 3 meses (modelo garantido)
        $datas = ['2026-01-15', '2026-02-15', '2026-03-15'];
        foreach ($cobrancas as $i => $cob) {
            $this->criarRepasses($tenant, $cob, $titularidades, $contrato, [
                'data_prevista' => $datas[$i],
                'data_realizada' => $datas[$i],
                'status' => 'realizado',
            ]);
        }
    }

    /**
     * Contrato 3 — Apt herdado, Ed. Família (por_recebimento, taxa 10%, split 3 titulares)
     * Inquilino: Rita Mendes | Titulares: Ana 50%, João 25%, Maria 25%
     */
    private function contrato3AptFamilia(): void
    {
        $contrato = Contrato::withoutGlobalScopes()
            ->whereHas('imovel', fn ($q) => $q->withoutGlobalScopes()->where('complemento', 'like', '%Ed. Família%'))
            ->firstOrFail();
        $tenant = $contrato->tenant_id;
        $titularidades = Titularidade::withoutGlobalScopes()->where('imovel_id', $contrato->imovel_id)->get();

        // Valores fixos: Aluguel 1900 + Condomínio 650 + IPTU 120 = 2670
        $baseData = [
            'tenant_id' => $tenant,
            'contrato_id' => $contrato->id,
            'valor_aluguel' => 1900.00,
            'valor_condominio' => 650.00,
            'valor_iptu' => 120.00,
            'valor_seguro_incendio' => null,
            'valor_taxa_bombeiros' => null,
            'valor_taxa_extra_condominio' => null,
            'tipo_geracao' => 'automatica',
        ];

        // 01/2026 — pago
        $cob1 = Cobranca::create(array_merge($baseData, [
            'referencia' => '01/2026',
            'valor_total' => 2670.00,
            'data_vencimento' => '2026-01-28',
            'data_pagamento' => '2026-01-28',
            'valor_pago' => 2670.00,
            'metodo_pagamento' => 'boleto',
            'status' => 'pago',
        ]));

        // 02/2026 — atrasado
        Cobranca::create(array_merge($baseData, [
            'referencia' => '02/2026',
            'valor_total' => 2670.00,
            'data_vencimento' => '2026-02-28',
            'status' => 'atrasado',
        ]));

        // 03/2026 — pendente
        Cobranca::create(array_merge($baseData, [
            'referencia' => '03/2026',
            'valor_total' => 2670.00,
            'data_vencimento' => '2026-03-28',
            'status' => 'pendente',
        ]));

        // Repasse APENAS para 01/2026 (por_recebimento, só quando pago)
        // Split: Ana 50%, João 25%, Maria 25%
        $this->criarRepasses($tenant, $cob1, $titularidades, $contrato, [
            'data_prevista' => '2026-02-02',
            'data_realizada' => '2026-02-02',
            'status' => 'realizado',
        ]);
    }

    /**
     * Contrato 4 — Loja 3, Galeria Central (por_recebimento, taxa 0%)
     * Inquilino: Café Aroma | Titular: Carlos Mendes 100%
     */
    private function contrato4LojaGaleria(): void
    {
        $contrato = Contrato::withoutGlobalScopes()
            ->whereHas('imovel', fn ($q) => $q->withoutGlobalScopes()->where('complemento', 'like', '%Galeria Central%'))
            ->firstOrFail();
        $tenant = $contrato->tenant_id;
        $titularidades = Titularidade::withoutGlobalScopes()->where('imovel_id', $contrato->imovel_id)->get();

        // Valores fixos: Aluguel 4100 + Condomínio 950 + IPTU 320 + Taxa extra 200 = 5570
        $baseData = [
            'tenant_id' => $tenant,
            'contrato_id' => $contrato->id,
            'valor_aluguel' => 4100.00,
            'valor_condominio' => 950.00,
            'valor_iptu' => 320.00,
            'valor_seguro_incendio' => null,
            'valor_taxa_bombeiros' => null,
            'valor_taxa_extra_condominio' => 200.00,
            'tipo_geracao' => 'automatica',
        ];

        $meses = [
            ['ref' => '01/2026', 'venc' => '2026-01-01', 'pgto' => '2026-01-02', 'metodo' => 'pix', 'status' => 'pago'],
            ['ref' => '02/2026', 'venc' => '2026-02-01', 'pgto' => '2026-02-01', 'metodo' => 'pix', 'status' => 'pago'],
            ['ref' => '03/2026', 'venc' => '2026-03-01', 'pgto' => null, 'metodo' => null, 'status' => 'pendente'],
        ];

        $cobrancas = [];
        foreach ($meses as $m) {
            $cobrancas[] = Cobranca::create(array_merge($baseData, [
                'referencia' => $m['ref'],
                'valor_total' => 5570.00,
                'data_vencimento' => $m['venc'],
                'data_pagamento' => $m['pgto'],
                'valor_pago' => $m['pgto'] ? 5570.00 : null,
                'metodo_pagamento' => $m['metodo'],
                'status' => $m['status'],
            ]));
        }

        // Repasses para as 2 cobranças pagas (por_recebimento)
        $datas = ['2026-01-05', '2026-02-05'];
        foreach ([$cobrancas[0], $cobrancas[1]] as $i => $cob) {
            $this->criarRepasses($tenant, $cob, $titularidades, $contrato, [
                'data_prevista' => $datas[$i],
                'data_realizada' => $datas[$i],
                'status' => 'realizado',
            ]);
        }
    }

    /**
     * Cria repasses para todos os titulares de um imóvel com base na cobrança.
     * Calcula proporcionalmente ao percentual de cada titular.
     */
    private function criarRepasses(
        int $tenantId,
        Cobranca $cobranca,
        $titularidades,
        Contrato $contrato,
        array $overrides,
    ): void {
        $aluguel = (float) $contrato->valor_aluguel;
        $taxaAdminPct = (float) $contrato->taxa_administracao_pct;
        $seguroInadPct = $contrato->taxa_seguro_inadimplencia_pct ? (float) $contrato->taxa_seguro_inadimplencia_pct : null;

        foreach ($titularidades as $tit) {
            $percentual = (float) $tit->percentual / 100;

            // Valor bruto proporcional ao percentual de propriedade
            $bruto = round($aluguel * $percentual, 2);

            // Taxa de administração sobre o bruto
            $taxaAdminValor = round($bruto * $taxaAdminPct / 100, 2);

            // Seguro inadimplência (só modelo garantido)
            $seguroInadValor = $seguroInadPct ? round($bruto * $seguroInadPct / 100, 2) : null;

            // Líquido = bruto - taxa admin - seguro
            $liquido = $bruto - $taxaAdminValor - ($seguroInadValor ?? 0);

            Repasse::create(array_merge([
                'tenant_id' => $tenantId,
                'cobranca_id' => $cobranca->id,
                'titularidade_id' => $tit->id,
                'valor_aluguel_bruto' => $bruto,
                'taxa_administracao_valor' => $taxaAdminValor,
                'taxa_seguro_inadimplencia_valor' => $seguroInadValor,
                'valor_liquido' => $liquido,
            ], $overrides));
        }
    }
}
