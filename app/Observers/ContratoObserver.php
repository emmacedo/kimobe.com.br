<?php

namespace App\Observers;

use App\Models\Contrato;
use App\Models\ContratoAlteracao;

/**
 * Auditoria estruturada (Camada 3) — registra alterações em campos
 * críticos do contrato em `contrato_alteracoes`.
 *
 * Campos cobertos pela Camada 3 dedicada:
 *   - taxa_administracao_pct
 *   - modelo_repasse
 *   - status
 *   - data_fim
 *
 * `valor_aluguel` NÃO é auditado aqui — fica em `contrato_reajustes` via
 * `ContratoReajusteService`. Os demais campos são cobertos pelo activitylog
 * (Camada 2) e ficam fora do observer.
 */
class ContratoObserver
{
    /**
     * @var list<string>
     */
    private const CAMPOS_AUDITADOS = [
        'taxa_administracao_pct',
        'modelo_repasse',
        'status',
        'data_fim',
    ];

    public function updated(Contrato $contrato): void
    {
        foreach (self::CAMPOS_AUDITADOS as $campo) {
            if (! $contrato->wasChanged($campo)) {
                continue;
            }

            ContratoAlteracao::create([
                'contrato_id' => $contrato->id,
                'alterado_por_user_id' => auth()->id(),
                'alterado_em' => now(),
                'campo' => $campo,
                'valor_anterior' => [$campo => $this->serializar($contrato->getOriginal($campo))],
                'valor_novo' => [$campo => $this->serializar($contrato->getAttribute($campo))],
                'data_efetiva' => now()->toDateString(),
            ]);
        }
    }

    private function serializar(mixed $valor): mixed
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        return $valor;
    }
}
