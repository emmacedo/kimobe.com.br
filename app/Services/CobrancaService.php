<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\CobrancaItemExtra;
use App\Models\Contrato;
use App\Models\Repasse;
use App\Models\Titularidade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CobrancaService
{
    /**
     * Gera cobranças mensais para todos os contratos ativos do tenant que
     * ainda não possuem cobrança na referência informada.
     *
     * @return array{quantidade: int, valor_total: float}
     */
    public function gerarCobrancasMensais(string $referencia): array
    {
        $contratos = Contrato::where('status', 'ativo')
            ->whereDoesntHave('cobrancas', fn ($q) => $q->where('referencia', $referencia))
            ->with(['responsabilidades', 'imovel.titularidades'])
            ->get();

        $quantidade = 0;
        $valorTotal = 0;

        DB::transaction(function () use ($contratos, $referencia, &$quantidade, &$valorTotal) {
            foreach ($contratos as $contrato) {
                $cobranca = $this->gerarCobrancaParaContrato($contrato, $referencia);
                $quantidade++;
                $valorTotal += (float) $cobranca->valor_total;
            }
        });

        return ['quantidade' => $quantidade, 'valor_total' => $valorTotal];
    }

    /**
     * Gera uma cobrança individual para um contrato, com possibilidade de override nos valores.
     */
    public function gerarCobrancaIndividual(Contrato $contrato, string $referencia, array $overrides = []): Cobranca
    {
        return DB::transaction(function () use ($contrato, $referencia, $overrides) {
            return $this->gerarCobrancaParaContrato($contrato, $referencia, $overrides);
        });
    }

    /**
     * Lógica interna de geração de cobrança para um contrato.
     * Mapeia responsabilidades para campos fixos e cria itens extras para os não-mapeados.
     */
    private function gerarCobrancaParaContrato(Contrato $contrato, string $referencia, array $overrides = []): Cobranca
    {
        $tenantId = $contrato->tenant_id;

        // Mapear responsabilidades do inquilino para campos fixos
        $valores = $this->mapearResponsabilidades($contrato);

        // Aplicar overrides se fornecidos
        $valores = array_merge($valores, array_filter($overrides, fn ($v) => $v !== null));

        // Calcular data de vencimento
        $partes = explode('/', $referencia);
        $mes = (int) $partes[0];
        $ano = (int) $partes[1];
        $dia = min($contrato->dia_vencimento, 28);
        $dataVencimento = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);

        // Valor total = soma dos campos fixos
        $valorTotal = ($valores['valor_aluguel'] ?? 0)
            + ($valores['valor_condominio'] ?? 0)
            + ($valores['valor_iptu'] ?? 0)
            + ($valores['valor_seguro_incendio'] ?? 0)
            + ($valores['valor_taxa_bombeiros'] ?? 0)
            + ($valores['valor_taxa_extra_condominio'] ?? 0);

        $cobranca = Cobranca::create([
            'tenant_id' => $tenantId,
            'contrato_id' => $contrato->id,
            'referencia' => $referencia,
            'valor_aluguel' => $valores['valor_aluguel'] ?? $contrato->valor_aluguel,
            'valor_condominio' => $valores['valor_condominio'] ?? null,
            'valor_iptu' => $valores['valor_iptu'] ?? null,
            'valor_seguro_incendio' => $valores['valor_seguro_incendio'] ?? null,
            'valor_taxa_bombeiros' => $valores['valor_taxa_bombeiros'] ?? null,
            'valor_taxa_extra_condominio' => $valores['valor_taxa_extra_condominio'] ?? null,
            'valor_total' => $valorTotal,
            'data_vencimento' => $dataVencimento,
            'tipo_geracao' => $overrides ? 'manual' : 'automatica',
            'status' => 'pendente',
        ]);

        // Criar itens extras para responsabilidades não-mapeadas
        $naoMapeadas = $valores['_itens_extras'] ?? [];
        foreach ($naoMapeadas as $item) {
            CobrancaItemExtra::create([
                'tenant_id' => $tenantId,
                'cobranca_id' => $cobranca->id,
                'descricao' => $item['descricao'],
                'valor' => $item['valor'],
            ]);
            $valorTotal += (float) $item['valor'];
        }

        // Atualizar valor_total se houve itens extras
        if (! empty($naoMapeadas)) {
            $cobranca->update(['valor_total' => $valorTotal]);
        }

        // Se modelo garantido: gerar repasses imediatamente
        if ($contrato->modelo_repasse === 'garantido') {
            $this->gerarRepasses($cobranca, $contrato, $dataVencimento);
        }

        // Notificar inquilino sobre nova cobrança
        try {
            app(NotificacaoAdminService::class)->notificarCobrancaGerada($cobranca);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Falha ao notificar cobrança gerada: {$e->getMessage()}");
        }

        return $cobranca;
    }

    /**
     * Mapeia responsabilidades do inquilino para os campos fixos da cobrança.
     * Responsabilidades que não mapeiam viram itens extras.
     */
    private function mapearResponsabilidades(Contrato $contrato): array
    {
        $valores = [
            'valor_aluguel' => (float) $contrato->valor_aluguel,
            'valor_condominio' => null,
            'valor_iptu' => null,
            'valor_seguro_incendio' => null,
            'valor_taxa_bombeiros' => null,
            'valor_taxa_extra_condominio' => null,
            '_itens_extras' => [],
        ];

        $responsabilidades = $contrato->responsabilidades
            ->where('responsavel', 'inquilino');

        foreach ($responsabilidades as $resp) {
            $descLower = mb_strtolower($this->removerAcentos($resp->descricao));
            $valor = $resp->valor ? (float) $resp->valor : null;

            // Valor mensal: anual divide por 12
            if ($valor && $resp->periodicidade === 'anual') {
                $valor = round($valor / 12, 2);
            }

            if (! $valor) {
                continue;
            }

            if (str_contains($descLower, 'condominio') && str_contains($descLower, 'extra')) {
                $valores['valor_taxa_extra_condominio'] = $valor;
            } elseif (str_contains($descLower, 'condominio')) {
                $valores['valor_condominio'] = $valor;
            } elseif (str_contains($descLower, 'iptu')) {
                $valores['valor_iptu'] = $valor;
            } elseif (str_contains($descLower, 'seguro') && str_contains($descLower, 'incendio')) {
                $valores['valor_seguro_incendio'] = $valor;
            } elseif (str_contains($descLower, 'bombeiro')) {
                $valores['valor_taxa_bombeiros'] = $valor;
            } else {
                // Não mapeou — será item extra
                $valores['_itens_extras'][] = [
                    'descricao' => $resp->descricao,
                    'valor' => $valor,
                ];
            }
        }

        return $valores;
    }

    /**
     * Gera repasses para todos os titulares do imóvel de um contrato.
     */
    public function gerarRepasses(Cobranca $cobranca, Contrato $contrato, string $dataBase): void
    {
        $titularidades = Titularidade::withoutGlobalScopes()
            ->where('imovel_id', $contrato->imovel_id)
            ->get();

        $aluguel = (float) $contrato->valor_aluguel;
        $taxaAdminPct = (float) $contrato->taxa_administracao_pct;
        $seguroPct = $contrato->taxa_seguro_inadimplencia_pct
            ? (float) $contrato->taxa_seguro_inadimplencia_pct
            : null;

        // Data prevista: +7 dias corridos
        $dataPrevista = date('Y-m-d', strtotime($dataBase . ' +7 days'));

        foreach ($titularidades as $tit) {
            $percentual = (float) $tit->percentual / 100;
            $bruto = round($aluguel * $percentual, 2);
            $taxaAdmin = round($bruto * $taxaAdminPct / 100, 2);
            $seguro = $seguroPct ? round($bruto * $seguroPct / 100, 2) : null;
            $liquido = $bruto - $taxaAdmin - ($seguro ?? 0);

            $repasse = Repasse::create([
                'tenant_id' => $contrato->tenant_id,
                'cobranca_id' => $cobranca->id,
                'titularidade_id' => $tit->id,
                'valor_aluguel_bruto' => $bruto,
                'taxa_administracao_valor' => $taxaAdmin,
                'taxa_seguro_inadimplencia_valor' => $seguro,
                'valor_liquido' => $liquido,
                'data_prevista' => $dataPrevista,
                'status' => 'pendente',
            ]);

            // Notificar proprietário sobre repasse pendente
            try {
                app(NotificacaoAdminService::class)->notificarRepassePendente($repasse);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Falha ao notificar repasse pendente: {$e->getMessage()}");
            }
        }
    }

    /**
     * Recalcula o valor_total de uma cobrança (soma campos fixos + itens extras).
     */
    public function recalcularTotal(Cobranca $cobranca): void
    {
        $fixos = (float) ($cobranca->valor_aluguel ?? 0)
            + (float) ($cobranca->valor_condominio ?? 0)
            + (float) ($cobranca->valor_iptu ?? 0)
            + (float) ($cobranca->valor_seguro_incendio ?? 0)
            + (float) ($cobranca->valor_taxa_bombeiros ?? 0)
            + (float) ($cobranca->valor_taxa_extra_condominio ?? 0);

        $extras = $cobranca->itensExtras()->sum('valor');

        $cobranca->update(['valor_total' => $fixos + $extras]);
    }

    private function removerAcentos(string $str): string
    {
        return strtr($str, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o',
            'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A',
            'É' => 'E', 'Ê' => 'E', 'Í' => 'I', 'Ó' => 'O',
            'Ô' => 'O', 'Õ' => 'O', 'Ú' => 'U', 'Ç' => 'C',
        ]);
    }
}
