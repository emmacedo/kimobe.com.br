<?php

namespace App\Services;

use App\Models\Contrato;
use App\Models\ItemCobranca;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Serviço responsável pela criação, pré-geração de ocorrências e edição
 * de itens de cobrança seguindo o padrão `parent_item_id` (inspirado no MoneyMagnet).
 *
 * Ver Seção 2.5 e 2.6 do documento `escopo-evolucao-financeiro.md`.
 */
class ItemCobrancaService
{
    /**
     * Cria a primeira ocorrência da série e pré-gera as demais quando aplicável
     * (recorrente até `data_fim` do contrato; parcelado por `num_parcelas_total`).
     *
     * Retorna a ocorrência-pai (a primeira da série, com `parent_item_id=NULL`).
     *
     * @param  array<string, mixed>  $dados
     */
    public function criar(Contrato $contrato, array $dados): ItemCobranca
    {
        $dados = $this->normalizarDados($dados);
        $this->validarConsistencia($dados);

        return DB::transaction(function () use ($contrato, $dados) {
            $pai = $this->criarOcorrencia($contrato, $dados, parentId: null);

            match ($dados['tipo']) {
                'recorrente' => $this->preGerarRecorrente($contrato, $pai, $dados),
                'parcelado' => $this->preGerarParcelado($contrato, $pai, $dados),
                'avulso' => null,
            };

            return $pai->fresh();
        });
    }

    /**
     * Atualiza apenas a ocorrência específica.
     *
     * @param  array<string, mixed>  $dados
     */
    public function atualizarOcorrencia(ItemCobranca $item, array $dados): ItemCobranca
    {
        $this->garantirEditavel($item);
        $dados = $this->normalizarDadosAtualizacao($item, $dados);

        $item->update($dados);

        return $item->fresh();
    }

    /**
     * Atualiza esta ocorrência e todas as futuras pendentes da mesma série.
     *
     * @param  array<string, mixed>  $dados
     * @return int número de ocorrências afetadas
     */
    public function atualizarEstaEFuturas(ItemCobranca $item, array $dados): int
    {
        $this->garantirEditavel($item);
        $dados = $this->normalizarDadosAtualizacao($item, $dados);
        $parentId = $item->parent_item_id ?? $item->id;

        return ItemCobranca::query()
            ->where(function ($q) use ($parentId) {
                $q->where('id', $parentId)->orWhere('parent_item_id', $parentId);
            })
            ->where('status', 'pendente')
            ->where('mes_referencia', '>=', $item->mes_referencia)
            ->update($dados);
    }

    /**
     * Atualiza todas as ocorrências pendentes da série (passadas e futuras).
     *
     * @param  array<string, mixed>  $dados
     * @return int número de ocorrências afetadas
     */
    public function atualizarTodas(ItemCobranca $item, array $dados): int
    {
        $this->garantirEditavel($item);
        $dados = $this->normalizarDadosAtualizacao($item, $dados);
        $parentId = $item->parent_item_id ?? $item->id;

        return ItemCobranca::query()
            ->where(function ($q) use ($parentId) {
                $q->where('id', $parentId)->orWhere('parent_item_id', $parentId);
            })
            ->where('status', 'pendente')
            ->update($dados);
    }

    /**
     * Cancela apenas esta ocorrência.
     */
    public function cancelarOcorrencia(ItemCobranca $item): void
    {
        $this->garantirEditavel($item);
        $item->update(['status' => 'cancelado']);
    }

    /**
     * Cancela todas as ocorrências pendentes da série.
     */
    public function cancelarSerie(ItemCobranca $item): int
    {
        $parentId = $item->parent_item_id ?? $item->id;

        return ItemCobranca::query()
            ->where(function ($q) use ($parentId) {
                $q->where('id', $parentId)->orWhere('parent_item_id', $parentId);
            })
            ->where('status', 'pendente')
            ->update(['status' => 'cancelado']);
    }

    // =========================================================================
    // Internos
    // =========================================================================

