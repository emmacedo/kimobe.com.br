<?php

use App\Models\Contrato;
use App\Models\ItemCobranca;
use App\Models\Scopes\TenantScope;
use App\Services\ItemCobrancaService;
use App\Services\TenantService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Para cada contrato ativo que ainda não possui itens de cobrança de
     * natureza='taxa_admin', gera-os retroativamente para todo o período do
     * contrato (data_inicio → data_fim), espelhando o padrão do aluguel.
     *
     * É necessário porque a partir desta refatoração o cálculo do líquido do
     * repasse passa a somar itens em vez de aplicar a taxa via fórmula direta.
     * Sem este backfill, contratos legacy ficariam com líquido inflado.
     */
    public function up(): void
    {
        $service = app(ItemCobrancaService::class);
        $tenantService = app(TenantService::class);

        $contratos = Contrato::withoutGlobalScopes([TenantScope::class])
            ->where('status', 'ativo')
            ->get();

        DB::transaction(function () use ($contratos, $service, $tenantService) {
            foreach ($contratos as $contrato) {
                $jaTem = ItemCobranca::withoutGlobalScopes([TenantScope::class])
                    ->where('contrato_id', $contrato->id)
                    ->where('natureza', 'taxa_admin')
                    ->exists();

                if ($jaTem) {
                    continue;
                }

                $valor = $contrato->valorTaxaAdministrativa();
                if ($valor <= 0) {
                    continue;
                }

                // O ItemCobrancaService depende do tenant atual para criar os itens.
                $tenantService->setTenant($contrato->tenant);

                $service->criar($contrato, [
                    'descricao' => 'Taxa administrativa',
                    'natureza' => 'taxa_admin',
                    'pagante' => 'proprietario',
                    'recebedor' => 'administradora',
                    'tipo' => 'recorrente',
                    'periodicidade' => 'mensal',
                    'valor_unitario' => $valor,
                    'dia_vencimento' => $contrato->dia_vencimento,
                    'mes_referencia' => $contrato->data_inicio->format('m/Y'),
                    'visivel_inquilino' => false,
                    'criado_por_user_id' => $contrato->criado_por_user_id,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Reversível: remove apenas os itens criados pelo backfill (todos os
        // de natureza='taxa_admin', já que esta é a primeira migração que os cria).
        ItemCobranca::withoutGlobalScopes([TenantScope::class])
            ->where('natureza', 'taxa_admin')
            ->delete();
    }
};
