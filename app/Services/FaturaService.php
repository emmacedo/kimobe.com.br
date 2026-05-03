<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\Fatura;
use App\Models\ItemCobranca;
use App\Models\Repasse;
use App\Models\Scopes\TenantScope;
use App\Models\Titularidade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Geração e manutenção de faturas mensais.
 *
 * Fluxo de fechamento (item 6 do plano):
 *  1. Cria a fatura (agrupador) para o contrato no mês de referência.
 *  2. Concilia todos os `itens_cobranca` pendentes do contrato cujo
 *     `mes_referencia` bate com o mês fechado: marca `status=conciliado`
 *     e preenche `fatura_id`.
 *  3. Recalcula `valor_total` da fatura como soma dos itens conciliados.
 *  4. No modelo de repasse `garantido`, gera repasses imediatamente
 *     usando `contrato.valor_aluguel` como base (refinar no futuro para
 *     considerar todos os itens com `recebedor=proprietario`).
 *
 * Pré-requisito: itens recorrentes/parcelados/avulsos devem estar
 * pré-gerados via `ItemCobrancaService` (item 5).
 */
class FaturaService
{
    /**
     * Gera faturas mensais (vazias) para todos os contratos ativos do tenant
     * que ainda não possuem fatura na referência informada.
     *
     * @return array{quantidade: int, valor_total: float}
     */
    public function gerarFaturasMensais(string $referencia): array
    {
        $contratos = Contrato::where('status', 'ativo')
            ->whereDoesntHave('faturas', fn ($q) => $q->where('referencia', $referencia))
            ->with(['imovel.titularidades'])
            ->get();

        $quantidade = 0;
        $valorTotal = 0;

        DB::transaction(function () use ($contratos, $referencia, &$quantidade, &$valorTotal) {
            foreach ($contratos as $contrato) {
                $fatura = $this->gerarFaturaParaContrato($contrato, $referencia);
                $quantidade++;
                $valorTotal += (float) $fatura->valor_total;
            }
        });

        return ['quantidade' => $quantidade, 'valor_total' => $valorTotal];
    }

    /**
     * Gera uma fatura individual para um contrato.
     */
    public function gerarFaturaIndividual(Contrato $contrato, string $referencia, string $tipoGeracao = 'manual'): Fatura
    {
        return DB::transaction(function () use ($contrato, $referencia, $tipoGeracao) {
            return $this->gerarFaturaParaContrato($contrato, $referencia, $tipoGeracao);
        });
    }

    /**
     * Lógica interna de geração de fatura — cria a fatura, concilia itens
     * pendentes do mês e calcula o total.
     */
    private function gerarFaturaParaContrato(Contrato $contrato, string $referencia, string $tipoGeracao = 'automatica'): Fatura
    {
        $partes = explode('/', $referencia);
        $mes = (int) $partes[0];
        $ano = (int) $partes[1];
        $dia = min($contrato->dia_vencimento, 28);
        $dataVencimento = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);

        // 1. Cria fatura (agrupador, valor_total será preenchido após conciliação).
        // Snapshot dos percentuais e parâmetros vigentes no contrato AGORA.
        $fatura = Fatura::create([
            'tenant_id' => $contrato->tenant_id,
            'contrato_id' => $contrato->id,
            'referencia' => $referencia,
            'valor_total' => 0,
            'data_vencimento' => $dataVencimento,
            'tipo_geracao' => $tipoGeracao,
            'status' => 'pendente',
            'multa_atraso_pct_aplicada' => $contrato->multa_atraso_pct,
            'juros_atraso_pct_dia_aplicada' => $contrato->juros_atraso_pct_dia,
            'desconto_pontualidade_pct_aplicada' => $contrato->desconto_pontualidade_pct,
            'dias_carencia_aplicada' => $contrato->dias_carencia,
            'gerada_por_user_id' => auth()->id(),
        ]);

        // 2. Concilia itens pendentes do contrato cujo mes_referencia bate.
        ItemCobranca::query()
            ->where('contrato_id', $contrato->id)
            ->where('mes_referencia', $referencia)
            ->where('status', 'pendente')
            ->update([
                'fatura_id' => $fatura->id,
                'status' => 'conciliado',
            ]);

        // 3. Recalcula valor_total a partir dos itens conciliados.
        $this->recalcularTotal($fatura);
        $fatura->refresh();

        // 4. Repasses no modelo garantido — usa contrato.valor_aluguel como base.
        // Refinar no futuro para considerar itens com recebedor=proprietario.
        if ($contrato->modelo_repasse === 'garantido') {
            $this->gerarRepasses($fatura, $contrato, $dataVencimento);
        }

        try {
            app(NotificacaoAdminService::class)->notificarFaturaGerada($fatura);
        } catch (\Throwable $e) {
            Log::warning("Falha ao notificar fatura gerada: {$e->getMessage()}");
        }

        return $fatura;
    }

    /**
     * Gera repasses para todos os titulares do imóvel de um contrato.
     * Continua usando `valor_aluguel` do contrato como base (snapshot do mês).
     */
    public function gerarRepasses(Fatura $fatura, Contrato $contrato, string $dataBase): void
    {
        $titularidades = Titularidade::withoutGlobalScopes([TenantScope::class])
            ->where('imovel_id', $contrato->imovel_id)
            ->get();

        $aluguel = (float) $contrato->valor_aluguel;
        $taxaAdminPct = (float) $contrato->taxa_administracao_pct;
        $seguroPct = $contrato->taxa_seguro_inadimplencia_pct
            ? (float) $contrato->taxa_seguro_inadimplencia_pct
            : null;

        $dataPrevista = date('Y-m-d', strtotime($dataBase.' +7 days'));

        foreach ($titularidades as $tit) {
            $percentual = (float) $tit->percentual / 100;
            $bruto = round($aluguel * $percentual, 2);
            $taxaAdmin = round($bruto * $taxaAdminPct / 100, 2);
            $seguro = $seguroPct ? round($bruto * $seguroPct / 100, 2) : null;
            $liquido = $bruto - $taxaAdmin - ($seguro ?? 0);

            $repasse = Repasse::create([
                'tenant_id' => $contrato->tenant_id,
                'fatura_id' => $fatura->id,
                'titularidade_id' => $tit->id,
                'valor_aluguel_bruto' => $bruto,
                'taxa_administracao_valor' => $taxaAdmin,
                'taxa_seguro_inadimplencia_valor' => $seguro,
                'valor_liquido' => $liquido,
                'data_prevista' => $dataPrevista,
                'status' => 'pendente',
                'taxa_administracao_pct_aplicada' => $taxaAdminPct,
                'taxa_seguro_inadimplencia_pct_aplicada' => $seguroPct,
                'percentual_titularidade_aplicado' => $tit->percentual,
                'gerado_por_user_id' => auth()->id(),
            ]);

            try {
                app(NotificacaoAdminService::class)->notificarRepassePendente($repasse);
            } catch (\Throwable $e) {
                Log::warning("Falha ao notificar repasse pendente: {$e->getMessage()}");
            }
        }
    }

    /**
     * Recalcula o valor_total de uma fatura — soma de todos os itens conciliados.
     */
    public function recalcularTotal(Fatura $fatura): void
    {
        $total = (float) $fatura->itens()->sum('valor_unitario');
        $fatura->update(['valor_total' => $total]);
    }
}