    /**
     * @param  array<string, mixed>  $dados
     */
    private function criarOcorrencia(Contrato $contrato, array $dados, ?int $parentId, ?int $numParcela = null): ItemCobranca
    {
        return ItemCobranca::create([
            'tenant_id' => $contrato->tenant_id,
            'contrato_id' => $contrato->id,
            'parent_item_id' => $parentId,
            'descricao' => $dados['descricao'],
            'pagante' => $dados['pagante'],
            'recebedor' => $dados['recebedor'],
            'entidade_externa_id' => $dados['entidade_externa_id'] ?? null,
            'tipo' => $dados['tipo'],
            'periodicidade' => $dados['periodicidade'] ?? null,
            'num_parcela' => $numParcela,
            'num_parcelas_total' => $dados['num_parcelas_total'] ?? null,
            'valor_unitario' => $dados['valor_unitario'],
            'mes_referencia' => $dados['mes_referencia'],
            'visivel_inquilino' => $dados['visivel_inquilino'],
            'status' => 'pendente',
            'observacoes' => $dados['observacoes'] ?? null,
            'criado_por_user_id' => $dados['criado_por_user_id'] ?? auth()->id(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function preGerarRecorrente(Contrato $contrato, ItemCobranca $pai, array $dados): void
    {
        $intervalo = $this->intervaloMeses($dados['periodicidade']);
        $mesRef = $this->avancarMes($dados['mes_referencia'], $intervalo);
        $limite = $this->mesReferenciaDe($contrato->data_fim);

        // Itens recorrentes só fazem sentido se o pai (mes_referencia) <= data_fim do contrato.
        // A partir daí, gera ocorrências até atingir o limite.
        while ($this->mesEhMenorOuIgual($mesRef, $limite)) {
            $this->criarOcorrencia(
                $contrato,
                array_merge($dados, ['mes_referencia' => $mesRef]),
                parentId: $pai->id,
            );
            $mesRef = $this->avancarMes($mesRef, $intervalo);
        }
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function preGerarParcelado(Contrato $contrato, ItemCobranca $pai, array $dados): void
    {
        $total = (int) $dados['num_parcelas_total'];
        $pai->update(['num_parcela' => 1]);

        // Parcelado assume cadência mensal entre parcelas.
        $mesRef = $this->avancarMes($dados['mes_referencia'], 1);
        for ($parcela = 2; $parcela <= $total; $parcela++) {
            $this->criarOcorrencia(
                $contrato,
                array_merge($dados, ['mes_referencia' => $mesRef]),
                parentId: $pai->id,
                numParcela: $parcela,
            );
            $mesRef = $this->avancarMes($mesRef, 1);
        }
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function normalizarDados(array $dados): array
    {
        $dados['visivel_inquilino'] = $dados['visivel_inquilino'] ?? true;

        // Regra de domínio: se o inquilino paga, o item DEVE ser visível para ele.
        if (($dados['pagante'] ?? null) === 'inquilino') {
            $dados['visivel_inquilino'] = true;
        }

        return $dados;
    }

    /**
     * Atualizações em massa (esta-e-futuras / todas) só permitem trocar campos
     * "evolutivos". Mudar `tipo`, `parent_item_id` ou `mes_referencia` exige
     * cancelamento + recriação (Regra 7 da Seção 2.3).
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function normalizarDadosAtualizacao(ItemCobranca $item, array $dados): array
    {
        $proibidos = ['tipo', 'parent_item_id', 'mes_referencia', 'num_parcela', 'num_parcelas_total'];
        foreach ($proibidos as $campo) {
            if (array_key_exists($campo, $dados)) {
                throw new DomainException(
                    "O campo `{$campo}` não pode ser alterado. Para mudanças estruturais, cancele a série e recrie."
                );
            }
        }

        if (($dados['pagante'] ?? $item->pagante) === 'inquilino') {
            $dados['visivel_inquilino'] = true;
        }

        $dados['atualizado_por_user_id'] = $dados['atualizado_por_user_id'] ?? auth()->id();

        return $dados;
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function validarConsistencia(array $dados): void
    {
        $tipo = $dados['tipo'] ?? null;

        if ($tipo === 'recorrente') {
            if (empty($dados['periodicidade'])) {
                throw new DomainException('Itens recorrentes exigem `periodicidade`.');
            }
            if (! empty($dados['num_parcela']) || ! empty($dados['num_parcelas_total'])) {
                throw new DomainException('Itens recorrentes não podem ter parcelas definidas.');
            }
        } elseif ($tipo === 'parcelado') {
            if (empty($dados['num_parcelas_total']) || (int) $dados['num_parcelas_total'] < 1) {
                throw new DomainException('Itens parcelados exigem `num_parcelas_total >= 1`.');
            }
            if (! empty($dados['periodicidade'])) {
                throw new DomainException('Itens parcelados não devem ter `periodicidade`.');
            }
        } elseif ($tipo === 'avulso') {
            if (! empty($dados['periodicidade']) || ! empty($dados['num_parcelas_total'])) {
                throw new DomainException('Itens avulsos não têm periodicidade nem parcelas.');
            }
        } else {
            throw new DomainException("Tipo inválido: {$tipo}");
        }

        if (empty($dados['mes_referencia']) || ! preg_match('/^\d{2}\/\d{4}$/', (string) $dados['mes_referencia'])) {
            throw new DomainException('`mes_referencia` deve estar no formato MM/YYYY.');
        }
    }

    private function garantirEditavel(ItemCobranca $item): void
    {
        if ($item->status === 'conciliado') {
            throw new DomainException('Itens conciliados são imutáveis. Crie um item de ajuste no mês corrente.');
        }
    }

    private function intervaloMeses(string $periodicidade): int
    {
        return match ($periodicidade) {
            'mensal' => 1,
            'bimestral' => 2,
            'trimestral' => 3,
            'semestral' => 6,
            'anual' => 12,
            default => throw new DomainException("Periodicidade inválida: {$periodicidade}"),
        };
    }

    /**
     * Avança N meses sobre uma string de mes_referencia (formato MM/YYYY).
     */
    private function avancarMes(string $mesRef, int $intervalo): string
    {
        [$mes, $ano] = explode('/', $mesRef);
        $totalMeses = ((int) $ano * 12) + (int) $mes - 1 + $intervalo;
        $novoAno = intdiv($totalMeses, 12);
        $novoMes = ($totalMeses % 12) + 1;

        return sprintf('%02d/%04d', $novoMes, $novoAno);
    }

    private function mesReferenciaDe(\DateTimeInterface $data): string
    {
        return Carbon::parse($data)->format('m/Y');
    }

    /**
     * Compara dois mes_referencia no formato MM/YYYY.
     */
    private function mesEhMenorOuIgual(string $a, string $b): bool
    {
        [$ma, $aa] = explode('/', $a);
        [$mb, $ab] = explode('/', $b);
        $valorA = ((int) $aa * 100) + (int) $ma;
        $valorB = ((int) $ab * 100) + (int) $mb;

        return $valorA <= $valorB;
    }
}
