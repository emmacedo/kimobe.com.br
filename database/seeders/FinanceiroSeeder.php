<?php

namespace Database\Seeders;

use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\Repasse;
use App\Models\Titularidade;
use Illuminate\Database\Seeder;

/**
 * Gera 3 meses de faturas (01-03/2026) para os 4 contratos ativos,
 * com repasses calculados conforme modelo e titularidades.
 *
 * NOTA: As faturas são criadas apenas com `valor_total` (sem itens detalhados).
 * Os itens em si serão pré-gerados pelo `ItemCobrancaService` quando a Frente 1
 * for completada (item 5 do plano de implementação).
 *
 * Depende de TenantSeeder, ImoveisSeeder e ContratosSeeder.
 */
class FinanceiroSeeder extends Seeder
{
    public function run(): void
    {
        $this->contrato1AptAurora();
        $this->contrato2CasaVerde();
        $this->contrato3AptFamilia();
        $this->contrato4LojaGaleria();
    }

    /**
     * Contrato 1 — Apt 302, Ed. Aurora (por_recebimento, taxa 10%)
     * Total mensal estimado: 3430 (Aluguel 2400 + Condomínio 800 + IPTU 150 + Seguro 80)
     */
    private function contrato1AptAurora(): void
    {
        $contrato = Contrato::withoutGlobalScopes()
            ->whereHas('imovel', fn ($q) => $q->withoutGlobalScopes()->where('complemento', 'like', '%Ed. Aurora%'))
            ->firstOrFail();
        $tenant = $contrato->tenant_id;
        $titularidades = Titularidade::withoutGlobalScopes()->where('imovel_id', $contrato->imovel_id)->get();

        $baseData = [
            'tenant_id' => $tenant,
            'contrato_id' => $contrato->id,
            'tipo_geracao' => 'automatica',
        ];

        // 01/2026 — pago
        $fat1 = Fatura::create(array_merge($baseData, [
            'referencia' => '01/2026',
            'valor_total' => 3430.00,
            'data_vencimento' => '2026-01-05',
            'data_pagamento' => '2026-01-05',
            'valor_pago' => 3430.00,
            'metodo_pagamento' => 'pix',
            'status' => 'pago',
        ]));

        // 02/2026 — pago com 2 dias de atraso
        $multaValor = round(3430.00 * 0.02, 2);
        $jurosValor = round(3430.00 * 0.0333 * 2 / 100, 2);
        $fat2 = Fatura::create(array_merge($baseData, [
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
        Fatura::create(array_merge($baseData, [
            'referencia' => '03/2026',
            'valor_total' => 3430.00,
            'data_vencimento' => '2026-03-05',
            'status' => 'pendente',
        ]));

        foreach ([$fat1, $fat2] as $i => $fat) {
            $this->criarRepasses($tenant, $fat, $titularidades, $contrato, [
                'data_prevista' => $i === 0 ? '2026-01-10' : '2026-02-12',
                'data_realizada' => $i === 0 ? '2026-01-10' : '2026-02-12',
                'status' => 'realizado',
            ]);
        }
    }

    /**
     * Contrato 2 — Casa 14, Cond. Verde (garantido, taxa 8%, seguro 4%)
     */
    private function contrato2CasaVerde(): void
    {
        $contrato = Contrato::withoutGlobalScopes()
            ->whereHas('imovel', fn ($q) => $q->withoutGlobalScopes()->where('complemento', 'like', '%Cond. Verde%'))
            ->firstOrFail();
        $tenant = $contrato->tenant_id;
        $titularidades = Titularidade::withoutGlobalScopes()->where('imovel_id', $contrato->imovel_id)->get();

        $baseData = [
            'tenant_id' => $tenant,
            'contrato_id' => $contrato->id,
            'tipo_geracao' => 'automatica',
        ];

        $meses = [
            ['ref' => '01/2026', 'venc' => '2026-01-10', 'pgto' => '2026-01-10', 'metodo' => 'transferencia', 'status' => 'pago'],
            ['ref' => '02/2026', 'venc' => '2026-02-10', 'pgto' => '2026-02-10', 'metodo' => 'pix', 'status' => 'pago'],
            ['ref' => '03/2026', 'venc' => '2026-03-10', 'pgto' => null, 'metodo' => null, 'status' => 'pendente'],
        ];

        $faturas = [];
        foreach ($meses as $m) {
            $faturas[] = Fatura::create(array_merge($baseData, [
                'referencia' => $m['ref'],
                'valor_total' => 5280.00,
                'data_vencimento' => $m['venc'],
                'data_pagamento' => $m['pgto'],
                'valor_pago' => $m['pgto'] ? 5280.00 : null,
                'metodo_pagamento' => $m['metodo'],
                'status' => $m['status'],
            ]));
        }

        $datas = ['2026-01-15', '2026-02-15', '2026-03-15'];
        foreach ($faturas as $i => $fat) {
            $this->criarRepasses($tenant, $fat, $titularidades, $contrato, [
                'data_prevista' => $datas[$i],
                'data_realizada' => $datas[$i],
                'status' => 'realizado',
            ]);
        }
    }

    /**
     * Contrato 3 — Apt herdado, Ed. Família (por_recebimento, taxa 10%, split 3 titulares)
     */
    private function contrato3AptFamilia(): void
    {
        $contrato = Contrato::withoutGlobalScopes()
            ->whereHas('imovel', fn ($q) => $q->withoutGlobalScopes()->where('complemento', 'like', '%Ed. Família%'))
            ->firstOrFail();
        $tenant = $contrato->tenant_id;
        $titularidades = Titularidade::withoutGlobalScopes()->where('imovel_id', $contrato->imovel_id)->get();

        $baseData = [
            'tenant_id' => $tenant,
            'contrato_id' => $contrato->id,
            'tipo_geracao' => 'automatica',
        ];

        $fat1 = Fatura::create(array_merge($baseData, [
            'referencia' => '01/2026',
            'valor_total' => 2670.00,
            'data_vencimento' => '2026-01-28',
            'data_pagamento' => '2026-01-28',
            'valor_pago' => 2670.00,
            'metodo_pagamento' => 'boleto',
            'status' => 'pago',
        ]));

        Fatura::create(array_merge($baseData, [
            'referencia' => '02/2026',
            'valor_total' => 2670.00,
            'data_vencimento' => '2026-02-28',
            'status' => 'atrasado',
        ]));

        Fatura::create(array_merge($baseData, [
            'referencia' => '03/2026',
            'valor_total' => 2670.00,
            'data_vencimento' => '2026-03-28',
            'status' => 'pendente',
        ]));

        $this->criarRepasses($tenant, $fat1, $titularidades, $contrato, [
            'data_prevista' => '2026-02-02',
            'data_realizada' => '2026-02-02',
            'status' => 'realizado',
        ]);
    }

    /**
     * Contrato 4 — Loja 3, Galeria Central (por_recebimento, taxa 0%)
     */
    private function contrato4LojaGaleria(): void
    {
        $contrato = Contrato::withoutGlobalScopes()
            ->whereHas('imovel', fn ($q) => $q->withoutGlobalScopes()->where('complemento', 'like', '%Galeria Central%'))
            ->firstOrFail();
        $tenant = $contrato->tenant_id;
        $titularidades = Titularidade::withoutGlobalScopes()->where('imovel_id', $contrato->imovel_id)->get();

        $baseData = [
            'tenant_id' => $tenant,
            'contrato_id' => $contrato->id,
            'tipo_geracao' => 'automatica',
        ];

        $meses = [
            ['ref' => '01/2026', 'venc' => '2026-01-01', 'pgto' => '2026-01-02', 'metodo' => 'pix', 'status' => 'pago'],
            ['ref' => '02/2026', 'venc' => '2026-02-01', 'pgto' => '2026-02-01', 'metodo' => 'pix', 'status' => 'pago'],
            ['ref' => '03/2026', 'venc' => '2026-03-01', 'pgto' => null, 'metodo' => null, 'status' => 'pendente'],
        ];

        $faturas = [];
        foreach ($meses as $m) {
            $faturas[] = Fatura::create(array_merge($baseData, [
                'referencia' => $m['ref'],
                'valor_total' => 5570.00,
                'data_vencimento' => $m['venc'],
                'data_pagamento' => $m['pgto'],
                'valor_pago' => $m['pgto'] ? 5570.00 : null,
                'metodo_pagamento' => $m['metodo'],
                'status' => $m['status'],
            ]));
        }

        $datas = ['2026-01-05', '2026-02-05'];
        foreach ([$faturas[0], $faturas[1]] as $i => $fat) {
            $this->criarRepasses($tenant, $fat, $titularidades, $contrato, [
                'data_prevista' => $datas[$i],
                'data_realizada' => $datas[$i],
                'status' => 'realizado',
            ]);
        }
    }

    /**
     * Cria repasses para todos os titulares de um imóvel com base na fatura.
     */
    private function criarRepasses(
        int $tenantId,
        Fatura $fatura,
        $titularidades,
        Contrato $contrato,
        array $overrides,
    ): void {
        $aluguel = (float) $contrato->valor_aluguel;
        $taxaAdminPct = (float) $contrato->taxa_administracao_pct;
        $seguroInadPct = $contrato->taxa_seguro_inadimplencia_pct ? (float) $contrato->taxa_seguro_inadimplencia_pct : null;

        foreach ($titularidades as $tit) {
            $percentual = (float) $tit->percentual / 100;
            $bruto = round($aluguel * $percentual, 2);
            $taxaAdminValor = round($bruto * $taxaAdminPct / 100, 2);
            $seguroInadValor = $seguroInadPct ? round($bruto * $seguroInadPct / 100, 2) : null;
            $liquido = $bruto - $taxaAdminValor - ($seguroInadValor ?? 0);

            Repasse::create(array_merge([
                'tenant_id' => $tenantId,
                'fatura_id' => $fatura->id,
                'titularidade_id' => $tit->id,
                'valor_aluguel_bruto' => $bruto,
                'taxa_administracao_valor' => $taxaAdminValor,
                'taxa_seguro_inadimplencia_valor' => $seguroInadValor,
                'valor_liquido' => $liquido,
            ], $overrides));
        }
    }
}
