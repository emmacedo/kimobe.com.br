<?php

namespace App\Services;

use App\Models\ConfiguracaoCobrancaKimobe;
use App\Models\FaturaKimobe;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FaturamentoKimobeService
{
    /**
     * Gera faturas mensais para todos os tenants elegíveis.
     */
    public function gerarFaturasMensais(string $referencia): array
    {
        $config = ConfiguracaoCobrancaKimobe::getConfig();
        $tenants = $this->getTenantsElegiveis($referencia);

        $quantidade = 0;
        $valorTotal = 0;

        DB::transaction(function () use ($tenants, $referencia, $config, &$quantidade, &$valorTotal) {
            foreach ($tenants as $tenant) {
                $plano = $tenant->planoAssinatura;
                if (! $plano) {
                    continue;
                }

                // Calcular data de vencimento
                $partes = explode('/', $referencia);
                $mes = (int) $partes[0];
                $ano = (int) $partes[1];
                $dia = min($config->dia_vencimento_fatura, 28);
                $dataVencimento = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);

                FaturaKimobe::create([
                    'tenant_id' => $tenant->id,
                    'plano_id' => $plano->id,
                    'referencia' => $referencia,
                    'valor' => $plano->valor_mensal,
                    'data_vencimento' => $dataVencimento,
                    'status' => 'pendente',
                ]);

                $quantidade++;
                $valorTotal += (float) $plano->valor_mensal;
            }
        });

        return ['quantidade' => $quantidade, 'valor_total' => $valorTotal];
    }

    /**
     * Preview dos tenants que teriam fatura gerada.
     */
    public function previewFaturas(string $referencia): Collection
    {
        return $this->getTenantsElegiveis($referencia)->map(fn ($t) => [
            'id' => $t->id,
            'nome' => $t->nome,
            'plano' => $t->planoAssinatura?->nome ?? '—',
            'valor' => $t->planoAssinatura?->valor_mensal ?? 0,
        ]);
    }

    /**
     * Tenants elegíveis: ativos ou bloqueados, não cortesia, com plano, sem fatura na referência.
     */
    private function getTenantsElegiveis(string $referencia): Collection
    {
        return Tenant::withoutGlobalScopes()
            ->whereIn('status', ['ativo', 'bloqueado'])
            ->where('cortesia', false)
            ->whereNotNull('plano_id')
            ->whereDoesntHave('faturasKimobe', fn ($q) => $q->where('referencia', $referencia))
            ->with('planoAssinatura')
            ->get();
    }
}
