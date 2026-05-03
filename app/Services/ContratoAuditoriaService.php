<?php

namespace App\Services;

use App\Models\Contrato;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * Monta a timeline unificada de auditoria de um contrato a partir das três
 * camadas: reajustes (Camada 3), alterações críticas (Camada 3) e activitylog
 * (Camada 2). Inclui também atividades de dependentes (faturas, itens, garantia,
 * fiadores) com `subject` apontando para o contrato.
 *
 * Ver Seção 4.5 do `escopo-evolucao-financeiro.md`.
 */
class ContratoAuditoriaService
{
    /**
     * Retorna eventos ordenados do mais recente para o mais antigo.
     *
     * Cada evento tem o formato:
     *   - id: string única (ex: "reajuste-12", "alteracao-7", "atividade-345")
     *   - tipo: 'reajuste' | 'alteracao' | 'atividade'
     *   - data: ISO string
     *   - titulo: string para exibição
     *   - subtitulo?: string com detalhes resumidos
     *   - usuario?: string com nome de quem causou
     *   - subject_type?: classe Eloquent (em atividades)
     *   - subject_id?: id do subject
     *   - properties?: array de mudanças (em atividades)
     *   - valor_anterior?: mixed (em reajustes/alterações)
     *   - valor_novo?: mixed (em reajustes/alterações)
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function montarTimeline(Contrato $contrato): Collection
    {
        $eventos = collect();

        $contrato->load(['reajustes.alteradoPor:id,name', 'alteracoes.alteradoPor:id,name']);

        foreach ($contrato->reajustes as $r) {
            $eventos->push([
                'id' => "reajuste-{$r->id}",
                'tipo' => 'reajuste',
                'data' => $r->alterado_em->toIso8601String(),
                'titulo' => 'Reajuste de aluguel',
                'subtitulo' => sprintf(
                    '%s — %s → %s (%s%%)',
                    $r->origem,
                    'R$ '.number_format((float) $r->valor_anterior, 2, ',', '.'),
                    'R$ '.number_format((float) $r->valor_novo, 2, ',', '.'),
                    number_format((float) $r->percentual, 2, ',', '.')
                ),
                'usuario' => $r->alteradoPor?->name,
                'valor_anterior' => $r->valor_anterior,
                'valor_novo' => $r->valor_novo,
                'extra' => [
                    'data_aplicacao' => $r->data_aplicacao?->toDateString(),
                    'indice_usado' => $r->indice_usado,
                    'origem' => $r->origem,
                    'observacao' => $r->observacao,
                ],
            ]);
        }

        foreach ($contrato->alteracoes as $a) {
            $eventos->push([
                'id' => "alteracao-{$a->id}",
                'tipo' => 'alteracao',
                'data' => $a->alterado_em->toIso8601String(),
                'titulo' => 'Alteração contratual',
                'subtitulo' => sprintf('Campo %s alterado', $a->campo),
                'usuario' => $a->alteradoPor?->name,
                'valor_anterior' => $a->valor_anterior,
                'valor_novo' => $a->valor_novo,
                'extra' => [
                    'campo' => $a->campo,
                    'data_efetiva' => $a->data_efetiva?->toDateString(),
                    'motivo' => $a->motivo,
                ],
            ]);
        }

        $atividades = Activity::query()
            ->where('subject_type', Contrato::class)
            ->where('subject_id', $contrato->id)
            ->with('causer:id,name')
            ->get();

        foreach ($atividades as $act) {
            $eventos->push([
                'id' => "atividade-{$act->id}",
                'tipo' => 'atividade',
                'data' => $act->created_at->toIso8601String(),
                'titulo' => $this->tituloAtividade($act->event),
                'subtitulo' => $this->resumirChanges($act->attribute_changes?->toArray() ?? []),
                'usuario' => $act->causer?->name,
                'subject_type' => $act->subject_type,
                'subject_id' => $act->subject_id,
                'properties' => $act->attribute_changes?->toArray(),
            ]);
        }

        return $eventos->sortByDesc('data')->values();
    }

    private function tituloAtividade(?string $event): string
    {
        return match ($event) {
            'created' => 'Contrato criado',
            'updated' => 'Contrato atualizado',
            'deleted' => 'Contrato removido',
            default => 'Atividade no contrato',
        };
    }

    /**
     * @param  array{attributes?: array<string, mixed>, old?: array<string, mixed>}  $changes
     */
    private function resumirChanges(array $changes): string
    {
        $attrs = $changes['attributes'] ?? [];
        if (empty($attrs)) {
            return '';
        }

        return collect(array_keys($attrs))->take(4)->implode(', ');
    }
}
