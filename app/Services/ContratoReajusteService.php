<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\ContratoReajuste;
use App\Models\ItemCobranca;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Aplica reajustes ao `valor_aluguel` de um contrato preservando histórico
 * estruturado (Camada 3 — `contrato_reajustes`) e propagando o novo valor
 * para os itens de aluguel pendentes a partir da `data_aplicacao`.
 *
 * Ver Seção 4.4 do `escopo-evolucao-financeiro.md`.
 */
class ContratoReajusteService
{
    /**
     * Aplica um reajuste, registra o histórico e propaga o valor para o aluguel
     * do contrato (campo do contrato + itens de aluguel pendentes a partir da data).
     *
     * @param  array{
     *     valor_novo: numeric-string|float|int,
     *     data_aplicacao: \DateTimeInterface|string,
     *     indice_usado: 'igpm'|'ipca'|'fixo'|'manual',
     *     origem: 'reajuste_anual'|'aditivo'|'renegociacao'|'correcao',
     *     observacao?: string|null,
     * }  $dados
     */
    public function aplicar(Contrato $contrato, array $dados): ContratoReajuste
    {
        $valorAnterior = (float) $contrato->valor_aluguel;
        $valorNovo = (float) $dados['valor_novo'];

        if ($valorNovo <= 0) {
            throw new DomainException('Valor novo do reajuste deve ser maior que zero.');
        }

        $dataAplicacao = CarbonImmutable::parse($dados['data_aplicacao'])->startOfDay();

        if ($contrato->data_inicio && $dataAplicacao->lt($contrato->data_inicio)) {
            throw new DomainException('Data de aplicação anterior ao início do contrato.');
        }

        if ($contrato->data_fim && $dataAplicacao->gt($contrato->data_fim)) {
            throw new DomainException('Data de aplicação posterior ao fim do contrato.');
        }

        $percentual = $valorAnterior > 0
            ? round((($valorNovo - $valorAnterior) / $valorAnterior) * 100, 4)
            : 0;

        return DB::transaction(function () use ($contrato, $dados, $valorAnterior, $valorNovo, $dataAplicacao, $percentual) {
            $contrato->update(['valor_aluguel' => $valorNovo]);

            $this->propagarParaItensAluguel($contrato, $valorNovo, $dataAplicacao);

            return ContratoReajuste::create([
                'contrato_id' => $contrato->id,
                'alterado_por_user_id' => auth()->id(),
                'alterado_em' => now(),
                'data_aplicacao' => $dataAplicacao,
                'valor_anterior' => $valorAnterior,
                'valor_novo' => $valorNovo,
                'percentual' => $percentual,
                'indice_usado' => $dados['indice_usado'],
                'origem' => $dados['origem'],
                'observacao' => $dados['observacao'] ?? null,
            ]);
        });
    }

    /**
     * Atualiza valor_unitario dos itens de aluguel pendentes do contrato cujo
     * mes_referencia está no mês da data de aplicação ou em meses posteriores.
     *
     * Itens conciliados ficam intocados (Princípio 4 — imutabilidade do conciliado).
     *
     * Filtragem feita em PHP para portabilidade entre MySQL/SQLite — `mes_referencia`
     * é uma string "MM/YYYY" e não há função SQL portável para parseá-la.
     */
    protected function propagarParaItensAluguel(Contrato $contrato, float $valorNovo, CarbonImmutable $dataAplicacao): void
    {
        $corteAnoMes = (int) $dataAplicacao->format('Ym');

        $idsAfetados = ItemCobranca::query()
            ->where('contrato_id', $contrato->id)
            ->where('natureza', 'aluguel')
            ->where('status', 'pendente')
            ->pluck('mes_referencia', 'id')
            ->filter(function (string $mesRef) use ($corteAnoMes) {
                [$mes, $ano] = explode('/', $mesRef);

                return ((int) $ano * 100 + (int) $mes) >= $corteAnoMes;
            })
            ->keys();

        if ($idsAfetados->isEmpty()) {
            return;
        }

        ItemCobranca::whereIn('id', $idsAfetados)->update(['valor_unitario' => $valorNovo]);
    }
}
